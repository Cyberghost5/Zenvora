<?php

namespace App\Services\Gateways;

use App\Models\Deposit;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaystackGateway implements PaymentGateway
{
    private const BASE = 'https://api.paystack.co';

    public function key(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return 'Paystack';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.paystack.secret'));
    }

    public function initiate(Deposit $deposit, string $callbackUrl): string
    {
        $response = Http::withToken((string) config('services.paystack.secret'))
            ->acceptJson()
            ->timeout(20)
            ->post(self::BASE.'/transaction/initialize', [
                'email' => $deposit->user->email,
                // Paystack expects the smallest currency unit, which is exactly
                // how the amount is already stored.
                'amount' => $deposit->amount->minor,
                'currency' => config('zenvora.currency', 'NGN'),
                'reference' => $deposit->reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'user_id' => $deposit->user_id,
                    'deposit_id' => $deposit->id,
                ],
            ]);

        $body = $response->json() ?? [];

        Log::info('[PAYSTACK_INITIATE] Response received', [
            'deposit_reference' => $deposit->reference,
            'user_id' => $deposit->user_id,
            'email' => $deposit->user->email,
            'status_code' => $response->status(),
            'response' => $body,
        ]);

        if (! $response->successful() || ! ($body['status'] ?? false)) {
            throw new RuntimeException(
                'Paystack could not start this payment: '.($body['message'] ?? $response->status())
            );
        }

        $url = $body['data']['authorization_url'] ?? null;

        if (! $url) {
            throw new RuntimeException('Paystack did not return a checkout URL.');
        }

        $deposit->update([
            'gateway_reference' => $body['data']['reference'] ?? $deposit->reference,
        ]);

        return $url;
    }

    public function verify(string $reference): GatewayResult
    {
        $response = Http::withToken((string) config('services.paystack.secret'))
            ->acceptJson()
            ->timeout(20)
            ->get(self::BASE.'/transaction/verify/'.urlencode($reference));

        $body = $response->json() ?? [];

        Log::info('[PAYSTACK_VERIFY] Response received', [
            'reference' => $reference,
            'status_code' => $response->status(),
            'response' => $body,
        ]);

        if (! $response->successful() || ! ($body['status'] ?? false)) {
            return GatewayResult::failed(
                $reference,
                $body['message'] ?? 'Paystack could not verify this payment.',
                is_array($body) ? $body : [],
            );
        }

        $data = $body['data'] ?? [];

        return new GatewayResult(
            successful: ($data['status'] ?? null) === 'success',
            amount: Money::fromMinor((int) ($data['amount'] ?? 0)),
            reference: (string) ($data['reference'] ?? $reference),
            currency: (string) ($data['currency'] ?? config('zenvora.currency', 'NGN')),
            message: $data['gateway_response'] ?? null,
            payload: $data,
        );
    }

    /**
     * Verify a webhook came from Paystack by recomputing its HMAC.
     */
    public function signatureIsValid(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret');

        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }
}
