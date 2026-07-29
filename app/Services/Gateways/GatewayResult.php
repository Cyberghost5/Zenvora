<?php

namespace App\Services\Gateways;

use App\Support\Money;

/**
 * The gateway's own account of a transaction.
 */
final class GatewayResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly Money $amount,
        public readonly string $reference,
        public readonly string $currency,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {}

    public static function failed(string $reference, string $message, array $payload = []): self
    {
        return new self(
            successful: false,
            amount: Money::zero(),
            reference: $reference,
            currency: config('zenvora.currency', 'NGN'),
            message: $message,
            payload: $payload,
        );
    }
}
