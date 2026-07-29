<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\ReferralService;
use App\Services\WithdrawalService;
use App\Services\WithdrawalWindow;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ReferralService $referrals,
        WithdrawalWindow $window,
        WithdrawalService $withdrawals,
    ): View {
        $user = $request->user();
        $wallet = $user->ensureWallet();

        return view('user.dashboard', [
            'wallet' => $wallet,

            'plans' => Plan::query()->active()->ordered()->get(),

            'activeInvestments' => $user->investments()
                ->active()
                ->with('plan')
                ->latest()
                ->get(),

            'recentTransactions' => $user->transactions()
                ->latest()
                ->limit(8)
                ->get(),

            'pendingDeposits' => $user->deposits()
                ->whereIn('status', ['pending', 'awaiting_review'])
                ->count(),

            'pendingWithdrawals' => $user->withdrawals()->pending()->count(),

            'referralCounts' => $referrals->downlineCounts($user),
            'referralTiers' => $referrals->tierTable(),

            'window' => $window,
            'withdrawalMin' => $withdrawals->minimum(),
            'withdrawalMax' => $withdrawals->maximum(),
        ]);
    }
}
