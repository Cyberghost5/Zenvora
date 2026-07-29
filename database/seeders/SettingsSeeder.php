<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Writes the default platform rules into the settings table.
 *
 * Uses updateOrCreate keyed on `key`, so re-running the seeder on a live
 * database will not clobber values an administrator has since changed... with
 * the exception of labels and hints, which are presentation and always refreshed.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $existing = Setting::query()->where('key', $definition['key'])->first();

            if ($existing) {
                // Keep the admin's value, refresh only the copy around it.
                $existing->update([
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'label' => $definition['label'],
                    'hint' => $definition['hint'] ?? null,
                ]);

                continue;
            }

            Setting::query()->create($definition);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        $d = config('zenvora.defaults');

        return [
            // --- Deposits -------------------------------------------------
            [
                'key' => 'deposit_min',
                'value' => (string) $d['deposit_min'],
                'type' => 'integer',
                'group' => 'deposits',
                'label' => 'Minimum deposit',
                'hint' => 'The smallest amount a user may fund at once.',
            ],
            [
                'key' => 'deposit_max',
                'value' => (string) $d['deposit_max'],
                'type' => 'integer',
                'group' => 'deposits',
                'label' => 'Maximum deposit',
                'hint' => 'The largest amount a user may fund in a single transaction.',
            ],
            [
                'key' => 'deposit_channels',
                'value' => json_encode($d['deposit_channels']),
                'type' => 'json',
                'group' => 'deposits',
                'label' => 'Enabled funding methods',
                'hint' => 'Gateways only appear to users once their API keys are set in .env.',
            ],
            [
                'key' => 'welcome_bonus',
                'value' => (string) $d['welcome_bonus'],
                'type' => 'integer',
                'group' => 'deposits',
                'label' => 'Welcome Bonus',
                'hint' => 'Bonus credited to deposit balance upon registration.',
            ],

            // --- Withdrawals ----------------------------------------------
            [
                'key' => 'withdrawal_enabled',
                'value' => $d['withdrawal_enabled'] ? '1' : '0',
                'type' => 'boolean',
                'group' => 'withdrawals',
                'label' => 'Withdrawals enabled',
                'hint' => 'Turning this off closes withdrawals regardless of the window.',
            ],
            [
                'key' => 'withdrawal_min',
                'value' => (string) $d['withdrawal_min'],
                'type' => 'integer',
                'group' => 'withdrawals',
                'label' => 'Minimum withdrawal',
            ],
            [
                'key' => 'withdrawal_max',
                'value' => (string) $d['withdrawal_max'],
                'type' => 'integer',
                'group' => 'withdrawals',
                'label' => 'Maximum withdrawal',
            ],
            [
                'key' => 'withdrawal_fee_bp',
                'value' => (string) $d['withdrawal_fee_bp'],
                'type' => 'integer',
                'group' => 'withdrawals',
                'label' => 'Withdrawal fee',
                'hint' => 'Deducted from the amount requested. Stored in basis points.',
            ],
            [
                'key' => 'withdrawal_days',
                'value' => json_encode($d['withdrawal_days']),
                'type' => 'json',
                'group' => 'withdrawals',
                'label' => 'Withdrawal days',
                'hint' => 'Weekdays on which a request may be submitted.',
            ],
            [
                'key' => 'withdrawal_opens_at',
                'value' => $d['withdrawal_opens_at'],
                'type' => 'string',
                'group' => 'withdrawals',
                'label' => 'Window opens at',
            ],
            [
                'key' => 'withdrawal_closes_at',
                'value' => $d['withdrawal_closes_at'],
                'type' => 'string',
                'group' => 'withdrawals',
                'label' => 'Window closes at',
            ],
            [
                'key' => 'withdrawal_timezone',
                'value' => $d['withdrawal_timezone'],
                'type' => 'string',
                'group' => 'withdrawals',
                'label' => 'Window timezone',
                'hint' => 'The window is evaluated in this timezone, not the server\'s.',
            ],

            // --- Referrals ------------------------------------------------
            [
                'key' => 'referral_enabled',
                'value' => $d['referral_enabled'] ? '1' : '0',
                'type' => 'boolean',
                'group' => 'referrals',
                'label' => 'Referral commissions enabled',
            ],
            [
                'key' => 'referral_tier_1_bp',
                'value' => (string) $d['referral_tier_1_bp'],
                'type' => 'integer',
                'group' => 'referrals',
                'label' => 'Tier 1 commission',
                'hint' => 'Paid to the direct referrer when their referral invests.',
            ],
            [
                'key' => 'referral_tier_2_bp',
                'value' => (string) $d['referral_tier_2_bp'],
                'type' => 'integer',
                'group' => 'referrals',
                'label' => 'Tier 2 commission',
            ],
            [
                'key' => 'referral_tier_3_bp',
                'value' => (string) $d['referral_tier_3_bp'],
                'type' => 'integer',
                'group' => 'referrals',
                'label' => 'Tier 3 commission',
            ],

            // --- Manual bank transfer -------------------------------------
            [
                'key' => 'manual_bank_name',
                'value' => $d['manual_bank_name'],
                'type' => 'string',
                'group' => 'manual',
                'label' => 'Bank name',
                'hint' => 'Shown to users who choose to pay by bank transfer.',
            ],
            [
                'key' => 'manual_account_number',
                'value' => $d['manual_account_number'],
                'type' => 'string',
                'group' => 'manual',
                'label' => 'Account number',
                'hint' => 'Manual transfers stay hidden from users until this is filled in.',
            ],
            [
                'key' => 'manual_account_name',
                'value' => $d['manual_account_name'],
                'type' => 'string',
                'group' => 'manual',
                'label' => 'Account name',
            ],
            [
                'key' => 'manual_instructions',
                'value' => $d['manual_instructions'],
                'type' => 'string',
                'group' => 'manual',
                'label' => 'Transfer instructions',
            ],

            // --- Site -----------------------------------------------------
            [
                'key' => 'site_name',
                'value' => $d['site_name'],
                'type' => 'string',
                'group' => 'general',
                'label' => 'Site name',
            ],
            [
                'key' => 'support_email',
                'value' => $d['support_email'],
                'type' => 'string',
                'group' => 'general',
                'label' => 'Support email',
            ],
            [
                'key' => 'support_phone',
                'value' => $d['support_phone'],
                'type' => 'string',
                'group' => 'general',
                'label' => 'Support phone',
            ],
        ];
    }
}
