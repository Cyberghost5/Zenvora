<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $type = $request->query('type');

        $query = $request->user()->transactions()->latest();

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        return view('user.transactions', [
            'transactions' => $query->paginate(20)->withQueryString(),
            'wallet' => $request->user()->ensureWallet(),
            'activeType' => $type ?? 'all',
            'types' => [
                'all' => 'Everything',
                'deposit' => 'Deposits',
                'investment' => 'Investments',
                'roi' => 'Returns',
                'capital_return' => 'Capital returned',
                'referral_commission' => 'Commissions',
                'withdrawal' => 'Withdrawals',
            ],
        ]);
    }
}
