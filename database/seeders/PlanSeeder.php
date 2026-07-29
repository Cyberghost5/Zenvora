<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Sample plans so the landing page and dashboard have something to render.
 *
 * The rates here are illustrative starting points, not a recommendation --
 * set your own in the admin panel before going live.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'VIP Trial',
                'slug' => 'vip-trial',
                'tagline' => 'Activate trial with welcome bonus',
                'description' => 'Special trial cycle for new users. Earn ₦100 daily for 7 days.',
                'min_amount' => 250_000,          // ₦2,500
                'max_amount' => 250_000,
                'fixed_daily_payout' => 10_000,   // ₦100
                'daily_roi_bp' => 400,            // 4%
                'duration_days' => 7,
                'sort_order' => 1,
            ],
            [
                'name' => 'Vip 1',
                'slug' => 'vip-1',
                'tagline' => 'Earn ₦1,000 daily for 7 days',
                'description' => 'Invest ₦3,600 and receive ₦1,000 daily income over a 7-day cycle.',
                'min_amount' => 360_000,          // ₦3,600
                'max_amount' => 360_000,
                'fixed_daily_payout' => 100_000,  // ₦1,000
                'daily_roi_bp' => 2778,
                'duration_days' => 7,
                'sort_order' => 2,
            ],
            [
                'name' => 'Vip 2',
                'slug' => 'vip-2',
                'tagline' => 'Earn ₦1,500 daily for 7 days',
                'description' => 'Invest ₦5,500 and receive ₦1,500 daily income over a 7-day cycle.',
                'min_amount' => 550_000,          // ₦5,500
                'max_amount' => 550_000,
                'fixed_daily_payout' => 150_000,  // ₦1,500
                'daily_roi_bp' => 2727,
                'duration_days' => 7,
                'sort_order' => 3,
            ],
            [
                'name' => 'Vip 3',
                'slug' => 'vip-3',
                'tagline' => 'Earn ₦3,200 daily for 7 days',
                'description' => 'Invest ₦12,000 and receive ₦3,200 daily income over a 7-day cycle.',
                'min_amount' => 1_200_000,        // ₦12,000
                'max_amount' => 1_200_000,
                'fixed_daily_payout' => 320_000,  // ₦3,200
                'daily_roi_bp' => 2667,
                'duration_days' => 7,
                'sort_order' => 4,
            ],
            [
                'name' => 'Vip 4',
                'slug' => 'vip-4',
                'tagline' => 'Earn ₦8,200 daily for 7 days',
                'description' => 'Invest ₦30,000 and receive ₦8,200 daily income over a 7-day cycle.',
                'min_amount' => 3_000_000,        // ₦30,000
                'max_amount' => 3_000_000,
                'fixed_daily_payout' => 820_000,  // ₦8,200
                'daily_roi_bp' => 2733,
                'duration_days' => 7,
                'sort_order' => 5,
            ],
            [
                'name' => 'Vip 5',
                'slug' => 'vip-5',
                'tagline' => 'Earn ₦18,000 daily for 7 days',
                'description' => 'Invest ₦60,000 and receive ₦18,000 daily income over a 7-day cycle.',
                'min_amount' => 6_000_000,        // ₦60,000
                'max_amount' => 6_000_000,
                'fixed_daily_payout' => 1_800_000, // ₦18,000
                'daily_roi_bp' => 3000,
                'duration_days' => 7,
                'sort_order' => 6,
            ],
            [
                'name' => 'Vip 6',
                'slug' => 'vip-6',
                'tagline' => 'Earn ₦30,000 daily for 7 days',
                'description' => 'Invest ₦100,000 and receive ₦30,000 daily income over a 7-day cycle.',
                'min_amount' => 10_000_000,       // ₦100,000
                'max_amount' => 10_000_000,
                'fixed_daily_payout' => 3_000_000, // ₦30,000
                'daily_roi_bp' => 3000,
                'duration_days' => 7,
                'sort_order' => 7,
            ],
            [
                'name' => 'Vip 7',
                'slug' => 'vip-7',
                'tagline' => 'Earn ₦40,000 daily for 7 days',
                'description' => 'Invest ₦150,000 and receive ₦40,000 daily income over a 7-day cycle.',
                'min_amount' => 15_000_000,       // ₦150,000
                'max_amount' => 15_000_000,
                'fixed_daily_payout' => 4_000_000, // ₦40,000
                'daily_roi_bp' => 2667,
                'duration_days' => 7,
                'sort_order' => 8,
            ],
            [
                'name' => 'Vip 8',
                'slug' => 'vip-8',
                'tagline' => 'Earn ₦65,000 daily for 7 days',
                'description' => 'Invest ₦250,000 and receive ₦65,000 daily income over a 7-day cycle.',
                'min_amount' => 25_000_000,       // ₦250,000
                'max_amount' => 25_000_000,
                'fixed_daily_payout' => 6_500_000, // ₦65,000
                'daily_roi_bp' => 2600,
                'duration_days' => 7,
                'sort_order' => 9,
            ],
            [
                'name' => 'Vip 9',
                'slug' => 'vip-9',
                'tagline' => 'Earn ₦160,000 daily for 7 days',
                'description' => 'Invest ₦500,000 and receive ₦160,000 daily income over a 7-day cycle.',
                'min_amount' => 50_000_000,       // ₦500,000
                'max_amount' => 50_000_000,
                'fixed_daily_payout' => 16_000_000, // ₦160,000
                'daily_roi_bp' => 3200,
                'duration_days' => 7,
                'sort_order' => 10,
            ],
            [
                'name' => 'Vip 10',
                'slug' => 'vip-10',
                'tagline' => 'Earn ₦260,000 daily for 7 days',
                'description' => 'Invest ₦1,000,000 and receive ₦260,000 daily income over a 7-day cycle.',
                'min_amount' => 100_000_000,      // ₦1,000,000
                'max_amount' => 100_000_000,
                'fixed_daily_payout' => 26_000_000, // ₦260,000
                'daily_roi_bp' => 2600,
                'duration_days' => 7,
                'sort_order' => 11,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    ...$plan,
                    'return_capital' => true,
                    'referral_eligible' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
