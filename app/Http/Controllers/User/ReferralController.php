<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __invoke(Request $request, ReferralService $referrals): View
    {
        $user = $request->user();

        return view('user.referrals', [
            'wallet' => $user->ensureWallet(),
            'tiers' => $referrals->tierTable(),
            'counts' => $referrals->downlineCounts($user),

            'directReferrals' => $user->referrals()
                ->withCount('investments')
                ->latest()
                ->paginate(10),

            'commissions' => $user->referralCommissions()
                ->with('sourceUser')
                ->latest()
                ->limit(20)
                ->get(),

            'link' => $user->referralLink(),
        ]);
    }
}
