<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'amount', 'max_uses', 'expires_at', 'is_active', 'note', 'created_by'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->used_count >= $this->max_uses;
    }

    /**
     * Whether this coupon could be redeemed by anyone right now. Per-user
     * eligibility is a separate check, and the authoritative guard against
     * double redemption is the unique index on coupon_redemptions.
     */
    public function isRedeemable(): bool
    {
        return $this->is_active && ! $this->isExpired() && ! $this->isExhausted();
    }

    public function remainingUses(): int
    {
        return max(0, $this->max_uses - $this->used_count);
    }

    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_active => 'Disabled',
            $this->isExpired() => 'Expired',
            $this->isExhausted() => 'Fully used',
            default => 'Active',
        };
    }

    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = 'ZVC-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }
}
