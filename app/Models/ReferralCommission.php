<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'source_user_id', 'investment_id', 'tier', 'rate_bp',
    'amount', 'wallet_transaction_id',
])]
class ReferralCommission extends Model
{
    protected function casts(): array
    {
        return ['amount' => MoneyCast::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function rateLabel(): string
    {
        return rtrim(rtrim(number_format($this->rate_bp / 100, 2, '.', ''), '0'), '.').'%';
    }

    public function tierLabel(): string
    {
        return match ($this->tier) {
            1 => 'Direct referral',
            2 => 'Second tier',
            3 => 'Third tier',
            default => "Tier {$this->tier}",
        };
    }
}
