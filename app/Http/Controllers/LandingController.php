<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\WithdrawalWindow;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(
        SettingsService $settings,
        ReferralService $referrals,
        WithdrawalWindow $window,
    ): View {
        return view('landing', [
            'plans' => Plan::query()->active()->ordered()->get(),
            'tiers' => $referrals->tierTable(),
            'depositMin' => $settings->money('deposit_min', 100_000),
            'depositMax' => $settings->money('deposit_max', 500_000_000),
            'withdrawalMin' => $settings->money('withdrawal_min', 100_000),
            'withdrawalWindow' => $window->summary(),
            'supportEmail' => $settings->string('support_email', 'support@zenvora.test'),
        ]);
    }
}
