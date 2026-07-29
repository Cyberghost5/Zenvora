<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'tagline', 'description', 'min_amount', 'max_amount',
    'daily_roi_bp', 'fixed_daily_payout', 'duration_days', 'return_capital', 'referral_eligible',
    'is_active', 'sort_order',
])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'min_amount' => MoneyCast::class,
            'max_amount' => MoneyCast::class,
            'fixed_daily_payout' => MoneyCast::class,
            'return_capital' => 'boolean',
            'referral_eligible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('min_amount');
    }

    // -----------------------------------------------------------------
    // Presentation of the plan's terms
    // -----------------------------------------------------------------

    /** Daily rate as a percentage, e.g. 2.5 for 250bp. */
    public function dailyRoiPercent(): float
    {
        if ($this->fixed_daily_payout !== null && $this->min_amount->minor > 0) {
            return ($this->fixed_daily_payout->minor / $this->min_amount->minor) * 100;
        }

        return $this->daily_roi_bp / 100;
    }

    /** Trimmed for display: "2.5%" not "2.50%". */
    public function dailyRoiLabel(): string
    {
        return rtrim(rtrim(number_format($this->dailyRoiPercent(), 2, '.', ''), '0'), '.').'%';
    }

    /** Total return over the full term, e.g. 25% for 2.5% x 10 days. */
    public function totalRoiPercent(): float
    {
        return $this->dailyRoiPercent() * $this->duration_days;
    }

    public function totalRoiLabel(): string
    {
        return rtrim(rtrim(number_format($this->totalRoiPercent(), 2, '.', ''), '0'), '.').'%';
    }

    public function dailyPayoutFor(Money $principal): Money
    {
        if ($this->fixed_daily_payout !== null) {
            return $this->fixed_daily_payout;
        }

        return $principal->percentageBp($this->daily_roi_bp);
    }

    public function totalRoiFor(Money $principal): Money
    {
        return $this->dailyPayoutFor($principal)->multiply($this->duration_days);
    }

    /** Principal plus total ROI, i.e. what the user ends up with. */
    public function totalReturnFor(Money $principal): Money
    {
        $roi = $this->totalRoiFor($principal);

        return $this->return_capital ? $roi->add($principal) : $roi;
    }

    public function getPriceAttribute(): Money
    {
        return $this->min_amount;
    }

    public function accepts(Money $amount): bool
    {
        if ($this->min_amount->equals($this->max_amount)) {
            return $amount->equals($this->min_amount);
        }

        return ! $amount->lessThan($this->min_amount)
            && ! $amount->greaterThan($this->max_amount);
    }

    public function durationLabel(): string
    {
        return $this->duration_days.' '.str('day')->plural($this->duration_days);
    }
}
