<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Deposit;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Every page renders, for each audience, with real records present.
 *
 * A Blade typo does not fail a unit test, so this walks the whole surface with
 * populated data -- an empty database would skip the branches that touch
 * relationships and money formatting.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $admin;
    private Plan $plan;
    private Investment $investment;
    private Deposit $deposit;
    private Withdrawal $withdrawal;
    private Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->admin = $this->makeUser('admin@test.test', isAdmin: true);
        $this->member = $this->makeUser('member@test.test');

        // A referral relationship, so the referral views have something to show.
        $downline = $this->makeUser('downline@test.test');
        $downline->forceFill(['referred_by' => $this->member->id])->save();

        $this->plan = Plan::query()->create([
            'name' => 'Render Plan',
            'slug' => 'render-plan',
            'tagline' => 'For the render test',
            'description' => 'A plan used by the page render test.',
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'daily_roi_bp' => 200,
            'duration_days' => 5,
            'return_capital' => true,
            'referral_eligible' => true,
            'is_active' => true,
        ]);

        $this->member->bankAccounts()->create([
            'bank_name' => 'Render Bank',
            'account_number' => '0123456789',
            'account_name' => 'Member Person',
            'is_primary' => true,
        ]);

        // Fund, invest and accrue a day so the ledger and payout tables populate.
        app(\App\Services\WalletService::class)->creditDeposit(
            $this->member,
            \App\Support\Money::fromMinor(5_000_000),
            $this->plan,
        );

        $this->investment = app(\App\Services\InvestmentService::class)->subscribe(
            $this->member->fresh(),
            $this->plan,
            \App\Support\Money::fromMinor(1_000_000),
        );

        app(\App\Services\InvestmentService::class)->accrueDay($this->investment->fresh(), Carbon::today());

        $this->deposit = Deposit::query()->create([
            'reference' => 'DEP-RENDER01',
            'user_id' => $this->member->id,
            'channel' => 'manual',
            'amount' => 500_000,
            'status' => 'awaiting_review',
            'depositor_name' => 'Member Person',
            'paid_on' => Carbon::today(),
        ]);

        $this->withdrawal = Withdrawal::query()->create([
            'reference' => 'WDR-RENDER01',
            'user_id' => $this->member->id,
            'amount' => 10_000,
            'fee' => 0,
            'net_amount' => 10_000,
            'status' => 'pending',
            'bank_name' => 'Render Bank',
            'account_number' => '0123456789',
            'account_name' => 'Member Person',
        ]);

        $this->coupon = Coupon::query()->create([
            'code' => 'ZVC-RENDER01',
            'amount' => 100_000,
            'max_uses' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    private function makeUser(string $email, bool $isAdmin = false): User
    {
        $user = User::query()->create([
            'name' => 'Test Person',
            'email' => $email,
            'phone' => '+234'.random_int(1_000_000_000, 9_999_999_999),
            'password' => 'password123',
            'referral_code' => User::generateReferralCode(),
        ]);

        // is_admin is not fillable by design, so it cannot be passed to create().
        if ($isAdmin) {
            $user->grantAdmin();
        }

        $user->wallet()->create();

        return $user->fresh();
    }

    // -----------------------------------------------------------------
    // Public
    // -----------------------------------------------------------------

    public function test_public_pages_render(): void
    {
        $paths = ['/', '/login', '/register', '/forgot-password', '/terms', '/privacy'];

        foreach ($paths as $path) {
            $this->get($path)->assertOk("Expected {$path} to render for a guest.");
        }
    }

    public function test_reset_password_page_renders(): void
    {
        $this->get('/reset-password/some-token?email=member@test.test')->assertOk();
    }

    public function test_register_prefills_a_valid_referral_code(): void
    {
        $this->get('/register?ref='.$this->member->referral_code)
            ->assertOk()
            ->assertSee($this->member->referral_code);
    }

    public function test_register_warns_about_an_unknown_referral_code(): void
    {
        // A bad code must not silently vanish -- the user is told.
        $this->get('/register?ref=ZVNOTREAL')
            ->assertOk()
            ->assertSee('could not find the referral code', escape: false);
    }

    // -----------------------------------------------------------------
    // User module
    // -----------------------------------------------------------------

    public function userPages(): array
    {
        return [
            '/dashboard',
            '/investments',
            '/deposits',
            '/deposits/fund',
            '/withdrawals',
            '/referrals',
            '/transactions',
            '/profile',
        ];
    }

    public function test_user_pages_render(): void
    {
        $this->actingAs($this->member);

        foreach ($this->userPages() as $path) {
            $this->get($path)->assertOk("Expected {$path} to render for a member.");
        }
    }

    public function test_user_detail_pages_render(): void
    {
        $this->actingAs($this->member);

        $this->get('/investments/'.$this->investment->id)->assertOk();
        $this->get('/deposits/'.$this->deposit->id)->assertOk();
    }

    public function test_transaction_filters_render(): void
    {
        $this->actingAs($this->member);

        foreach (['all', 'deposit', 'roi', 'referral_commission', 'withdrawal'] as $type) {
            $this->get('/transactions?type='.$type)->assertOk();
        }
    }

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guests_are_redirected_away_from_the_user_module(): void
    {
        foreach ($this->userPages() as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_the_admin_panel_is_hidden_from_ordinary_users(): void
    {
        $this->actingAs($this->member);

        // A 404 rather than 403: no need to confirm the panel exists.
        $this->get('/admin')->assertNotFound();
        $this->get('/admin/settings')->assertNotFound();
        $this->get('/admin/users')->assertNotFound();
        $this->get('/admin/withdrawals')->assertNotFound();
    }

    public function test_a_user_cannot_open_another_users_records(): void
    {
        $stranger = $this->makeUser('stranger@test.test');

        $this->actingAs($stranger);

        $this->get('/investments/'.$this->investment->id)->assertNotFound();
        $this->get('/deposits/'.$this->deposit->id)->assertNotFound();
    }

    public function test_a_suspended_user_is_signed_out_on_their_next_request(): void
    {
        $this->actingAs($this->member);
        $this->get('/dashboard')->assertOk();

        $this->member->forceFill([
            'is_blocked' => true,
            'blocked_reason' => 'Testing',
        ])->save();

        // Suspension takes effect immediately, not at the next login attempt.
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    // -----------------------------------------------------------------
    // Admin module
    // -----------------------------------------------------------------

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin);

        $paths = [
            '/admin',
            '/admin/settings',
            '/admin/plans',
            '/admin/plans/create',
            '/admin/plans/'.$this->plan->id.'/edit',
            '/admin/deposits',
            '/admin/deposits?status=all',
            '/admin/deposits/'.$this->deposit->id,
            '/admin/withdrawals',
            '/admin/withdrawals?status=all',
            '/admin/withdrawals/'.$this->withdrawal->id,
            '/admin/investments',
            '/admin/investments?status=all',
            '/admin/investments/'.$this->investment->id,
            '/admin/users',
            '/admin/users?filter=investors',
            '/admin/users?filter=blocked',
            '/admin/users?filter=admins',
            '/admin/users/'.$this->member->id,
            '/admin/coupons',
            '/admin/audit',
        ];

        foreach ($paths as $path) {
            $this->get($path)->assertOk("Expected {$path} to render for an admin.");
        }
    }

    public function test_admin_search_queries_render(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/deposits?status=all&q=DEP-RENDER')->assertOk();
        $this->get('/admin/withdrawals?status=all&q=Member')->assertOk();
        $this->get('/admin/users?q=member@test.test')->assertOk();
        $this->get('/admin/investments?status=all&q=INV')->assertOk();
        $this->get('/admin/audit?q=anything')->assertOk();
    }

    // -----------------------------------------------------------------
    // Key form submissions
    // -----------------------------------------------------------------

    public function test_registration_captures_the_referrer(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Person',
            'email' => 'new@test.test',
            'phone' => '+2348011122233',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $this->member->referral_code,
            'terms' => '1',
        ]);

        $response->assertRedirect('/dashboard');

        $created = User::query()->where('email', 'new@test.test')->first();

        $this->assertNotNull($created);
        $this->assertSame($this->member->id, $created->referred_by);

        // A wallet must exist from the moment the account does.
        $this->assertNotNull($created->wallet);
    }

    public function test_registration_rejects_an_unknown_referral_code(): void
    {
        $this->post('/register', [
            'name' => 'New Person',
            'email' => 'new2@test.test',
            'phone' => '+2348011122244',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => 'ZVFAKE99',
            'terms' => '1',
        ])->assertSessionHasErrors('referral_code');
    }

    public function test_admin_can_save_settings(): void
    {
        $this->actingAs($this->admin);

        $this->put('/admin/settings', [
            'deposit_min' => '2000',
            'deposit_max' => '1000000',
            'deposit_channels' => ['coupon'],
            'withdrawal_enabled' => '1',
            'withdrawal_min' => '1500',
            'withdrawal_max' => '250000',
            'withdrawal_fee_percent' => '1.5',
            'withdrawal_days' => ['1', '3', '5'],
            'withdrawal_opens_at' => '10:00',
            'withdrawal_closes_at' => '16:00',
            'withdrawal_timezone' => 'Africa/Lagos',
            'referral_enabled' => '1',
            'referral_tier_1_percent' => '8',
            'referral_tier_2_percent' => '4',
            'referral_tier_3_percent' => '1',
            'site_name' => 'Zenvora',
            'support_email' => 'help@test.test',
        ])->assertSessionHasNoErrors();

        $settings = app(\App\Services\SettingsService::class);
        $settings->flush();

        // Naira in, kobo stored.
        $this->assertSame(200_000, $settings->integer('deposit_min'));
        $this->assertSame(150_000, $settings->integer('withdrawal_min'));

        // Percent in, basis points stored.
        $this->assertSame(150, $settings->integer('withdrawal_fee_bp'));
        $this->assertSame(800, $settings->integer('referral_tier_1_bp'));

        $this->assertSame([1, 3, 5], $settings->array('withdrawal_days'));

        // The change is on the record.
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'settings.updated']);
    }

    public function test_settings_refuse_to_disable_every_funding_method(): void
    {
        $this->actingAs($this->admin);

        $this->put('/admin/settings', [
            'deposit_min' => '2000',
            'deposit_max' => '1000000',
            // deposit_channels omitted entirely
            'withdrawal_min' => '1500',
            'withdrawal_max' => '250000',
            'withdrawal_fee_percent' => '0',
            'withdrawal_opens_at' => '10:00',
            'withdrawal_closes_at' => '16:00',
            'withdrawal_timezone' => 'Africa/Lagos',
            'referral_tier_1_percent' => '8',
            'referral_tier_2_percent' => '4',
            'referral_tier_3_percent' => '1',
            'site_name' => 'Zenvora',
            'support_email' => 'help@test.test',
        ])->assertSessionHasErrors('deposit_channels');
    }

    public function test_admin_can_create_a_plan(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/plans', [
            'name' => 'Created Plan',
            'min_amount' => '1000',
            'max_amount' => '50000',
            'daily_roi_percent' => '1.75',
            'duration_days' => '14',
            'return_capital' => '1',
            'referral_eligible' => '1',
            'is_active' => '1',
        ])->assertRedirect('/admin/plans');

        $plan = Plan::query()->where('name', 'Created Plan')->first();

        $this->assertNotNull($plan);
        $this->assertSame(175, $plan->daily_roi_bp);   // 1.75% -> 175bp
        $this->assertSame(100_000, $plan->min_amount->minor);
        $this->assertSame('created-plan', $plan->slug);
    }

    public function test_a_plan_with_history_is_deactivated_rather_than_deleted(): void
    {
        $this->actingAs($this->admin);

        $this->delete('/admin/plans/'.$this->plan->id);

        // The investment still needs a parent record it can resolve.
        $this->assertDatabaseHas('plans', ['id' => $this->plan->id, 'is_active' => false]);
    }

    public function test_admin_approving_a_deposit_credits_the_wallet(): void
    {
        $this->actingAs($this->admin);

        $before = $this->member->fresh()->wallet->deposit_balance->minor;

        $this->post('/admin/deposits/'.$this->deposit->id.'/approve', ['amount' => '5000'])
            ->assertRedirect('/admin/deposits');

        $this->assertSame(
            $before + 500_000,
            $this->member->fresh()->wallet->deposit_balance->minor,
        );

        $this->assertSame('successful', $this->deposit->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'deposit.approved']);
    }

    public function test_an_already_credited_deposit_cannot_be_approved_twice(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/deposits/'.$this->deposit->id.'/approve', ['amount' => '5000']);
        $after = $this->member->fresh()->wallet->deposit_balance->minor;

        $this->post('/admin/deposits/'.$this->deposit->id.'/approve', ['amount' => '5000'])
            ->assertSessionHasErrors();

        // Crucially, the balance did not move a second time.
        $this->assertSame($after, $this->member->fresh()->wallet->deposit_balance->minor);
    }

    public function test_admin_cannot_suspend_their_own_account(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/users/'.$this->admin->id.'/block', ['reason' => 'Oops'])
            ->assertSessionHasErrors();

        $this->assertFalse($this->admin->fresh()->is_blocked);
    }

    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin);

        $other = $this->makeUser('secondadmin@test.test', isAdmin: true);

        // Two admins: demoting one is allowed.
        $this->post('/admin/users/'.$other->id.'/toggle-admin')->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_admin);

        // The remaining admin cannot demote themselves either way.
        $this->post('/admin/users/'.$this->admin->id.'/toggle-admin')->assertSessionHasErrors();
        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_webhooks_reject_an_unsigned_request(): void
    {
        // No signature header, so the wallet must not be touched.
        $this->postJson('/webhooks/paystack', ['event' => 'charge.success'])
            ->assertStatus(401);

        $this->postJson('/webhooks/flutterwave', ['data' => ['tx_ref' => 'x']])
            ->assertStatus(401);
    }
}
