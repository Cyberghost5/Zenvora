<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()
                ->withCount(['investments' => fn ($q) => $q->where('status', 'active')])
                ->ordered()
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => new Plan([
            'duration_days' => 10,
            'return_capital' => true,
            'referral_eligible' => true,
            'is_active' => true,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $plan = Plan::query()->create($data);

        $this->audit->log(
            action: 'plan.created',
            description: "Created plan {$plan->name}",
            subject: $plan,
            after: $this->snapshot($plan),
        );

        return redirect()->route('admin.plans.index')->with('status', "Plan “{$plan->name}” created.");
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $before = $this->snapshot($plan);

        $plan->update($this->validated($request, $plan));

        $this->audit->log(
            action: 'plan.updated',
            description: "Updated plan {$plan->name}",
            subject: $plan,
            before: $before,
            after: $this->snapshot($plan->fresh()),
        );

        return redirect()->route('admin.plans.index')->with(
            'status',
            "Plan “{$plan->name}” updated. Investments already running keep their original terms.",
        );
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // Deactivate rather than delete once a plan has history, so existing
        // contracts keep a resolvable parent.
        if ($plan->investments()->exists()) {
            $plan->update(['is_active' => false]);

            $this->audit->log(
                action: 'plan.deactivated',
                description: "Deactivated plan {$plan->name} (has investment history)",
                subject: $plan,
            );

            return back()->with(
                'status',
                "“{$plan->name}” has investment history, so it was deactivated rather than deleted.",
            );
        }

        $name = $plan->name;

        $this->audit->log(
            action: 'plan.deleted',
            description: "Deleted plan {$name}",
            before: $this->snapshot($plan),
        );

        $plan->delete();

        return back()->with('status', "Plan “{$name}” deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'min_amount' => ['required', 'numeric', 'min:0.01'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount'],
            'daily_roi_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'fixed_daily_payout' => ['nullable', 'numeric', 'min:0.01'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'return_capital' => ['nullable', 'boolean'],
            'referral_eligible' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'slug' => [
                'nullable', 'string', 'max:80', 'alpha_dash',
                Rule::unique('plans', 'slug')->ignore($plan?->id),
            ],
        ], [
            'max_amount.gte' => 'The maximum must be at least the minimum.',
            'image.image' => 'The uploaded file must be a valid image.',
        ]);

        $minMajor = $validated['min_amount'];
        $maxMajor = $validated['max_amount'] ?? $minMajor;

        $imagePath = $plan?->image_path;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('uploads/plans'), $filename);
            $imagePath = 'uploads/plans/'.$filename;
        }

        return [
            'name' => $validated['name'],
            'slug' => ($validated['slug'] ?? null) ?: $this->uniqueSlug($validated['name'], $plan),
            'tagline' => $validated['tagline'] ?? null,
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'min_amount' => Money::fromMajor($minMajor),
            'max_amount' => Money::fromMajor($maxMajor),

            // Percent in, basis points stored.
            'daily_roi_bp' => (int) round($validated['daily_roi_percent'] * 100),
            'fixed_daily_payout' => ! empty($validated['fixed_daily_payout'] ?? null)
                ? Money::fromMajor($validated['fixed_daily_payout'])
                : null,

            'duration_days' => $validated['duration_days'],
            'return_capital' => $request->boolean('return_capital'),
            'referral_eligible' => $request->boolean('referral_eligible'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug(string $name, ?Plan $plan): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $suffix = 2;

        while (Plan::query()
            ->where('slug', $slug)
            ->when($plan, fn ($q) => $q->whereKeyNot($plan->id))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Plan $plan): array
    {
        return [
            'name' => $plan->name,
            'min_amount' => $plan->min_amount->format(),
            'max_amount' => $plan->max_amount->format(),
            'daily_roi' => $plan->dailyRoiLabel(),
            'duration_days' => $plan->duration_days,
            'return_capital' => $plan->return_capital,
            'is_active' => $plan->is_active,
        ];
    }
}
