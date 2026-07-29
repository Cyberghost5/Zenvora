<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\WithdrawalService;
use App\Services\WithdrawalWindow;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawals,
        private readonly WithdrawalWindow $window,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('user.withdrawals.index', [
            'wallet' => $user->ensureWallet(),
            'withdrawals' => $user->withdrawals()->latest()->paginate(15),
            'accounts' => $user->bankAccounts()->orderByDesc('is_primary')->get(),
            'window' => $this->window,
            'min' => $this->withdrawals->minimum(),
            'max' => $this->withdrawals->maximum(),
            'feeBp' => config('zenvora.defaults.withdrawal_fee_bp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
        ], [
            'bank_account_id.required' => 'Choose the account you want to be paid into.',
        ]);

        $account = BankAccount::query()->findOrFail($validated['bank_account_id']);

        try {
            $withdrawal = $this->withdrawals->request(
                user: $request->user(),
                amount: Money::fromMajor($validated['amount']),
                account: $account,
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('withdrawals.index')->with(
            'status',
            sprintf(
                'Request %s for %s submitted. The amount is held until an administrator processes it.',
                $withdrawal->reference,
                $withdrawal->amount->formatWithSymbol(),
            ),
        );
    }
}
