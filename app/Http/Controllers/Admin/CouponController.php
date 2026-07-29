<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::query()
                ->with('creator')
                ->withCount('redemptions')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:10000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'code' => ['nullable', 'string', 'max:32', 'unique:coupons,code'],
        ], [
            'expires_at.after' => 'An expiry date has to be in the future.',
            'code.unique' => 'That code already exists.',
        ]);

        $amount = Money::fromMajor($validated['amount']);
        $quantity = (int) $validated['quantity'];

        // A custom code only makes sense for a single coupon.
        if (filled($validated['code'] ?? null) && $quantity > 1) {
            return back()->withInput()->withErrors([
                'code' => 'A custom code can only be used when creating one coupon.',
            ]);
        }

        $created = collect(range(1, $quantity))->map(fn () => Coupon::query()->create([
            'code' => ($validated['code'] ?? null) ?: Coupon::generateCode(),
            'amount' => $amount,
            'max_uses' => $validated['max_uses'],
            'expires_at' => $validated['expires_at'] ?? null,
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]));

        $this->audit->log(
            action: 'coupon.created',
            description: sprintf(
                'Issued %d coupon(s) worth %s each (%d use(s) apiece)',
                $quantity,
                $amount->formatWithSymbol(),
                $validated['max_uses'],
            ),
            after: ['codes' => $created->pluck('code')->all()],
        );

        return back()->with('status', $quantity === 1
            ? "Coupon {$created->first()->code} created for {$amount->formatWithSymbol()}."
            : "{$quantity} coupons created for {$amount->formatWithSymbol()} each.");
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        $this->audit->log(
            action: $coupon->is_active ? 'coupon.enabled' : 'coupon.disabled',
            description: ($coupon->is_active ? 'Enabled' : 'Disabled')." coupon {$coupon->code}",
            subject: $coupon,
        );

        return back()->with('status', "Coupon {$coupon->code} ".($coupon->is_active ? 'enabled.' : 'disabled.'));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        // A redeemed coupon is part of the deposit trail, so disable instead.
        if ($coupon->redemptions()->exists()) {
            $coupon->update(['is_active' => false]);

            $this->audit->log(
                action: 'coupon.disabled',
                description: "Disabled redeemed coupon {$coupon->code} instead of deleting it",
                subject: $coupon,
            );

            return back()->with('status', "Coupon {$coupon->code} has been redeemed, so it was disabled rather than deleted.");
        }

        $code = $coupon->code;
        $coupon->delete();

        $this->audit->log(
            action: 'coupon.deleted',
            description: "Deleted unused coupon {$code}",
        );

        return back()->with('status', "Coupon {$code} deleted.");
    }
}
