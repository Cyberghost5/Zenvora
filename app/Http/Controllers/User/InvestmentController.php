<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Plan;
use App\Services\InvestmentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class InvestmentController extends Controller
{
    public function __construct(private readonly InvestmentService $investments) {}

    public function plans(Request $request): View
    {
        $user = $request->user();

        return view('user.plans.index', [
            'wallet' => $user->ensureWallet(),
            'plans' => Plan::query()->active()->ordered()->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('user.investments.index', [
            'wallet' => $user->ensureWallet(),
            'investments' => $user->investments()->with('plan')->latest()->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);
        $amount = ! empty($validated['amount']) ? Money::fromMajor($validated['amount']) : null;

        try {
            $investment = $this->investments->subscribe(
                user: $request->user(),
                plan: $plan,
                amount: $amount,
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage(), 'plan_id' => $e->getMessage()]);
        }

        return redirect()->route('investments.show', $investment)->with(
            'status',
            sprintf(
                '%s invested in %s. Your first return is credited within 24 hours.',
                $investment->principal->formatWithSymbol(),
                $plan->name,
            ),
        );
    }

    public function show(Request $request, Investment $investment): View
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        if ($investment->status === 'active' && ! $investment->hasRunFullTerm()) {
            $this->investments->accrueDay($investment);
        }

        return view('user.investments.show', [
            'investment' => $investment->fresh(['plan']),
            'payouts' => $investment->payouts()->latest('accrual_date')->paginate(15),
        ]);
    }
}
