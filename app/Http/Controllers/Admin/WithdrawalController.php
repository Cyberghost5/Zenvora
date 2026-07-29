<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\AuditLogger;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawals,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        $query = Withdrawal::query()->with(['user', 'processor'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return view('admin.withdrawals.index', [
            'withdrawals' => $query->paginate(20)->withQueryString(),
            'activeStatus' => $status,
            'search' => $search,
            'counts' => [
                'pending' => Withdrawal::query()->where('status', 'pending')->count(),
                'processing' => Withdrawal::query()->where('status', 'processing')->count(),
                'paid' => Withdrawal::query()->where('status', 'paid')->count(),
                'rejected' => Withdrawal::query()->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function show(Withdrawal $withdrawal): View
    {
        return view('admin.withdrawals.show', [
            'withdrawal' => $withdrawal->load(['user.wallet', 'processor']),
        ]);
    }

    /**
     * Flag that the transfer is being made, so a second admin does not also
     * start paying the same request.
     */
    public function markProcessing(Withdrawal $withdrawal): RedirectResponse
    {
        if ($withdrawal->status !== 'pending') {
            return back()->withErrors(['withdrawal' => 'Only a pending request can be moved to processing.']);
        }

        $withdrawal->update(['status' => 'processing']);

        $this->audit->log(
            action: 'withdrawal.processing',
            description: "Marked {$withdrawal->reference} as being processed",
            subject: $withdrawal,
        );

        return back()->with('status', "{$withdrawal->reference} marked as processing.");
    }

    /**
     * Confirm the bank transfer has been sent. The held funds leave the wallet.
     */
    public function markPaid(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->withdrawals->markPaid($withdrawal, $request->user(), $validated['note'] ?? null);
        } catch (Throwable $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        $this->audit->log(
            action: 'withdrawal.paid',
            description: sprintf(
                'Paid %s to %s (%s / %s)',
                $withdrawal->net_amount->formatWithSymbol(),
                $withdrawal->user->email,
                $withdrawal->bank_name,
                $withdrawal->account_number,
            ),
            subject: $withdrawal,
            before: ['status' => 'pending'],
            after: ['status' => 'paid', 'note' => $validated['note'] ?? null],
        );

        return redirect()->route('admin.withdrawals.index')->with(
            'status',
            "{$withdrawal->reference} settled. {$withdrawal->net_amount->formatWithSymbol()} released.",
        );
    }

    /**
     * Decline the request and hand the money back to the user.
     */
    public function reject(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Give the user a reason -- it is shown to them.',
        ]);

        try {
            $this->withdrawals->reject($withdrawal, $request->user(), $validated['reason']);
        } catch (Throwable $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        $this->audit->log(
            action: 'withdrawal.rejected',
            description: "Rejected {$withdrawal->reference}: {$validated['reason']}",
            subject: $withdrawal,
            before: ['status' => 'pending'],
            after: ['status' => 'rejected', 'reason' => $validated['reason']],
        );

        return redirect()->route('admin.withdrawals.index')->with(
            'status',
            "{$withdrawal->reference} rejected and {$withdrawal->amount->formatWithSymbol()} returned to the user.",
        );
    }
}
