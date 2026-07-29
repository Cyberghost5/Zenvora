<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id'])]
class Wallet extends Model
{
    protected function casts(): array
    {
        return [
            'deposit_balance' => MoneyCast::class,
            'withdrawable_balance' => MoneyCast::class,
            'locked_balance' => MoneyCast::class,
            'total_deposited' => MoneyCast::class,
            'total_withdrawn' => MoneyCast::class,
            'total_invested' => MoneyCast::class,
            'total_roi_earned' => MoneyCast::class,
            'total_referral_earned' => MoneyCast::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Everything the user owns right now, across all buckets. Display only --
     * this figure is not spendable as a unit.
     */
    public function totalBalance(): Money
    {
        return $this->deposit_balance
            ->add($this->withdrawable_balance)
            ->add($this->locked_balance);
    }
}
