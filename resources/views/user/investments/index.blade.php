<x-layouts.app title="Investments"
               heading="Investments"
               subheading="Invest from your deposit balance and track every plan you're running.">

    <x-slot:actions>
        <a href="{{ route('deposits.create') }}" class="btn-ghost">Fund wallet</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Available to invest"
                :value="$wallet->deposit_balance->formatWithSymbol()"
                hint="Your deposit balance."
                tone="brand" />

        <x-stat label="Currently invested"
                :value="$wallet->total_invested->formatWithSymbol()"
                hint="Lifetime total placed into plans." />

        <x-stat label="Returns earned"
                :value="$wallet->total_roi_earned->formatWithSymbol()"
                hint="Credited to your withdrawable balance."
                tone="positive" />
    </div>

    @if ($wallet->deposit_balance->isZero())
        <div class="mt-6 flex flex-wrap items-center gap-3 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            <span>Your deposit balance is empty, so there is nothing to invest yet.</span>
            <a href="{{ route('deposits.create') }}" class="ml-auto font-medium underline hover:text-amber-100">Fund your wallet</a>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Plans, each with its own form and live projection                --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-8">
        <h2 class="mb-4 text-lg font-semibold text-white">Choose a plan</h2>

        @if ($plans->isEmpty())
            <x-empty-state title="No plans available"
                           message="An administrator has not published any plans yet." />
        @else
            <div class="grid gap-5 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    @php
                        // Only offer amounts the wallet can actually cover.
                        $affordable = ! $wallet->deposit_balance->lessThan($plan->min_amount);
                    @endphp

                    <div id="plan-{{ $plan->id }}"
                         class="flex flex-col rounded-2xl border border-white/10 bg-ink-900/70 p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-white">{{ $plan->name }}</h3>
                                @if ($plan->tagline)
                                    <p class="mt-0.5 text-sm text-slate-400">{{ $plan->tagline }}</p>
                                @endif
                            </div>
                            <span class="pill shrink-0 bg-brand-500/10 text-brand-300 ring-brand-500/20">
                                {{ $plan->dailyRoiLabel() }} / day
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 border-y border-white/5 py-4 text-sm">
                            <div>
                                <dt class="text-xs text-slate-500">Plan Price</dt>
                                <dd class="tabular mt-0.5 font-bold text-white text-base">{{ $plan->min_amount->formatWithSymbol() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Daily Income</dt>
                                <dd class="tabular mt-0.5 font-bold text-emerald-400 text-base">
                                    {{ $plan->dailyPayoutFor($plan->min_amount)->formatWithSymbol() }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Cycle Duration</dt>
                                <dd class="mt-0.5 font-medium text-white">{{ $plan->durationLabel() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Total Return</dt>
                                <dd class="tabular mt-0.5 font-semibold text-brand-300">
                                    {{ $plan->totalReturnFor($plan->min_amount)->formatWithSymbol() }}
                                </dd>
                            </div>
                        </dl>

                        <p class="mt-3 text-xs {{ $plan->return_capital ? 'text-emerald-400' : 'text-slate-400' }}">
                            {{ $plan->return_capital
                                ? 'Capital is returned at the end of the 7-day cycle.'
                                : 'Capital is included in the total payout.' }}
                        </p>

                        <form method="POST" action="{{ route('investments.store') }}" class="mt-auto pt-4">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="amount" value="{{ $plan->min_amount->toMajor() }}">

                            @if (old('plan_id') == $plan->id)
                                <x-input-error for="plan_id" />
                            @endif

                            @if ($affordable)
                                <button type="submit" class="btn-primary mt-2 w-full">
                                    Subscribe ({{ $plan->min_amount->formatWithSymbol() }})
                                </button>
                            @else
                                <p class="mt-2 rounded-xl border border-white/10 px-3 py-2.5 text-center text-xs text-slate-500">
                                    Needs {{ $plan->min_amount->formatWithSymbol() }} in deposit balance
                                </p>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- History                                                          --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-white">Your investments</h2>

        @if ($investments->isEmpty())
            <x-empty-state title="No investments yet"
                           message="Once you invest, each plan and its daily payouts appear here." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                                <th scope="col" class="px-4 py-3 font-medium">Plan</th>
                                <th scope="col" class="px-4 py-3 font-medium">Principal</th>
                                <th scope="col" class="px-4 py-3 font-medium">Paid</th>
                                <th scope="col" class="px-4 py-3 font-medium">Progress</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($investments as $investment)
                                <tr class="bg-ink-900/40 transition hover:bg-ink-900">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('investments.show', $investment) }}"
                                           class="font-mono text-xs text-brand-400 hover:text-brand-300">
                                            {{ $investment->reference }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-white">{{ $investment->plan->name ?? '-' }}</td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-white">{{ $investment->principal->formatWithSymbol() }}</td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-emerald-400">{{ $investment->total_roi_paid->formatWithSymbol() }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full rounded-full bg-brand-500"
                                                     style="width: {{ $investment->progressPercent() }}%"></div>
                                            </div>
                                            <span class="tabular text-xs text-slate-500">
                                                {{ $investment->days_paid }}/{{ $investment->duration_days }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'pill',
                                            'bg-brand-500/10 text-brand-300 ring-brand-500/20' => $investment->status === 'active',
                                            'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $investment->status === 'completed',
                                            'bg-rose-500/10 text-rose-400 ring-rose-500/20' => $investment->status === 'cancelled',
                                        ])>
                                            {{ ucfirst($investment->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $investments->links() }}</div>
        @endif
    </section>
</x-layouts.app>
