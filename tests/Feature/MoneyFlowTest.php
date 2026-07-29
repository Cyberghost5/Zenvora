<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\DepositService;
use App\Services\InvestmentService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The money path, end to end: fund, invest, accrue, mature, withdraw.
 *
 * These are the assertions that matter most -- a rounding or idempotency bug
 * here is the difference between a working ledger and an unrecoverable one.
 */
class MoneyFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?User $referrer = null): User
    {
        $user = User::query()->create([
            'name' => 'Test Person',
            'email' => 'u'.uniqid().'@example.test',
            'phone' => '+234'.random_int(1_000_000_000, 9_999_999_999),
            'password' => 'password123',
            'referral_code' => User::generateReferralCode(),
            'referred_by' => $referrer?->id,
        ]);

        $user->wallet()->create();

        return $user;
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::query()->create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-'.uniqid(),
            'min_amount' => 100_000,          // ₦1,000
            'max_amount' => 10_000_000,       // ₦100,000
            'daily_roi_bp' => 200,            // 2% daily
            'duration_days' => 5,
            'return_capital' => true,
            'referral_eligible' => true,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    // -----------------------------------------------------------------
    // Money value object
    // -----------------------------------------------------------------

    public function test_money_parses_decimal_strings_without_float_drift(): void
    {
        // The classic float trap: 0.1 as a float times 100 is 10.000000000000002.
        $this->assertSame(10, Money::fromMajor('0.10')->minor);
        $this->assertSame(2_075, Money::fromMajor('20.75')->minor);
        $this->assertSame(1_500_000, Money::fromMajor('15,000')->minor);
        $this->assertSame(1_500_050, Money::fromMajor('₦15,000.50')->minor);

        // Sub-kobo precision is truncated, never rounded up in the user's favour.
        $this->assertSame(1_299, Money::fromMajor('12.999')->minor);
    }

    public function test_percentage_floors_to_whole_kobo(): void
    {
        // 2% of ₦10.01 is 20.02 kobo, which must floor to 20.
        $this->assertSame(20, Money::fromMinor(1_001)->percentageBp(200)->minor);

        // 10% of ₦1,000 is exactly ₦100.
        $this->assertSame(10_000, Money::fromMinor(100_000)->percentageBp(1_000)->minor);
    }

    // -----------------------------------------------------------------
    // Bucket separation
    // -----------------------------------------------------------------

    public function test_deposits_land_in_deposit_balance_and_are_not_withdrawable(): void
    {
        $user = $this->makeUser();
        $coupon = Coupon::query()->create([
            'code' => 'TESTCOUPON1',
            'amount' => 500_000,
            'max_uses' => 1,
            'is_active' => true,
        ]);

        app(DepositService::class)->redeemCoupon($user, 'TESTCOUPON1');

        $wallet = $user->fresh()->wallet;

        $this->assertSame(500_000, $wallet->deposit_balance->minor);

        // The whole point of the two-bucket model: funded money is not cashable.
        $this->assertSame(0, $wallet->withdrawable_balance->minor);
    }

    public function test_a_coupon_cannot_be_redeemed_twice_by_the_same_user(): void
    {
        $user = $this->makeUser();

        Coupon::query()->create([
            'code' => 'ONCEONLY',
            'amount' => 200_000,
            'max_uses' => 5,   // plenty of uses left, but not for this user
            'is_active' => true,
        ]);

        app(DepositService::class)->redeemCoupon($user, 'ONCEONLY');

        $this->expectExceptionMessage('You have already redeemed that coupon.');
        app(DepositService::class)->redeemCoupon($user, 'ONCEONLY');
    }

    // -----------------------------------------------------------------
    // Investing
    // -----------------------------------------------------------------

    public function test_investing_moves_money_out_of_the_deposit_balance(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(1_000_000), $plan);

        $investment = app(InvestmentService::class)
            ->subscribe($user->fresh(), $plan, Money::fromMinor(500_000));

        $wallet = $user->fresh()->wallet;

        $this->assertSame(500_000, $wallet->deposit_balance->minor);
        $this->assertSame(500_000, $investment->principal->minor);

        // 2% of ₦5,000 == ₦100 per day, ₦500 over five days.
        $this->assertSame(10_000, $investment->daily_payout->minor);
        $this->assertSame(50_000, $investment->total_expected_roi->minor);
    }

    public function test_investing_more_than_the_deposit_balance_is_refused(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(200_000), $plan);

        $this->expectExceptionMessage('not enough');
        app(InvestmentService::class)->subscribe($user->fresh(), $plan, Money::fromMinor(500_000));
    }

    public function test_plan_bounds_are_enforced_server_side(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['min_amount' => 500_000]);

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(1_000_000), $plan);

        $this->expectExceptionMessage('minimum');
        app(InvestmentService::class)->subscribe($user->fresh(), $plan, Money::fromMinor(100_000));
    }

    // -----------------------------------------------------------------
    // Accrual
    // -----------------------------------------------------------------

    public function test_daily_accrual_credits_the_withdrawable_balance(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(500_000), $plan);
        $investment = app(InvestmentService::class)->subscribe($user->fresh(), $plan, Money::fromMinor(500_000));

        app(InvestmentService::class)->accrueDay($investment->fresh(), Carbon::today());

        $wallet = $user->fresh()->wallet;

        $this->assertSame(10_000, $wallet->withdrawable_balance->minor);
        $this->assertSame(1, $investment->fresh()->days_paid);
    }

    public function test_running_accrual_twice_in_one_day_does_not_double_pay(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(500_000), $plan);
        $investment = app(InvestmentService::class)->subscribe($user->fresh(), $plan, Money::fromMinor(500_000));

        $today = Carbon::today();

        $first = app(InvestmentService::class)->accrueDay($investment->fresh(), $today);
        $second = app(InvestmentService::class)->accrueDay($investment->fresh(), $today);

        $this->assertTrue($first, 'The first accrual should pay.');
        $this->assertFalse($second, 'The second accrual for the same day must be rejected.');

        // The guard that actually matters: the balance moved exactly once.
        $this->assertSame(10_000, $user->fresh()->wallet->withdrawable_balance->minor);
        $this->assertSame(1, $investment->fresh()->days_paid);
    }

    public function test_investment_completes_at_term_and_returns_capital(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['duration_days' => 3]);

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(500_000), $plan);
        $investment = app(InvestmentService::class)->subscribe($user->fresh(), $plan, Money::fromMinor(500_000));

        // Run the full term, one day at a time.
        for ($day = 0; $day < 3; $day++) {
            app(InvestmentService::class)->accrueDay(
                $investment->fresh(),
                Carbon::today()->addDays($day),
            );
        }

        $investment = $investment->fresh();
        $wallet = $user->fresh()->wallet;

        $this->assertSame('completed', $investment->status);
        $this->assertSame(3, $investment->days_paid);

        // ₦100/day x 3 days == ₦300 ROI, plus the ₦5,000 capital back.
        $this->assertSame(30_000, $investment->total_roi_paid->minor);
        $this->assertSame(530_000, $wallet->withdrawable_balance->minor);

        // Accrual after completion must not pay anything further.
        $this->assertFalse(
            app(InvestmentService::class)->accrueDay($investment, Carbon::today()->addDays(4))
        );
        $this->assertSame(530_000, $user->fresh()->wallet->withdrawable_balance->minor);
    }

    // -----------------------------------------------------------------
    // Referrals
    // -----------------------------------------------------------------

    public function test_three_tiers_of_referral_commission_are_paid_on_investment(): void
    {
        $tier3 = $this->makeUser();                 // top of the chain
        $tier2 = $this->makeUser($tier3);
        $tier1 = $this->makeUser($tier2);
        $investor = $this->makeUser($tier1);

        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($investor, Money::fromMinor(1_000_000), $plan);
        app(InvestmentService::class)->subscribe($investor->fresh(), $plan, Money::fromMinor(1_000_000));

        // Defaults: 20% / 2% / 1% of the ₦10,000 principal.
        $this->assertSame(200_000, $tier1->fresh()->wallet->withdrawable_balance->minor);
        $this->assertSame(20_000, $tier2->fresh()->wallet->withdrawable_balance->minor);
        $this->assertSame(10_000, $tier3->fresh()->wallet->withdrawable_balance->minor);
    }

    public function test_commission_is_not_paid_twice_for_the_same_investment(): void
    {
        $referrer = $this->makeUser();
        $investor = $this->makeUser($referrer);
        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($investor, Money::fromMinor(1_000_000), $plan);
        $investment = app(InvestmentService::class)->subscribe($investor->fresh(), $plan, Money::fromMinor(1_000_000));

        // Re-running the payout must be a no-op, guarded by the unique index.
        app(ReferralService::class)->payCommissionsFor($investment->fresh(['user', 'plan']));

        $this->assertSame(200_000, $referrer->fresh()->wallet->withdrawable_balance->minor);
    }

    public function test_a_referral_loop_does_not_hang_the_upline_walk(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser($a);

        // Force a cycle: a -> b -> a.
        $a->forceFill(['referred_by' => $b->id])->save();

        // Terminates rather than spinning, and never pays the same user twice.
        $chain = $b->fresh()->uplineChain(3);

        $this->assertLessThanOrEqual(2, count($chain));
        $this->assertSame(count($chain), count(array_unique(array_column($chain, 'id'))));
    }

    // -----------------------------------------------------------------
    // Withdrawals
    // -----------------------------------------------------------------

    private function openTheWindow(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('withdrawal_enabled', true);
        $settings->set('withdrawal_days', [1, 2, 3, 4, 5, 6, 7]);
        $settings->set('withdrawal_opens_at', '00:00');
        $settings->set('withdrawal_closes_at', '23:59');
        $settings->set('withdrawal_min', 10_000);
        $settings->set('withdrawal_max', 100_000_000);
    }

    public function test_a_withdrawal_request_holds_the_funds(): void
    {
        $this->openTheWindow();

        $user = $this->makeUser();
        app(WalletService::class)->creditRoi(
            $user,
            Money::fromMinor(500_000),
            $this->investmentFor($user),
            'Seed',
        );

        $account = $user->bankAccounts()->create([
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'Test Person',
            'is_primary' => true,
        ]);

        app(WithdrawalService::class)->request($user->fresh(), Money::fromMinor(200_000), $account);

        $wallet = $user->fresh()->wallet;

        // Moved out of withdrawable and into locked, not simply deducted.
        $this->assertSame(300_000, $wallet->withdrawable_balance->minor);
        $this->assertSame(200_000, $wallet->locked_balance->minor);
    }

    public function test_the_same_balance_cannot_be_requested_twice(): void
    {
        $this->openTheWindow();

        $user = $this->makeUser();
        app(WalletService::class)->creditRoi(
            $user,
            Money::fromMinor(100_000),
            $this->investmentFor($user),
            'Seed',
        );

        $account = $user->bankAccounts()->create([
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'Test Person',
            'is_primary' => true,
        ]);

        app(WithdrawalService::class)->request($user->fresh(), Money::fromMinor(100_000), $account);

        // The balance is now held, so a second request for it must fail.
        $this->expectExceptionMessage('not enough');
        app(WithdrawalService::class)->request($user->fresh(), Money::fromMinor(100_000), $account);
    }

    public function test_rejecting_a_withdrawal_returns_the_money(): void
    {
        $this->openTheWindow();

        $admin = $this->makeUser();
        $admin->forceFill(['is_admin' => true])->save();

        $user = $this->makeUser();
        app(WalletService::class)->creditRoi(
            $user,
            Money::fromMinor(500_000),
            $this->investmentFor($user),
            'Seed',
        );

        $account = $user->bankAccounts()->create([
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'Test Person',
            'is_primary' => true,
        ]);

        $withdrawal = app(WithdrawalService::class)
            ->request($user->fresh(), Money::fromMinor(200_000), $account);

        app(WithdrawalService::class)->reject($withdrawal, $admin, 'Details did not match');

        $wallet = $user->fresh()->wallet;

        $this->assertSame(500_000, $wallet->withdrawable_balance->minor, 'The full amount should return.');
        $this->assertSame(0, $wallet->locked_balance->minor);
        $this->assertSame('rejected', $withdrawal->fresh()->status);
    }

    public function test_paying_a_withdrawal_releases_the_held_funds(): void
    {
        $this->openTheWindow();

        $admin = $this->makeUser();
        $admin->forceFill(['is_admin' => true])->save();

        $user = $this->makeUser();
        app(WalletService::class)->creditRoi(
            $user,
            Money::fromMinor(500_000),
            $this->investmentFor($user),
            'Seed',
        );

        $account = $user->bankAccounts()->create([
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'Test Person',
            'is_primary' => true,
        ]);

        $withdrawal = app(WithdrawalService::class)
            ->request($user->fresh(), Money::fromMinor(200_000), $account);

        app(WithdrawalService::class)->markPaid($withdrawal, $admin, 'REF123');

        $wallet = $user->fresh()->wallet;

        $this->assertSame(300_000, $wallet->withdrawable_balance->minor);
        $this->assertSame(0, $wallet->locked_balance->minor, 'Held funds should be gone, not returned.');
        $this->assertSame(200_000, $wallet->total_withdrawn->minor);
        $this->assertSame('paid', $withdrawal->fresh()->status);
    }

    public function test_a_settled_withdrawal_cannot_be_paid_again(): void
    {
        $this->openTheWindow();

        $admin = $this->makeUser();
        $admin->forceFill(['is_admin' => true])->save();

        $user = $this->makeUser();
        app(WalletService::class)->creditRoi(
            $user,
            Money::fromMinor(500_000),
            $this->investmentFor($user),
            'Seed',
        );

        $account = $user->bankAccounts()->create([
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'Test Person',
            'is_primary' => true,
        ]);

        $withdrawal = app(WithdrawalService::class)
            ->request($user->fresh(), Money::fromMinor(200_000), $account);

        app(WithdrawalService::class)->markPaid($withdrawal, $admin);

        $this->expectExceptionMessage('already been settled');
        app(WithdrawalService::class)->markPaid($withdrawal->fresh(), $admin);
    }

    public function test_withdrawals_are_refused_when_the_window_is_closed(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('withdrawal_enabled', false);

        $user = $this->makeUser();
        app(WalletService::class)->creditRoi(
            $user,
            Money::fromMinor(500_000),
            $this->investmentFor($user),
            'Seed',
        );

        $account = $user->bankAccounts()->create([
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'Test Person',
            'is_primary' => true,
        ]);

        $this->expectExceptionMessage('temporarily disabled');
        app(WithdrawalService::class)->request($user->fresh(), Money::fromMinor(100_000), $account);
    }

    public function test_a_user_cannot_withdraw_to_someone_elses_account(): void
    {
        $this->openTheWindow();

        $user = $this->makeUser();
        $other = $this->makeUser();

        app(WalletService::class)->creditRoi(
            $user,
            Money::fromMinor(500_000),
            $this->investmentFor($user),
            'Seed',
        );

        $foreignAccount = $other->bankAccounts()->create([
            'bank_name' => 'Other Bank',
            'account_number' => '9876543210',
            'account_name' => 'Someone Else',
            'is_primary' => true,
        ]);

        $this->expectExceptionMessage('does not belong to you');
        app(WithdrawalService::class)->request($user->fresh(), Money::fromMinor(100_000), $foreignAccount);
    }

    /**
     * A minimal investment to hang a seeded ROI credit off, since the ledger
     * requires a related model.
     */
    private function investmentFor(User $user): \App\Models\Investment
    {
        $plan = $this->makePlan();

        return \App\Models\Investment::query()->create([
            'reference' => 'INV-'.strtoupper(uniqid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'principal' => 100_000,
            'daily_roi_bp' => 200,
            'duration_days' => 5,
            'return_capital' => true,
            'daily_payout' => 2_000,
            'total_expected_roi' => 10_000,
            'started_on' => Carbon::today(),
            'matures_on' => Carbon::today()->addDays(5),
        ]);
    }

    // -----------------------------------------------------------------
    // Ledger integrity
    // -----------------------------------------------------------------

    public function test_the_ledger_records_the_balance_either_side_of_every_write(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(300_000), $plan);
        app(WalletService::class)->creditDeposit($user, Money::fromMinor(200_000), $plan);

        $transactions = $user->fresh()->transactions()->orderBy('id')->get();

        $this->assertCount(2, $transactions);

        $this->assertSame(0, $transactions[0]->balance_before->minor);
        $this->assertSame(300_000, $transactions[0]->balance_after->minor);

        // The second write must start where the first one finished.
        $this->assertSame(300_000, $transactions[1]->balance_before->minor);
        $this->assertSame(500_000, $transactions[1]->balance_after->minor);
    }

    public function test_a_balance_can_never_be_driven_negative(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();

        app(WalletService::class)->creditDeposit($user, Money::fromMinor(100_000), $plan);

        $this->expectExceptionMessage('not enough');

        app(WalletService::class)->adminAdjust(
            user: $user->fresh(),
            amount: Money::fromMinor(200_000),
            bucket: 'deposit',
            direction: 'debit',
            description: 'Overdraw attempt',
        );

        $this->assertSame(100_000, $user->fresh()->wallet->deposit_balance->minor);
    }

    public function test_welcome_bonus_and_vip_trial_withdrawal_requirement(): void
    {
        $this->openTheWindow();

        // 1. Registration credits ₦2,500 welcome bonus to deposit balance.
        $this->post('/register', [
            'name' => 'Bonus User',
            'email' => 'bonus@example.test',
            'phone' => '+2348099887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'bonus@example.test')->firstOrFail();
        $this->assertSame(250_000, $user->wallet->deposit_balance->minor);

        // 2. Withdrawal without activating plan is blocked.
        $account = $user->bankAccounts()->create([
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'Bonus User',
            'is_primary' => true,
        ]);

        $this->expectExceptionMessage('You must activate the VIP Trial plan before you can make a withdrawal.');
        app(WithdrawalService::class)->request($user->fresh(), Money::fromMinor(250_000), $account);
    }
}
