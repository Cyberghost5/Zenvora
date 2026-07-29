@php
    $editing = $plan->exists;

    $dailyPercent = $plan->daily_roi_bp
        ? rtrim(rtrim(number_format($plan->daily_roi_bp / 100, 2, '.', ''), '0'), '.')
        : '';
@endphp

<x-layouts.admin :title="$editing ? 'Edit '.$plan->name : 'New plan'"
                 :heading="$editing ? 'Edit plan' : 'New plan'"
                 :subheading="$editing
                     ? 'Changes apply to new investments only - running contracts keep their original terms.'
                     : 'Set the rate, term and limits for this plan.'">

    <x-slot:actions>
        <a href="{{ route('admin.plans.index') }}" class="btn-ghost">&larr; All plans</a>
    </x-slot:actions>

    <form method="POST"
          action="{{ $editing ? route('admin.plans.update', $plan) : route('admin.plans.store') }}"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <h2 class="font-semibold text-white">Identity</h2>

                <div class="mt-4 space-y-5">
                    <div>
                        <label for="name" class="label">Plan name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $plan->name) }}"
                               required class="input" placeholder="e.g. Growth">
                        <x-input-error for="name" />
                    </div>

                    <div>
                        <label for="slug" class="label">
                            URL slug <span class="font-normal text-slate-500">(optional)</span>
                        </label>
                        <input id="slug" name="slug" type="text" value="{{ old('slug', $plan->slug) }}"
                               class="input font-mono" placeholder="Generated from the name if left blank">
                        <x-input-error for="slug" />
                    </div>

                    <div>
                        <label for="tagline" class="label">
                            Tagline <span class="font-normal text-slate-500">(optional)</span>
                        </label>
                        <input id="tagline" name="tagline" type="text" value="{{ old('tagline', $plan->tagline) }}"
                               class="input" placeholder="One line shown under the plan name">
                        <x-input-error for="tagline" />
                    </div>

                    <div>
                        <label for="description" class="label">
                            Description <span class="font-normal text-slate-500">(optional)</span>
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  class="input">{{ old('description', $plan->description) }}</textarea>
                        <x-input-error for="description" />
                    </div>
                </div>
            </section>

            <section class="card">
                <h2 class="font-semibold text-white">Terms</h2>

                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="daily_roi_percent" class="label">Daily return percentage</label>
                        <div class="relative">
                            <input id="daily_roi_percent" name="daily_roi_percent" type="number"
                                   step="0.01" min="0.01" max="100"
                                   value="{{ old('daily_roi_percent', $dailyPercent) }}"
                                   required class="input tabular !pr-8">
                            <span class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-500">%</span>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">Percentage of the principal, paid each day.</p>
                        <x-input-error for="daily_roi_percent" />
                    </div>

                    <div>
                        <label for="fixed_daily_payout" class="label">
                            Fixed daily income <span class="font-normal text-slate-500">(optional)</span>
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                                {{ config('zenvora.currency_symbol') }}
                            </span>
                            <input id="fixed_daily_payout" name="fixed_daily_payout" type="number" step="0.01" min="0.01"
                                   value="{{ old('fixed_daily_payout', $plan->fixed_daily_payout ? number_format($plan->fixed_daily_payout->toMajor(), 2, '.', '') : '') }}"
                                   class="input tabular !pl-9" placeholder="e.g. 1000.00 for ₦1,000/day">
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">Overrides percentage with exact daily naira payout.</p>
                        <x-input-error for="fixed_daily_payout" />
                    </div>

                    <div>
                        <label for="duration_days" class="label">Term length</label>
                        <div class="relative">
                            <input id="duration_days" name="duration_days" type="number" min="1" max="3650"
                                   value="{{ old('duration_days', $plan->duration_days) }}"
                                   required class="input tabular !pr-14">
                            <span class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-500">days</span>
                        </div>
                        <x-input-error for="duration_days" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="min_amount" class="label">Fixed Plan Price / Investment Amount</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                                {{ config('zenvora.currency_symbol') }}
                            </span>
                            <input id="min_amount" name="min_amount" type="number" step="0.01" min="0.01"
                                   value="{{ old('min_amount', $plan->exists ? number_format($plan->min_amount->toMajor(), 2, '.', '') : '') }}"
                                   required class="input tabular !pl-9" placeholder="e.g. 3600.00 for ₦3,600">
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">The exact fixed price required for users to subscribe to this plan.</p>
                        <x-input-error for="min_amount" />
                    </div>
                </div>

                <div class="mt-5 space-y-3 border-t border-white/5 pt-5">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="return_capital" value="1"
                               @checked(old('return_capital', $plan->return_capital ?? true))
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                        <span>
                            <span class="block text-sm font-medium text-white">Return capital at maturity</span>
                            <span class="mt-0.5 block text-xs text-slate-500">
                                The principal is credited to the withdrawable balance on the maturity date, on top
                                of the accrued daily returns.
                            </span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="referral_eligible" value="1"
                               @checked(old('referral_eligible', $plan->referral_eligible ?? true))
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                        <span>
                            <span class="block text-sm font-medium text-white">Pays referral commission</span>
                            <span class="mt-0.5 block text-xs text-slate-500">
                                Investing in this plan credits the investor's upline.
                            </span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $plan->is_active ?? true))
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                        <span>
                            <span class="block text-sm font-medium text-white">Visible to users</span>
                            <span class="mt-0.5 block text-xs text-slate-500">
                                Unticking hides it from the site without affecting running investments.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="mt-5 border-t border-white/5 pt-5">
                    <label for="sort_order" class="label">Display order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="999"
                           value="{{ old('sort_order', $plan->sort_order ?? 0) }}"
                           class="input tabular max-w-32">
                    <p class="mt-1.5 text-xs text-slate-500">Lower numbers appear first.</p>
                    <x-input-error for="sort_order" />
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-primary">
                    {{ $editing ? 'Save changes' : 'Create plan' }}
                </button>
                <a href="{{ route('admin.plans.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </div>

        {{-- ------------------------------------------------------------ --}}
        {{-- Guidance                                                     --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="space-y-4">
            @if ($editing)
                <div class="card">
                    <h2 class="font-semibold text-white">Current terms</h2>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-400">Daily</dt>
                            <dd class="tabular font-semibold text-brand-300">{{ $plan->dailyRoiLabel() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">Total over term</dt>
                            <dd class="tabular font-semibold text-white">{{ $plan->totalRoiLabel() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">On the minimum</dt>
                            <dd class="tabular text-right font-medium text-white">
                                {{ $plan->totalReturnFor($plan->min_amount)->formatWithSymbol() }}
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            <div class="card">
                <h2 class="font-semibold text-white">Before you publish</h2>
                <ul class="mt-3 space-y-2.5 text-xs leading-relaxed text-slate-400">
                    <li>
                        <strong class="font-semibold text-slate-300">Check the compounded total.</strong>
                        A 2.5% daily rate over 30 days is a 75% return on capital. Confirm the platform can
                        actually fund that before publishing.
                    </li>
                    <li>
                        <strong class="font-semibold text-slate-300">Terms are frozen per investment.</strong>
                        Existing contracts keep the rate they were sold at, so a correction here does not fix
                        an already-mispriced plan.
                    </li>
                    <li>
                        <strong class="font-semibold text-slate-300">Watch the minimum.</strong>
                        If the daily rate rounds to less than one kobo on the minimum amount, the investment is
                        refused at subscription time.
                    </li>
                    <li>
                        <strong class="font-semibold text-slate-300">Referral cost adds up.</strong>
                        Commission is paid on top of the ROI, out of the same pool.
                    </li>
                </ul>
            </div>
        </section>
    </form>
</x-layouts.admin>
