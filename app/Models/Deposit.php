<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Laravel 13 drops any attribute absent from this list on create()/update(),
// silently. Every field the service layer writes has to appear here.
#[Fillable([
    'reference', 'user_id', 'channel', 'amount', 'fee', 'status',
    'gateway_reference', 'gateway_payload', 'coupon_id', 'proof_path',
    'depositor_name', 'depositor_account', 'paid_to_account', 'paid_on',
    'reviewed_by', 'reviewed_at', 'rejection_reason', 'credited_at',
])]
class Deposit extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'fee' => MoneyCast::class,
            'gateway_payload' => 'array',
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', 'awaiting_review');
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'awaiting_review'], true);
    }

    public function channelLabel(): string
    {
        return match ($this->channel) {
            'paystack' => 'Paystack',
            'flutterwave' => 'Flutterwave',
            'korapay' => 'Korapay',
            'coupon' => 'Coupon',
            'manual' => 'Bank transfer',
            default => ucfirst($this->channel),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Awaiting payment',
            'awaiting_review' => 'Under review',
            'successful' => 'Credited',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /** Tailwind classes for the status pill. */
    public function statusTone(): string
    {
        return match ($this->status) {
            'successful' => 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20',
            'awaiting_review', 'pending' => 'bg-amber-500/10 text-amber-400 ring-amber-500/20',
            default => 'bg-rose-500/10 text-rose-400 ring-rose-500/20',
        };
    }

    public static function newReference(): string
    {
        return 'DEP-'.strtoupper(bin2hex(random_bytes(6)));
    }
}
