<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Services\AuditLogger;
use App\Services\InvestmentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class InvestmentController extends Controller
{
    public function __construct(
        private readonly InvestmentService $investments,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');

        $query = Investment::query()->with(['user', 'plan'])->latest();

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

        return view('admin.investments.index', [
            'investments' => $query->paginate(20)->withQueryString(),
            'activeStatus' => $status,
            'search' => $search,
            'counts' => [
                'active' => Investment::query()->where('status', 'active')->count(),
                'completed' => Investment::query()->where('status', 'completed')->count(),
                'cancelled' => Investment::query()->where('status', 'cancelled')->count(),
            ],
            'activePrincipal' => Money::fromMinor((int) Investment::query()->active()->sum('principal')),
        ]);
    }

    public function show(Investment $investment): View
    {
        return view('admin.investments.show', [
            'investment' => $investment->load(['user', 'plan']),
            'payouts' => $investment->payouts()->latest('accrual_date')->paginate(20),
            'commissions' => $investment->commissions()->with('user')->get(),
        ]);
    }

    public function cancel(Request $request, Investment $investment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refund_principal' => ['nullable', 'boolean'],
        ]);

        $refund = $request->boolean('refund_principal');

        try {
            $this->investments->cancel($investment, $validated['reason'], $refund);
        } catch (Throwable $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        $this->audit->log(
            action: 'investment.cancelled',
            description: sprintf(
                'Cancelled %s for %s (%s). Principal %s.',
                $investment->reference,
                $investment->user->email,
                $validated['reason'],
                $refund ? 'refunded to deposit balance' : 'not refunded',
            ),
            subject: $investment,
            before: ['status' => 'active'],
            after: ['status' => 'cancelled', 'refunded' => $refund],
        );

        return redirect()->route('admin.investments.index')->with(
            'status',
            "{$investment->reference} cancelled".($refund ? ' and the principal refunded.' : '.'),
        );
    }
}
