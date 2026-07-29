<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query()->with('wallet')->withCount('referrals')->latest();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        match ($request->query('filter')) {
            'blocked' => $query->where('is_blocked', true),
            'admins' => $query->where('is_admin', true),
            'investors' => $query->whereHas('investments'),
            default => null,
        };

        return view('admin.users.index', [
            'users' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'activeFilter' => $request->query('filter', 'all'),
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->load(['wallet', 'referrer', 'bankAccounts']),
            'investments' => $user->investments()->with('plan')->latest()->limit(10)->get(),
            'deposits' => $user->deposits()->latest()->limit(10)->get(),
            'withdrawals' => $user->withdrawals()->latest()->limit(10)->get(),
            'transactions' => $user->transactions()->latest()->limit(20)->get(),
            'commissions' => $user->referralCommissions()->with('sourceUser')->latest()->limit(10)->get(),
            'directReferrals' => $user->referrals()->latest()->limit(10)->get(),
        ]);
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['reason' => 'You cannot suspend your own account.']);
        }

        $user->block($validated['reason']);

        $this->audit->log(
            action: 'user.blocked',
            description: "Suspended {$user->email}: {$validated['reason']}",
            subject: $user,
            before: ['is_blocked' => false],
            after: ['is_blocked' => true, 'reason' => $validated['reason']],
        );

        return back()->with('status', "{$user->name} has been suspended.");
    }

    public function unblock(User $user): RedirectResponse
    {
        $user->unblock();

        $this->audit->log(
            action: 'user.unblocked',
            description: "Restored access for {$user->email}",
            subject: $user,
            before: ['is_blocked' => true],
            after: ['is_blocked' => false],
        );

        return back()->with('status', "{$user->name} can sign in again.");
    }

    /**
     * Hand-adjust a wallet. Deliberately explicit about the bucket, because
     * crediting `withdrawable` creates immediately cashable money whereas
     * `deposit` does not.
     */
    public function adjustWallet(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bucket' => ['required', 'in:deposit,withdrawable'],
            'direction' => ['required', 'in:credit,debit'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Record why this adjustment is being made.',
        ]);

        $amount = Money::fromMajor($validated['amount']);

        try {
            $transaction = $this->wallet->adminAdjust(
                user: $user,
                amount: $amount,
                bucket: $validated['bucket'],
                direction: $validated['direction'],
                description: $validated['reason'],
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $this->audit->log(
            action: 'wallet.adjusted',
            description: sprintf(
                '%s %s to %s balance of %s -- %s',
                ucfirst($validated['direction']),
                $amount->formatWithSymbol(),
                $validated['bucket'],
                $user->email,
                $validated['reason'],
            ),
            subject: $user,
            before: ['balance' => $transaction->balance_before->format()],
            after: ['balance' => $transaction->balance_after->format()],
        );

        return back()->with('status', sprintf(
            '%s %s. New %s balance: %s.',
            $validated['direction'] === 'credit' ? 'Credited' : 'Debited',
            $amount->formatWithSymbol(),
            $validated['bucket'],
            $transaction->balance_after->formatWithSymbol(),
        ));
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot change your own admin status.']);
        }

        // Refuse to remove the last administrator, which would lock everyone out
        // of the panel entirely.
        if ($user->is_admin && User::query()->where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['user' => 'This is the only administrator left. Promote someone else first.']);
        }

        $user->is_admin ? $user->revokeAdmin() : $user->grantAdmin();

        $user->refresh();

        $this->audit->log(
            action: $user->is_admin ? 'user.promoted' : 'user.demoted',
            description: ($user->is_admin ? 'Granted admin access to ' : 'Revoked admin access from ').$user->email,
            subject: $user,
        );

        return back()->with('status', $user->is_admin
            ? "{$user->name} is now an administrator."
            : "{$user->name} is no longer an administrator.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ]);

        $newPassword = filled($validated['password'] ?? null)
            ? $validated['password']
            : str()->random(10);

        $user->forceFill([
            'password' => \Illuminate\Support\Facades\Hash::make($newPassword),
        ])->save();

        $this->audit->log(
            action: 'user.password_reset',
            description: "Reset password for user {$user->email}",
            subject: $user,
        );

        return back()->with('status', "Password for {$user->name} successfully reset to: {$newPassword}");
    }
}
