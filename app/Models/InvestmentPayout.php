<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_id', 'user_id', 'day_index', 'amount', 'accrual_date',
    'kind', 'wallet_transaction_id',
])]
class InvestmentPayout extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'accrual_date' => 'date',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
