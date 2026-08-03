<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\AuditLogger;
use App\Services\DepositService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'awaiting_review');

        $query = Deposit::query()->with(['user', 'reviewer'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return view('admin.deposits.index', [
            'deposits' => $query->paginate(20)->withQueryString(),
            'activeStatus' => $status,
            'search' => $search,
            'counts' => [
                'awaiting_review' => Deposit::query()->awaitingReview()->count(),
                'pending' => Deposit::query()->where('status', 'pending')->count(),
                'successful' => Deposit::query()->where('status', 'successful')->count(),
                'failed' => Deposit::query()->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function show(Deposit $deposit): View
    {
        return view('admin.deposits.show', [
            'deposit' => $deposit->load(['user.wallet', 'coupon', 'reviewer']),
        ]);
    }

    /**
     * Confirm a deposit and credit the wallet.
     *
     * The admin may correct the amount, because a manual transfer sometimes
     * arrives for a different figure than the user typed.
     */
    public function approve(Request $request, Deposit $deposit): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $amount = isset($validated['amount']) ? Money::fromMajor($validated['amount']) : null;

        try {
            $this->deposits->approve($deposit, $request->user(), $amount);
        } catch (Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $credited = $deposit->fresh();

        $this->audit->log(
            action: 'deposit.approved',
            description: sprintf(
                'Credited %s to %s via %s',
                $credited->amount->formatWithSymbol(),
                $credited->user->email,
                $credited->channelLabel(),
            ),
            subject: $credited,
            before: ['status' => $deposit->status, 'amount' => $deposit->amount->format()],
            after: ['status' => 'successful', 'amount' => $credited->amount->format()],
        );

        return redirect()->route('admin.deposits.index')->with(
            'status',
            "{$credited->reference}: {$credited->amount->formatWithSymbol()} credited to {$credited->user->name}.",
        );
    }

    public function reject(Request $request, Deposit $deposit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Give the user a reason -- it is shown to them.',
        ]);

        try {
            $this->deposits->reject($deposit, $request->user(), $validated['reason']);
        } catch (Throwable $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        $this->audit->log(
            action: 'deposit.rejected',
            description: "Rejected {$deposit->reference}: {$validated['reason']}",
            subject: $deposit,
            before: ['status' => 'awaiting_review'],
            after: ['status' => 'failed', 'reason' => $validated['reason']],
        );

        return redirect()->route('admin.deposits.index')
            ->with('status', "{$deposit->reference} rejected.");
    }

    /**
     * Re-ask the gateway about a deposit that is stuck pending, for when a
     * callback was lost.
     */
    public function reverify(Deposit $deposit): RedirectResponse
    {
        if (! in_array($deposit->channel, ['paystack', 'flutterwave', 'korapay'], true)) {
            return back()->withErrors(['deposit' => 'Only gateway deposits can be re-verified.']);
        }

        try {
            $credited = $this->deposits->confirmGatewayDeposit(
                $deposit,
                $deposit->gateway_reference ?: $deposit->reference,
            );
        } catch (Throwable $e) {
            return back()->withErrors(['deposit' => 'The gateway could not be reached: '.$e->getMessage()]);
        }

        $this->audit->log(
            action: 'deposit.reverified',
            description: sprintf('Re-verified %s against %s: %s',
                $deposit->reference,
                $deposit->channelLabel(),
                $credited ? 'credited' : 'not successful',
            ),
            subject: $deposit->fresh(),
        );

        return back()->with('status', $credited
            ? "{$deposit->reference} confirmed and credited."
            : "The gateway reports {$deposit->reference} was not completed.");
    }
}
