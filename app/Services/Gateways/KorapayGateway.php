<?php

namespace App\Services\Gateways;

use App\Models\Deposit;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KorapayGateway implements PaymentGateway
{
    private const BASE = 'https://api.korapay.com/merchant/api/v1';

    public function key(): string
    {
        return 'korapay';
    }

    public function label(): string
    {
        return 'Korapay';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.korapay.secret'));
    }

    public function initiate(Deposit $deposit, string $callbackUrl): string
    {
        $response = Http::withToken((string) config('services.korapay.secret'))
            ->acceptJson()
            ->timeout(20)
            ->post(self::BASE.'/charges/initialize', [
                'reference' => $deposit->reference,
                'amount' => number_format($deposit->amount->toMajor(), 2, '.', ''),
                'currency' => config('zenvora.currency', 'NGN'),
                'customer' => [
                    'name' => $deposit->user->name,
                    'email' => $deposit->user->email,
                ],
                'notification_url' => route('webhooks.korapay'),
                'redirect_url' => $callbackUrl,
                'description' => config('app.name').' wallet deposit',
                'metadata' => [
                    'user_id' => $deposit->user_id,
                    'deposit_id' => $deposit->id,
                ],
            ]);

        $body = $response->json() ?? [];

        Log::info('[KORAPAY_INITIATE] Response received', [
            'deposit_reference' => $deposit->reference,
            'user_id' => $deposit->user_id,
            'email' => $deposit->user->email,
            'status_code' => $response->status(),
            'response' => $body,
        ]);

        if (! $response->successful() || ! ($body['status'] ?? false)) {
            throw new RuntimeException(
                'Korapay could not start this payment: '.($body['message'] ?? $response->status())
            );
        }

        $url = $body['data']['checkout_url'] ?? null;

        if (! $url) {
            throw new RuntimeException('Korapay did not return a checkout URL.');
        }

        $deposit->update([
            'gateway_reference' => $body['data']['reference'] ?? $deposit->reference,
        ]);

        return $url;
    }

    public function verify(string $reference): GatewayResult
    {
        $response = Http::withToken((string) config('services.korapay.secret'))
            ->acceptJson()
            ->timeout(20)
            ->get(self::BASE.'/charges/'.urlencode($reference));

        $body = $response->json() ?? [];

        Log::info('[KORAPAY_VERIFY] Response received', [
            'reference' => $reference,
            'status_code' => $response->status(),
            'response' => $body,
        ]);

        if (! $response->successful() || ! ($body['status'] ?? false)) {
            return GatewayResult::failed(
                $reference,
                $body['message'] ?? 'Korapay could not verify this payment.',
                is_array($body) ? $body : [],
            );
        }

        $data = $body['data'] ?? [];

        return new GatewayResult(
            successful: strtolower((string) ($data['status'] ?? '')) === 'success',
            amount: Money::fromMajor((string) ($data['amount'] ?? '0')),
            reference: (string) ($data['reference'] ?? $reference),
            currency: (string) ($data['currency'] ?? config('zenvora.currency', 'NGN')),
            message: $data['status'] ?? null,
            payload: $data,
        );
    }

    /**
     * Korapay sends HMAC SHA-512 signature in x-korapay-signature header.
     */
    public function signatureIsValid(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.korapay.secret');

        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }
}
