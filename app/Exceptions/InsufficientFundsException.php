<?php

namespace App\Exceptions;

use App\Support\Money;
use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public static function for(string $bucket, Money $requested, Money $available): self
    {
        $label = match ($bucket) {
            'deposit' => 'deposit balance',
            'withdrawable' => 'withdrawable balance',
            default => $bucket.' balance',
        };

        return new self(sprintf(
            'Your %s is %s, which is not enough for %s.',
            $label,
            $available->formatWithSymbol(),
            $requested->formatWithSymbol(),
        ));
    }
}
