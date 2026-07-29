<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use App\Services\WithdrawalWindow;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The admin's control panel for platform-wide rules: deposit and withdrawal
 * limits, the withdrawal window, referral rates and the manual bank details.
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(WithdrawalWindow $window): View
    {
        return view('admin.settings', [
            'settings' => $this->settings,
            'window' => $window,
            'dayNames' => WithdrawalWindow::dayNames(),
            'timezones' => [
                'Africa/Lagos', 'Africa/Accra', 'Africa/Nairobi', 'Africa/Johannesburg',
                'Europe/London', 'America/New_York', 'UTC',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Deposits
            'deposit_min' => ['required', 'numeric', 'min:0.01'],
            'deposit_max' => ['required', 'numeric', 'gte:deposit_min'],
            'deposit_channels' => ['nullable', 'array'],
            'deposit_channels.*' => ['in:paystack,flutterwave,coupon,manual'],
            'welcome_bonus' => ['required', 'numeric', 'min:0'],

            // Withdrawals
            'withdrawal_enabled' => ['nullable', 'boolean'],
            'withdrawal_require_investment' => ['nullable', 'boolean'],
            'withdrawal_min' => ['required', 'numeric', 'min:0.01'],
            'withdrawal_max' => ['required', 'numeric', 'gte:withdrawal_min'],
            'withdrawal_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'withdrawal_days' => ['nullable', 'array'],
            'withdrawal_days.*' => ['integer', 'between:1,7'],
            'withdrawal_opens_at' => ['required', 'date_format:H:i'],
            'withdrawal_closes_at' => ['required', 'date_format:H:i'],
            'withdrawal_timezone' => ['required', 'timezone'],

            // Referrals
            'referral_enabled' => ['nullable', 'boolean'],
            'referral_tier_1_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_tier_2_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_tier_3_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            // Manual transfer destination
            'manual_bank_name' => ['nullable', 'string', 'max:120'],
            'manual_account_number' => ['nullable', 'string', 'max:32'],
            'manual_account_name' => ['nullable', 'string', 'max:120'],
            'manual_instructions' => ['nullable', 'string', 'max:1000'],

            // Announcement Notice Popup
            'announcement_enabled' => ['nullable', 'boolean'],
            'announcement_title' => ['nullable', 'string', 'max:120'],
            'announcement_body' => ['nullable', 'string', 'max:2000'],

            // Site
            'site_name' => ['required', 'string', 'max:60'],
            'support_email' => ['required', 'email', 'max:120'],
            'support_phone' => ['nullable', 'string', 'max:32'],
        ], [
            'deposit_max.gte' => 'The maximum deposit must be at least the minimum.',
            'withdrawal_max.gte' => 'The maximum withdrawal must be at least the minimum.',
        ]);

        // Enabling manual transfers without an account number would show
        // depositors a blank destination, so require the pair together.
        $channels = $validated['deposit_channels'] ?? [];

        if (in_array('manual', $channels, true) && blank($validated['manual_account_number'] ?? null)) {
            return back()->withInput()->withErrors([
                'manual_account_number' => 'Add the account number depositors should pay into, or turn off manual transfers.',
            ]);
        }

        if ($channels === []) {
            return back()->withInput()->withErrors([
                'deposit_channels' => 'Leave at least one funding method enabled, or users cannot deposit at all.',
            ]);
        }

        $days = array_map('intval', $validated['withdrawal_days'] ?? []);

        $before = $this->auditSnapshot();

        $this->settings->setMany([
            'deposit_min' => Money::fromMajor($validated['deposit_min'])->minor,
            'deposit_max' => Money::fromMajor($validated['deposit_max'])->minor,
            'deposit_channels' => $channels,
            'welcome_bonus' => Money::fromMajor($validated['welcome_bonus'])->minor,

            'withdrawal_enabled' => $request->boolean('withdrawal_enabled'),
            'withdrawal_require_investment' => $request->boolean('withdrawal_require_investment'),
            'withdrawal_min' => Money::fromMajor($validated['withdrawal_min'])->minor,
            'withdrawal_max' => Money::fromMajor($validated['withdrawal_max'])->minor,
            'withdrawal_fee_bp' => (int) round($validated['withdrawal_fee_percent'] * 100),
            'withdrawal_days' => $days,
            'withdrawal_opens_at' => $validated['withdrawal_opens_at'],
            'withdrawal_closes_at' => $validated['withdrawal_closes_at'],
            'withdrawal_timezone' => $validated['withdrawal_timezone'],

            'referral_enabled' => $request->boolean('referral_enabled'),
            'referral_tier_1_bp' => (int) round($validated['referral_tier_1_percent'] * 100),
            'referral_tier_2_bp' => (int) round($validated['referral_tier_2_percent'] * 100),
            'referral_tier_3_bp' => (int) round($validated['referral_tier_3_percent'] * 100),

            'manual_bank_name' => $validated['manual_bank_name'] ?? '',
            'manual_account_number' => $validated['manual_account_number'] ?? '',
            'manual_account_name' => $validated['manual_account_name'] ?? '',
            'manual_instructions' => $validated['manual_instructions'] ?? '',

            'announcement_enabled' => $request->boolean('announcement_enabled'),
            'announcement_title' => $validated['announcement_title'] ?? 'Welcome to Zenvora',
            'announcement_body' => $validated['announcement_body'] ?? '',

            'site_name' => $validated['site_name'],
            'support_email' => $validated['support_email'],
            'support_phone' => $validated['support_phone'] ?? '',
        ]);

        $this->audit->logSettingsChange($before, $this->auditSnapshot());

        $warning = $days === []
            ? ' Note: no withdrawal days are selected, so withdrawals are effectively closed.'
            : '';

        return back()->with('status', 'Settings saved.'.$warning);
    }

    /**
     * Flatten the settings that matter for the audit trail.
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(): array
    {
        $this->settings->flush();

        $keys = [
            'deposit_min', 'deposit_max', 'deposit_channels', 'welcome_bonus',
            'withdrawal_enabled', 'withdrawal_require_investment', 'withdrawal_min', 'withdrawal_max', 'withdrawal_fee_bp',
            'withdrawal_days', 'withdrawal_opens_at', 'withdrawal_closes_at', 'withdrawal_timezone',
            'referral_enabled', 'referral_tier_1_bp', 'referral_tier_2_bp', 'referral_tier_3_bp',
            'manual_bank_name', 'manual_account_number', 'manual_account_name',
            'announcement_enabled', 'announcement_title', 'announcement_body',
            'site_name', 'support_email', 'support_phone',
        ];

        $snapshot = [];

        foreach ($keys as $key) {
            $value = $this->settings->get($key);
            $snapshot[$key] = is_array($value) ? implode(',', $value) : $value;
        }

        return $snapshot;
    }
}
