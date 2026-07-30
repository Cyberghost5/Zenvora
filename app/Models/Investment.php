<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'user_id', 'plan_id', 'principal', 'daily_roi_bp',
    'duration_days', 'return_capital', 'daily_payout', 'total_expected_roi',
    'started_on', 'matures_on',
])]
class Investment extends Model
{
    protected function casts(): array
    {
        return [
            'principal' => MoneyCast::class,
            'daily_payout' => MoneyCast::class,
            'total_expected_roi' => MoneyCast::class,
            'total_roi_paid' => MoneyCast::class,
            'return_capital' => 'boolean',
            'started_on' => 'date',
            'matures_on' => 'date',
            'last_accrued_on' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(InvestmentPayout::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // -----------------------------------------------------------------
    // Progress
    // -----------------------------------------------------------------

    public function daysRemaining(): int
    {
        return max(0, $this->duration_days - $this->days_paid);
    }

    /** 0-100, for the progress bar. */
    public function progressPercent(): int
    {
        if ($this->duration_days === 0) {
            return 100;
        }

        return (int) min(100, round(($this->days_paid / $this->duration_days) * 100));
    }

    public function outstandingRoi(): Money
    {
        return $this->total_expected_roi->subtract($this->total_roi_paid);
    }

    public function totalReturn(): Money
    {
        return $this->return_capital
            ? $this->total_expected_roi->add($this->principal)
            : $this->total_expected_roi;
    }

    public function dailyRoiLabel(): string
    {
        return rtrim(rtrim(number_format($this->daily_roi_bp / 100, 2, '.', ''), '0'), '.').'%';
    }

    /**
     * Whether the contract has run its full term. Checked against days paid
     * rather than the calendar, so a missed accrual run cannot close a contract
     * that still owes the user money.
     */
    public function hasRunFullTerm(): bool
    {
        return $this->days_paid >= $this->duration_days;
    }

    /**
     * Calculates the timestamp for when the next daily return is due.
     */
    public function nextPayoutAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->status !== 'active' || $this->hasRunFullTerm()) {
            return null;
        }

        $start = $this->created_at ?? \Illuminate\Support\Carbon::parse($this->started_on);

        return $start->copy()->addDays($this->days_paid + 1);
    }
}
