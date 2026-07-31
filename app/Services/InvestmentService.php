<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\Investment;
use App\Models\InvestmentPayout;
use App\Models\Plan;
use App\Models\User;
use App\Support\DatabaseErrors;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Subscribing to plans, and accruing the daily returns they promise.
 */
class InvestmentService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly ReferralService $referrals,
    ) {}

    // -----------------------------------------------------------------
    // Subscribing
    // -----------------------------------------------------------------

    /**
     * Place an investment, funded from the user's deposit balance.
     *
     * @throws InsufficientFundsException|RuntimeException
     */
    public function subscribe(User $user, Plan $plan, ?Money $amount = null): Investment
    {
        $amount = $amount ?? $plan->min_amount;

        if (! $plan->is_active) {
            throw new RuntimeException('That plan is no longer available.');
        }

        if ($plan->min_amount->equals($plan->max_amount)) {
            if (! $amount->equals($plan->min_amount)) {
                throw new RuntimeException(sprintf(
                    'The fixed investment amount for %s is %s.',
                    $plan->name,
                    $plan->min_amount->formatWithSymbol(),
                ));
            }
        } else {
            if ($amount->lessThan($plan->min_amount)) {
                throw new RuntimeException(sprintf(
                    'The minimum for %s is %s.',
                    $plan->name,
                    $plan->min_amount->formatWithSymbol(),
                ));
            }

            if ($amount->greaterThan($plan->max_amount)) {
                throw new RuntimeException(sprintf(
                    'The maximum for %s is %s.',
                    $plan->name,
                    $plan->max_amount->formatWithSymbol(),
                ));
            }
        }

        $wallet = $user->ensureWallet();

        if ($wallet->deposit_balance->lessThan($amount)) {
            throw InsufficientFundsException::for('deposit', $amount, $wallet->deposit_balance);
        }

        $investment = DB::transaction(function () use ($user, $plan, $amount) {
            $dailyPayout = $plan->dailyPayoutFor($amount);

            // A principal so small that the daily rate rounds to zero kobo would
            // run its full term and pay nothing.
            if (! $dailyPayout->isPositive()) {
                throw new RuntimeException('That amount is too small to earn a daily return on this plan.');
            }

            $today = Carbon::today();

            $investment = Investment::query()->create([
                'reference' => $this->newReference(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,

                'principal' => $amount,

                // Terms are frozen onto the contract here.
                'daily_roi_bp' => $plan->daily_roi_bp,
                'duration_days' => $plan->duration_days,
                'return_capital' => $plan->return_capital,
                'daily_payout' => $dailyPayout,
                'total_expected_roi' => $dailyPayout->multiply($plan->duration_days),

                'started_on' => $today,
                'matures_on' => $today->copy()->addDays($plan->duration_days),
            ]);

            $this->wallet->debitForInvestment($user, $amount, $investment);

            return $investment;
        });

        // Commissions are paid after the investment transaction commits: a
        // failure to pay an upline must not roll back a valid investment.
        $this->referrals->payCommissionsFor($investment->fresh(['user', 'plan']));

        return $investment;
    }

    // -----------------------------------------------------------------
    // Daily accrual
    // -----------------------------------------------------------------

    /**
     * Accrue one day of ROI for a single investment.
     *
     * Idempotent: the unique index on
     * (investment_id, accrual_date, kind) rejects a second attempt for the same
     * day, so running the command twice cannot double-pay.
     *
     * @return bool Whether a payout was actually written.
     */
    public function accrueDay(Investment $investment, ?Carbon $date = null): bool
    {
        $targetDate = ($date ?? Carbon::today())->startOfDay();

        if (! $investment->isActive()) {
            return false;
        }

        // Nothing accrues before the start date.
        if ($targetDate->lt($investment->started_on->copy()->startOfDay())) {
            return false;
        }

        if ($investment->hasRunFullTerm()) {
            $this->complete($investment);

            return false;
        }

        // Unless an explicit date was passed (e.g. CLI backfill), enforce 24 full hours.
        $nextDue = $investment->nextPayoutAt();
        if ($date === null && $nextDue && now()->lt($nextDue)) {
            return false;
        }

        try {
            return DB::transaction(function () use ($investment, $targetDate) {
                /** @var Investment $locked */
                $locked = Investment::query()
                    ->whereKey($investment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Re-check under the lock: a concurrent run may have moved it on.
                if ($locked->status !== 'active' || $locked->days_paid >= $locked->duration_days) {
                    return false;
                }

                $dayIndex = $locked->days_paid + 1;

                // Claim the day. Throws on a duplicate rather than paying again.
                $payout = InvestmentPayout::query()->create([
                    'investment_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'day_index' => $dayIndex,
                    'amount' => $locked->daily_payout,
                    'accrual_date' => $targetDate->toDateString(),
                    'kind' => 'roi',
                ]);

                $transaction = $this->wallet->creditRoi(
                    user: $locked->user,
                    amount: $locked->daily_payout,
                    investment: $locked,
                    description: sprintf('Day %d of %d return on %s', $dayIndex, $locked->duration_days, $locked->reference),
                );

                $payout->update(['wallet_transaction_id' => $transaction->id]);

                $locked->days_paid = $dayIndex;
                $locked->total_roi_paid = $locked->total_roi_paid->add($locked->daily_payout);
                $locked->last_accrued_on = $targetDate;
                $locked->save();

                if ($locked->days_paid >= $locked->duration_days) {
                    $this->complete($locked, $targetDate);
                }

                return true;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Close a contract that has run its term, returning capital if the plan
     * promised it.
     */
    public function complete(Investment $investment, ?Carbon $date = null): void
    {
        $date = ($date ?? Carbon::today())->startOfDay();

        DB::transaction(function () use ($investment, $date) {
            /** @var Investment $locked */
            $locked = Investment::query()
                ->whereKey($investment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'active') {
                return;
            }

            if ($locked->return_capital) {
                try {
                    $payout = InvestmentPayout::query()->create([
                        'investment_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'day_index' => $locked->duration_days,
                        'amount' => $locked->principal,
                        'accrual_date' => $date->toDateString(),
                        'kind' => 'capital_return',
                    ]);

                    $transaction = $this->wallet->creditCapitalReturn(
                        user: $locked->user,
                        amount: $locked->principal,
                        investment: $locked,
                    );

                    $payout->update(['wallet_transaction_id' => $transaction->id]);
                } catch (QueryException $e) {
                    // Capital already returned on an earlier attempt.
                    if (! $this->isDuplicateKey($e)) {
                        throw $e;
                    }
                }
            }

            $locked->status = 'completed';
            $locked->completed_at = now();
            $locked->save();
        });
    }

    /**
     * Accrue every investment that is due, for the given date.
     *
     * @return array{processed: int, paid: int, completed: int}
     */
    public function accrueAllDue(?Carbon $date = null): array
    {
        $targetDate = ($date ?? Carbon::today())->startOfDay();

        $stats = ['processed' => 0, 'paid' => 0, 'completed' => 0];

        Investment::query()
            ->active()
            ->where('started_on', '<=', $targetDate->toDateString())
            ->where(function ($query) use ($targetDate) {
                // Skip anything already accrued today.
                $query->whereNull('last_accrued_on')
                    ->orWhere('last_accrued_on', '<', $targetDate->toDateString());
            })
            ->with(['user', 'plan'])
            ->chunkById(200, function ($investments) use ($date, $targetDate, &$stats) {
                foreach ($investments as $investment) {
                    // Check if 24 hours have elapsed for this investment
                    $nextDue = $investment->nextPayoutAt();
                    if ($date === null && $nextDue && now()->lt($nextDue)) {
                        continue;
                    }

                    $stats['processed']++;

                    if ($this->accrueDay($investment, $date)) {
                        $stats['paid']++;
                    }

                    if ($investment->fresh()?->status === 'completed') {
                        $stats['completed']++;
                    }
                }
            });

        return $stats;
    }

    /**
     * Cancel an active contract. Refunds the principal to the deposit balance
     * and leaves any ROI already paid alone.
     */
    public function cancel(Investment $investment, string $reason, bool $refundPrincipal = true): void
    {
        DB::transaction(function () use ($investment, $reason, $refundPrincipal) {
            /** @var Investment $locked */
            $locked = Investment::query()
                ->whereKey($investment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'active') {
                throw new RuntimeException('Only an active investment can be cancelled.');
            }

            if ($refundPrincipal) {
                $this->wallet->adminAdjust(
                    user: $locked->user,
                    amount: $locked->principal,
                    bucket: 'deposit',
                    direction: 'credit',
                    description: 'Principal refunded: '.$reason,
                    related: $locked,
                );
            }

            $locked->status = 'cancelled';
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->save();
        });
    }

    private function newReference(): string
    {
        return 'INV-'.strtoupper(bin2hex(random_bytes(6)));
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return DatabaseErrors::isUniqueViolation($e);
    }
}
