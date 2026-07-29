<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a BIGINT minor-unit column to and from a Money value object, so model
 * code never handles a bare integer of kobo by accident.
 *
 * @implements CastsAttributes<Money, Money|int|string|null>
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromMinor((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->minor;
        }

        // A bare int assigned to a money attribute is taken as minor units,
        // matching what the column actually holds.
        if (is_int($value)) {
            return $value;
        }

        return Money::fromMajor($value)->minor;
    }
}
