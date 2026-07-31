<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Support\DatabaseErrors;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Three-tier referral commissions, paid when a referred user invests.
 *
 * Commission is calculated on the investment principal and credited to each
 * upline's withdrawable balance. Rates are read from settings at payout time
 * and then snapshotted onto the commission row, so a later rate change does not
 * rewrite what somebody was already paid.
 */
class ReferralService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly WalletService $wallet,
    ) {}

    /**
     * Pay every eligible upline for this investment.
     *
     * @return array<int, ReferralCommission>
     */
    public function payCommissionsFor(Investment $investment): array
    {
        if (! $this->settings->boolean('referral_enabled', true)) {
            return [];
        }

        $plan = $investment->plan;

        // Rule 1: No referral commission on VIP Trial plans or ineligible plans.
        if (! $plan || ! $plan->referral_eligible || str_contains(strtolower($plan->name), 'trial') || str_contains(strtolower($plan->slug), 'trial')) {
            return [];
        }

        $investor = $investment->user;

        if (! $investor) {
            return [];
        }

        $upline = $investor->uplineChain(3);
        $paid = [];

        foreach ($upline as $index => $earner) {
            $tier = $index + 1;
            $rateBp = $this->rateForTier($tier);

            if ($rateBp <= 0) {
                continue;
            }

            // A blocked upline does not accrue. Their downline still pays the
            // tiers below them -- the chain is not collapsed.
            if ($earner->is_blocked) {
                continue;
            }

            // Rule 2: Referral commission is paid ONLY ONCE PER REFERRED USER (first eligible investment only).
            $alreadyEarnedForUser = ReferralCommission::query()
                ->where('user_id', $earner->id)
                ->where('source_user_id', $investor->id)
                ->exists();

            if ($alreadyEarnedForUser) {
                continue;
            }

            $amount = $investment->principal->percentageBp($rateBp);

            // Sub-kobo commission rounds to zero; skip rather than write a
            // meaningless zero-value ledger row.
            if (! $amount->isPositive()) {
                continue;
            }

            $commission = $this->credit($investment, $earner, $investor, $tier, $rateBp, $amount);

            if ($commission) {
                $paid[] = $commission;
            }
        }

        return $paid;
    }

    private function credit(
        Investment $investment,
        User $earner,
        User $investor,
        int $tier,
        int $rateBp,
        Money $amount,
    ): ?ReferralCommission {
        try {
            return DB::transaction(function () use ($investment, $earner, $investor, $tier, $rateBp, $amount) {
                // Claim the slot first. The unique index on
                // (investment_id, user_id, tier) means a retry or a double
                // dispatch throws here instead of paying twice.
                $commission = ReferralCommission::query()->create([
                    'user_id' => $earner->id,
                    'source_user_id' => $investor->id,
                    'investment_id' => $investment->id,
                    'tier' => $tier,
                    'rate_bp' => $rateBp,
                    'amount' => $amount,
                ]);

                $transaction = $this->wallet->creditReferralCommission(
                    user: $earner,
                    amount: $amount,
                    source: $commission,
                    description: sprintf(
                        'Tier %d referral commission from %s',
                        $tier,
                        $investor->name,
                    ),
                );

                $commission->update(['wallet_transaction_id' => $transaction->id]);

                return $commission;
            });
        } catch (QueryException $e) {
            // Duplicate key: already paid. Anything else is a real fault.
            if ($this->isDuplicateKey($e)) {
                return null;
            }

            Log::error('Referral commission failed', [
                'investment_id' => $investment->id,
                'earner_id' => $earner->id,
                'tier' => $tier,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function rateForTier(int $tier): int
    {
        return $this->settings->integer("referral_tier_{$tier}_bp", match ($tier) {
            1 => 1_000,
            2 => 500,
            3 => 200,
            default => 0,
        });
    }

    /**
     * @return array<int, array{tier: int, rate_bp: int, label: string}>
     */
    public function tierTable(): array
    {
        return collect([1, 2, 3])
            ->map(fn (int $tier) => [
                'tier' => $tier,
                'rate_bp' => $this->rateForTier($tier),
                'label' => rtrim(rtrim(number_format($this->rateForTier($tier) / 100, 2, '.', ''), '0'), '.').'%',
            ])
            ->all();
    }

    /**
     * Downline counts per tier, for the referral dashboard.
     *
     * @return array<int, int>
     */
    public function downlineCounts(User $user): array
    {
        $counts = [];
        $currentIds = [$user->id];

        for ($tier = 1; $tier <= 3; $tier++) {
            $ids = User::query()
                ->whereIn('referred_by', $currentIds)
                ->pluck('id')
                ->all();

            $counts[$tier] = count($ids);

            if ($ids === []) {
                // No point querying deeper tiers once the tree runs out.
                for ($rest = $tier + 1; $rest <= 3; $rest++) {
                    $counts[$rest] = 0;
                }
                break;
            }

            $currentIds = $ids;
        }

        return $counts;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return DatabaseErrors::isUniqueViolation($e);
    }
}
