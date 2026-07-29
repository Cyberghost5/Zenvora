<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// See the note on Deposit: unlisted attributes are dropped without warning.
#[Fillable([
    'reference', 'user_id', 'amount', 'fee', 'net_amount', 'status',
    'bank_name', 'account_number', 'account_name',
    'processed_by', 'processed_at', 'rejection_reason', 'payment_note',
])]
class Withdrawal extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'fee' => MoneyCast::class,
            'net_amount' => MoneyCast::class,
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /** While open, the amount is held in the wallet's locked bucket. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending review',
            'processing' => 'Processing',
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'paid' => 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20',
            'pending', 'processing' => 'bg-amber-500/10 text-amber-400 ring-amber-500/20',
            default => 'bg-rose-500/10 text-rose-400 ring-rose-500/20',
        };
    }

    public static function newReference(): string
    {
        return 'WDR-'.strtoupper(bin2hex(random_bytes(6)));
    }
}
