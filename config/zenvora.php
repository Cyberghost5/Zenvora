<?php

return [
    'currency' => env('ZENVORA_CURRENCY', 'NGN'),
    'currency_symbol' => env('ZENVORA_CURRENCY_SYMBOL', '₦'),

    /*
     |----------------------------------------------------------------------
     | Setting defaults
     |----------------------------------------------------------------------
     | Fallbacks used when a row is absent from the `settings` table. The
     | admin UI writes to that table; these keep the app coherent before the
     | first save and after a fresh install.
     |
     | All money values are in minor units (kobo). All rates are in basis
     | points (100bp == 1%).
     */
    'defaults' => [
        // Deposits
        'deposit_min' => 360_000,          // ₦3,600.00
        'deposit_max' => 500_000_000,      // ₦5,000,000.00
        'deposit_channels' => ['paystack', 'flutterwave', 'coupon', 'manual'],
        'welcome_bonus' => 250_000,        // ₦2,500.00 welcome bonus

        // Withdrawals
        'withdrawal_min' => 250_000,       // ₦2,500.00
        'withdrawal_max' => 100_000_000,   // ₦1,000,000.00
        'withdrawal_fee_bp' => 1_000,      // 10%
        'withdrawal_enabled' => true,

        // Withdrawal window. ISO-8601 weekdays: 1 = Monday .. 7 = Sunday.
        'withdrawal_days' => [1, 2, 3, 4, 5, 6, 7], // Everyday
        'withdrawal_opens_at' => '10:00',
        'withdrawal_closes_at' => '17:00',
        'withdrawal_timezone' => 'Africa/Lagos',

        // Referral commission per tier, in basis points.
        'referral_enabled' => true,
        'referral_tier_1_bp' => 2_000,     // 20%
        'referral_tier_2_bp' => 200,       // 2%
        'referral_tier_3_bp' => 100,       // 1%

        // Manual bank transfer destination shown to depositors.
        'manual_bank_name' => '',
        'manual_account_number' => '',
        'manual_account_name' => '',
        'manual_bank_name_2' => '',
        'manual_account_number_2' => '',
        'manual_account_name_2' => '',
        'manual_instructions' => 'Transfer the exact amount to the account above, then upload your payment receipt. Your wallet is credited once an administrator confirms the transfer.',

        // Announcement Notice Popup
        'announcement_enabled' => false,
        'announcement_title' => 'Important Notice',
        'announcement_body' => 'Welcome back to Zenvora! Check out our latest investment plans and returns.',

        // Site chrome
        'site_name' => 'Zenvora',
        'support_email' => 'support@zenvora.test',
        'support_phone' => '',
    ],
];
