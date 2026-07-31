<?php

namespace App\Services\Gateways;

use App\Models\Deposit;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwaveGateway implements PaymentGateway
{
    private const BASE = 'https://api.flutterwave.com/v3';

    public function key(): string
    {
        return 'flutterwave';
    }

    public function label(): string
    {
        return 'Flutterwave';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.flutterwave.secret'));
    }

    public function initiate(Deposit $deposit, string $callbackUrl): string
    {
        $response = Http::withToken((string) config('services.flutterwave.secret'))
            ->acceptJson()
            ->timeout(20)
            ->post(self::BASE.'/payments', [
                'tx_ref' => $deposit->reference,
                // Flutterwave takes major units, unlike Paystack.
                'amount' => number_format($deposit->amount->toMajor(), 2, '.', ''),
                'currency' => config('zenvora.currency', 'NGN'),
                'redirect_url' => $callbackUrl,
                'customer' => [
                    'email' => $deposit->user->email,
                    'name' => $deposit->user->name,
                    'phonenumber' => $deposit->user->phone,
                ],
                'customizations' => [
                    'title' => config('app.name').' wallet funding',
                ],
                'meta' => [
                    'user_id' => $deposit->user_id,
                    'deposit_id' => $deposit->id,
                ],
            ]);

        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['status'] ?? null) !== 'success') {
            throw new RuntimeException(
                'Flutterwave could not start this payment: '.($body['message'] ?? $response->status())
            );
        }

        $url = $body['data']['link'] ?? null;

        if (! $url) {
            throw new RuntimeException('Flutterwave did not return a checkout URL.');
        }

        return $url;
    }

    /**
     * Flutterwave's callback hands back a numeric transaction id, so verify by
     * id when given one and fall back to the tx_ref lookup otherwise.
     */
    public function verify(string $reference): GatewayResult
    {
        $url = ctype_digit($reference)
            ? self::BASE.'/transactions/'.$reference.'/verify'
            : self::BASE.'/transactions/verify_by_reference?tx_ref='.urlencode($reference);

        $response = Http::withToken((string) config('services.flutterwave.secret'))
            ->acceptJson()
            ->timeout(20)
            ->get($url);

        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['status'] ?? null) !== 'success') {
            return GatewayResult::failed(
                $reference,
                $body['message'] ?? 'Flutterwave could not verify this payment.',
                is_array($body) ? $body : [],
            );
        }

        $data = $body['data'] ?? [];

        return new GatewayResult(
            successful: ($data['status'] ?? null) === 'successful',
            amount: Money::fromMajor((string) ($data['amount'] ?? '0')),
            reference: (string) ($data['tx_ref'] ?? $reference),
            currency: (string) ($data['currency'] ?? config('zenvora.currency', 'NGN')),
            message: $data['processor_response'] ?? null,
            payload: $data,
        );
    }

    /**
     * Flutterwave sends a shared secret in the verif-hash header rather than
     * an HMAC of the body.
     */
    public function signatureIsValid(string $rawBody, ?string $signature): bool
    {
        $hash = (string) config('services.flutterwave.webhook_hash');

        if ($hash === '' || ! $signature) {
            return false;
        }

        return hash_equals($hash, $signature);
    }
}
