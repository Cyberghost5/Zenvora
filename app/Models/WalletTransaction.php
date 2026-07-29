<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'wallet_id', 'user_id', 'reference', 'type', 'direction', 'bucket',
    'amount', 'balance_before', 'balance_after', 'description',
    'related_type', 'related_id', 'meta',
])]
class WalletTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'balance_before' => MoneyCast::class,
            'balance_after' => MoneyCast::class,
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    /** Signed, symbol-prefixed amount for the transaction list. */
    public function signedAmount(): string
    {
        return ($this->isCredit() ? '+' : '-').$this->amount->formatWithSymbol();
    }

    public function label(): string
    {
        return match ($this->type) {
            'deposit' => 'Wallet funding',
            'investment' => 'Investment placed',
            'roi' => 'Daily return',
            'capital_return' => 'Capital returned',
            'referral_commission' => 'Referral commission',
            'withdrawal_lock' => 'Withdrawal requested',
            'withdrawal' => 'Withdrawal paid',
            'withdrawal_refund' => 'Withdrawal reversed',
            'admin_credit' => 'Adjustment (credit)',
            'admin_debit' => 'Adjustment (debit)',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}
