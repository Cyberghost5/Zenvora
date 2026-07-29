<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('user.profile', [
            'user' => $user,
            'accounts' => $user->bankAccounts()->orderByDesc('is_primary')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'min:7', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->update($validated);

        // A changed email has to be re-proven, otherwise the password-reset
        // channel could be pointed at an address nobody owns. Done separately
        // because email_verified_at is not fillable -- were it fillable, a user
        // could self-verify by adding the field to this form.
        if ($emailChanged) {
            $user->markEmailAsUnverified();
        }

        return back()->with('status', 'Your details have been saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'string', 'min:6'],
        ], [
            'current_password.current_password' => 'That is not your current password.',
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return back()->with('status', 'Your password has been changed.');
    }

    // -----------------------------------------------------------------
    // Payout accounts
    // -----------------------------------------------------------------

    public function storeBankAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_number' => [
                'required', 'string', 'max:32', 'regex:/^[0-9]+$/',
                Rule::unique('bank_accounts', 'account_number')->where('user_id', $user->id),
            ],
            'account_name' => ['required', 'string', 'max:120'],
        ], [
            'account_number.regex' => 'An account number should contain digits only.',
            'account_number.unique' => 'You have already saved that account.',
        ]);

        DB::transaction(function () use ($user, $validated) {
            // The first account saved becomes the default payout destination.
            $isFirst = ! $user->bankAccounts()->exists();

            $user->bankAccounts()->create([
                ...$validated,
                'is_primary' => $isFirst,
            ]);
        });

        return back()->with('status', 'Payout account saved.');
    }

    public function makePrimary(Request $request, BankAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($request, $account) {
            $request->user()->bankAccounts()->update(['is_primary' => false]);
            $account->update(['is_primary' => true]);
        });

        return back()->with('status', 'Default payout account updated.');
    }

    public function destroyBankAccount(Request $request, BankAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        // Removing the destination of a request an admin has not yet paid would
        // leave that payment without a verifiable target.
        $hasOpenWithdrawal = $request->user()->withdrawals()
            ->pending()
            ->where('account_number', $account->account_number)
            ->exists();

        if ($hasOpenWithdrawal) {
            return back()->withErrors([
                'account' => 'That account has a withdrawal still being processed and cannot be removed yet.',
            ]);
        }

        $wasPrimary = $account->is_primary;
        $account->delete();

        // Promote another account so the user is never left with none marked.
        if ($wasPrimary) {
            $request->user()->bankAccounts()->oldest()->first()?->update(['is_primary' => true]);
        }

        return back()->with('status', 'Payout account removed.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Refuse while money is in play. A deleted user with an open contract or
        // a pending payout leaves an unresolvable liability.
        if ($user->investments()->active()->exists()) {
            return back()->withErrors(['password' => 'You still have an active investment. Wait for it to mature first.']);
        }

        if ($user->withdrawals()->pending()->exists()) {
            return back()->withErrors(['password' => 'You have a withdrawal being processed. Please wait for it to complete.']);
        }

        $wallet = $user->ensureWallet();

        if ($wallet->totalBalance()->isPositive()) {
            return back()->withErrors(['password' => 'Your wallet still holds a balance. Withdraw it before closing your account.']);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'That password is incorrect.']);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Your account has been closed.');
    }
}
