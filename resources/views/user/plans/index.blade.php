<x-layouts.app title="Investment Plans"
               heading="Investment Plans"
               subheading="Choose a plan to invest your deposit balance and earn daily returns.">

    @if ($wallet->deposit_balance->isZero())
        <div class="mt-6 flex flex-wrap items-center gap-3 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            <span>Your deposit balance is empty. Fund your wallet to subscribe to an investment plan.</span>
            <a href="{{ route('deposits.create') }}" class="ml-auto font-medium underline hover:text-amber-100">Fund your wallet</a>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Investment Plans List                                           --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-8">
        <h2 class="mb-4 text-lg font-semibold text-white">All Investment Plans</h2>

        @if ($plans->isEmpty())
            <x-empty-state title="No plans available"
                           message="An administrator has not published any plans yet." />
        @else
            <div class="grid gap-5 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    @php
                        $affordable = ! $wallet->deposit_balance->lessThan($plan->min_amount);
                    @endphp

                    <div id="plan-{{ $plan->id }}"
                         class="flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-ink-900/70 p-6 transition hover:border-brand-500/30">
                        @if ($plan->imageUrl())
                            <div class="mb-4 overflow-hidden rounded-xl border border-white/10 bg-ink-950/50">
                                <img src="{{ $plan->imageUrl() }}" alt="{{ $plan->name }}" class="h-44 w-full object-cover transition duration-300 hover:scale-105">
                            </div>
                        @endif

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
                                    Needs {{ $plan->min_amount->formatWithSymbol() }} in deposit balance <a href="{{ route('deposits.create') }}">Fund</a>
                                </p>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
