<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Deposit;
use App\Models\Investment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WithdrawalWindow;
use App\Support\Money;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(WithdrawalWindow $window): View
    {
        return view('admin.dashboard', [
            'userCount' => User::query()->where('is_admin', false)->count(),
            'blockedCount' => User::query()->where('is_blocked', true)->count(),

            // Sums are read straight from the wallets table as raw minor units
            // and wrapped, rather than loading every row into memory.
            'totalDeposited' => Money::fromMinor((int) Wallet::query()->sum('total_deposited')),
            'totalWithdrawn' => Money::fromMinor((int) Wallet::query()->sum('total_withdrawn')),
            'totalInvested' => Money::fromMinor((int) Wallet::query()->sum('total_invested')),
            'totalRoiPaid' => Money::fromMinor((int) Wallet::query()->sum('total_roi_earned')),
            'totalCommissions' => Money::fromMinor((int) Wallet::query()->sum('total_referral_earned')),

            // The platform's live liability: everything users could withdraw or
            // invest right now.
            'walletLiability' => Money::fromMinor(
                (int) Wallet::query()->sum('deposit_balance')
                + (int) Wallet::query()->sum('withdrawable_balance')
                + (int) Wallet::query()->sum('locked_balance')
            ),

            'activeInvestments' => Investment::query()->active()->count(),
            'activePrincipal' => Money::fromMinor(
                (int) Investment::query()->active()->sum('principal')
            ),
            'outstandingRoi' => Money::fromMinor(
                (int) Investment::query()->active()->sum('total_expected_roi')
                - (int) Investment::query()->active()->sum('total_roi_paid')
            ),

            'pendingDeposits' => Deposit::query()->awaitingReview()->count(),
            'pendingWithdrawals' => Withdrawal::query()->pending()->count(),
            'pendingWithdrawalValue' => Money::fromMinor(
                (int) Withdrawal::query()->pending()->sum('amount')
            ),

            'recentDeposits' => Deposit::query()->with('user')->latest()->limit(6)->get(),
            'recentWithdrawals' => Withdrawal::query()->with('user')->latest()->limit(6)->get(),
            'recentAudit' => AdminAuditLog::query()->latest()->limit(8)->get(),

            'window' => $window,
        ]);
    }
}
