<x-layouts.app :title="$investment->reference"
               :heading="$investment->plan->name ?? 'Investment'"
               :subheading="'Reference '.$investment->reference">

    <x-slot:actions>
        <a href="{{ route('investments.index') }}" class="btn-ghost">&larr; All investments</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Principal" :value="$investment->principal->formatWithSymbol()" />

        <x-stat label="Returns paid"
                :value="$investment->total_roi_paid->formatWithSymbol()"
                :hint="$investment->days_paid.' of '.$investment->duration_days.' days'"
                tone="positive" />

        <x-stat label="Still to come"
                :value="$investment->outstandingRoi()->formatWithSymbol()"
                :hint="$investment->daysRemaining().' days remaining'" />

        <x-stat label="Total at maturity"
                :value="$investment->totalReturn()->formatWithSymbol()"
                :hint="$investment->return_capital ? 'Includes returned capital' : 'Capital included in payout'"
                tone="brand" />
    </div>

    @if ($investment->status === 'active' && ! $investment->hasRunFullTerm() && $investment->nextPayoutAt())
        <div class="mt-6 rounded-2xl border border-brand-500/30 bg-gradient-to-b from-brand-500/10 via-ink-900 to-ink-950 p-6"
             data-countdown="{{ $investment->nextPayoutAt()->getTimestamp() * 1000 }}">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-brand-500"></span>
                        </span>
                        <h3 class="text-base font-semibold text-white">Next Income Countdown</h3>
                    </div>
                    <p class="mt-1 text-sm text-slate-400">
                        Next daily return of <strong class="text-emerald-400 font-semibold">{{ $investment->daily_payout->formatWithSymbol() }}</strong> will be credited when timer expires.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex flex-col items-center justify-center rounded-xl border border-white/10 px-4 py-2.5 min-w-[70px]">
                        <span class="tabular text-2xl font-bold text-brand-300" data-hours>00</span>
                        <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Hours</span>
                    </div>
                    <span class="text-xl font-bold text-slate-600">:</span>
                    <div class="flex flex-col items-center justify-center rounded-xl border border-white/10 px-4 py-2.5 min-w-[70px]">
                        <span class="tabular text-2xl font-bold text-brand-300" data-minutes>00</span>
                        <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Mins</span>
                    </div>
                    <span class="text-xl font-bold text-slate-600">:</span>
                    <div class="flex flex-col items-center justify-center rounded-xl border border-white/10 px-4 py-2.5 min-w-[70px]">
                        <span class="tabular text-2xl font-bold text-brand-300" data-seconds>00</span>
                        <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Secs</span>
                    </div>
                </div>
            </div>

            <div data-payout-due-msg class="hidden mt-4 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-2.5 text-xs text-emerald-400 text-center font-medium">
                <i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Daily payout is due and processing...
            </div>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- Contract terms, frozen at subscription                       --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card lg:col-span-1">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-white">Agreed terms</h2>
                <span @class([
                    'pill',
                    'bg-brand-500/10 text-brand-300 ring-brand-500/20' => $investment->status === 'active',
                    'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $investment->status === 'completed',
                    'bg-rose-500/10 text-rose-400 ring-rose-500/20' => $investment->status === 'cancelled',
                ])>
                    {{ ucfirst($investment->status) }}
                </span>
            </div>

            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-400">Daily rate</dt>
                    <dd class="tabular font-semibold text-brand-300">{{ $investment->dailyRoiLabel() }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Daily payout</dt>
                    <dd class="tabular font-semibold text-white">{{ $investment->daily_payout->formatWithSymbol() }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Term</dt>
                    <dd class="font-medium text-white">{{ $investment->duration_days }} days</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Started</dt>
                    <dd class="font-medium text-white">{{ $investment->started_on->format('j M Y') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Matures</dt>
                    <dd class="font-medium text-white">{{ $investment->matures_on->format('j M Y') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Capital</dt>
                    <dd class="font-medium {{ $investment->return_capital ? 'text-emerald-400' : 'text-slate-300' }}">
                        {{ $investment->return_capital ? 'Returned at maturity' : 'In payout' }}
                    </dd>
                </div>
            </dl>

            {{-- Explain why these figures may differ from the plan page. --}}
            <p class="mt-4 border-t border-white/5 pt-4 text-xs leading-relaxed text-slate-500">
                These terms were fixed when you subscribed. Later changes to the
                {{ $investment->plan->name ?? 'plan' }} plan do not affect this investment.
            </p>

            @if ($investment->status === 'cancelled' && $investment->cancellation_reason)
                <p class="mt-4 rounded-xl border border-rose-500/25 bg-rose-500/10 px-3 py-2.5 text-xs text-rose-200">
                    Cancelled: {{ $investment->cancellation_reason }}
                </p>
            @endif
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Payout ledger                                                --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="lg:col-span-2">
            <div class="mb-4 flex items-end justify-between gap-4">
                <h2 class="text-lg font-semibold text-white">Payout history</h2>
                <p class="text-sm text-slate-500">Every credited day, in order</p>
            </div>

            <div class="mb-5">
                <div class="mb-1.5 flex items-center justify-between text-xs text-slate-500">
                    <span>Day {{ $investment->days_paid }} of {{ $investment->duration_days }}</span>
                    <span class="tabular">{{ $investment->progressPercent() }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-brand-500 transition-all"
                         style="width: {{ $investment->progressPercent() }}%"
                         role="progressbar"
                         aria-valuenow="{{ $investment->progressPercent() }}"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-label="Term progress"></div>
                </div>
            </div>

            @if ($payouts->isEmpty())
                <x-empty-state title="No payouts credited yet"
                               message="Your first daily return is credited within 24 hours of subscribing." />
            @else
                <div class="overflow-hidden rounded-2xl border border-white/10">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium">Day</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Date</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Type</th>
                                    <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach ($payouts as $payout)
                                    <tr class="bg-ink-900/40">
                                        <td class="tabular px-4 py-3 text-slate-400">
                                            {{ $payout->kind === 'capital_return' ? '-' : $payout->day_index }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-300">
                                            {{ $payout->accrual_date->format('j M Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'pill',
                                                'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $payout->kind === 'roi',
                                                'bg-brand-500/10 text-brand-300 ring-brand-500/20' => $payout->kind === 'capital_return',
                                            ])>
                                                {{ $payout->kind === 'roi' ? 'Daily return' : 'Capital returned' }}
                                            </span>
                                        </td>
                                        <td class="tabular px-4 py-3 text-right font-semibold text-emerald-400">
                                            +{{ $payout->amount->formatWithSymbol() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">{{ $payouts->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
