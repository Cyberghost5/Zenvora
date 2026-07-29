<x-layouts.app title="Dashboard"
               :heading="'Welcome back, '.str(auth()->user()->name)->before(' ')"
               subheading="Here's where your money stands today.">

    <x-slot:actions>
        <a href="{{ route('deposits.create') }}" class="btn-primary">Fund wallet</a>
        <a href="{{ route('withdrawals.index') }}" class="btn-ghost">Withdraw</a>
    </x-slot:actions>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Balances                                                         --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Deposit balance"
                :value="$wallet->deposit_balance->formatWithSymbol()"
                hint="Available to invest. Not withdrawable."
                tone="brand" />

        <x-stat label="Withdrawable balance"
                :value="$wallet->withdrawable_balance->formatWithSymbol()"
                hint="Returns, commissions and returned capital."
                tone="positive" />

        <x-stat label="Active investments"
                :value="$activeInvestments->count()"
                :hint="$activeInvestments->isEmpty()
                    ? 'No plans running yet.'
                    : $activeInvestments->reduce(fn ($carry, $i) => $carry->add($i->principal), \App\Support\Money::zero())->formatWithSymbol().' at work'" />

        <x-stat label="Total earned"
                :value="$wallet->total_roi_earned->add($wallet->total_referral_earned)->formatWithSymbol()"
                :hint="$wallet->total_referral_earned->formatWithSymbol().' from referrals'"
                tone="positive" />
    </div>

    {{-- Held funds are worth calling out plainly, since they leave the
         withdrawable figure looking lower than the user expects. --}}
    @if ($wallet->locked_balance->isPositive())
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            <span class="tabular font-semibold">{{ $wallet->locked_balance->formatWithSymbol() }}</span>
            <span>is held against {{ $pendingWithdrawals }} withdrawal {{ str('request')->plural($pendingWithdrawals) }} awaiting review.</span>
            <a href="{{ route('withdrawals.index') }}" class="ml-auto font-medium underline hover:text-amber-100">View requests</a>
        </div>
    @endif

    @if ($pendingDeposits > 0)
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-white/10 bg-ink-900/60 px-4 py-3 text-sm text-slate-300">
            <span>You have {{ $pendingDeposits }} {{ str('deposit')->plural($pendingDeposits) }} still being confirmed.</span>
            <a href="{{ route('deposits.index') }}" class="ml-auto font-medium text-brand-400 hover:text-brand-300">Check status</a>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Active investments                                               --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-8">
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 class="text-lg font-semibold text-white">Running investments</h2>
            @if ($activeInvestments->isNotEmpty())
                <a href="{{ route('investments.index') }}" class="text-sm text-brand-400 hover:text-brand-300">View all</a>
            @endif
        </div>

        @if ($activeInvestments->isEmpty())
            <x-empty-state title="Nothing invested yet"
                           message="Fund your wallet and choose a plan to start earning a daily return.">
                @if ($wallet->deposit_balance->isPositive())
                    <a href="{{ route('investments.index') }}" class="btn-primary">Choose a plan</a>
                @else
                    <a href="{{ route('deposits.create') }}" class="btn-primary">Fund your wallet</a>
                @endif
            </x-empty-state>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($activeInvestments as $investment)
                    <a href="{{ route('investments.show', $investment) }}"
                       class="card transition hover:border-white/20 hover:bg-ink-900">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold text-white">{{ $investment->plan->name ?? 'Plan' }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $investment->reference }}</p>
                            </div>
                            <span class="pill bg-brand-500/10 text-brand-300 ring-brand-500/20">
                                {{ $investment->dailyRoiLabel() }} / day
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-slate-500">Principal</dt>
                                <dd class="tabular mt-0.5 font-semibold text-white">{{ $investment->principal->formatWithSymbol() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Paid so far</dt>
                                <dd class="tabular mt-0.5 font-semibold text-emerald-400">{{ $investment->total_roi_paid->formatWithSymbol() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Still to come</dt>
                                <dd class="tabular mt-0.5 font-semibold text-slate-300">{{ $investment->outstandingRoi()->formatWithSymbol() }}</dd>
                            </div>
                        </dl>

                        {{-- Progress by days paid, not by calendar date: a missed
                             accrual run must not look like completed progress. --}}
                        <div class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-xs text-slate-500">
                                <span>Day {{ $investment->days_paid }} of {{ $investment->duration_days }}</span>
                                <span>Matures {{ $investment->matures_on->format('j M Y') }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-brand-500 transition-all"
                                     style="width: {{ $investment->progressPercent() }}%"
                                     role="progressbar"
                                     aria-valuenow="{{ $investment->progressPercent() }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100"
                                     aria-label="Investment progress"></div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- Available plans                                              --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="lg:col-span-2">
            <div class="mb-4 flex items-end justify-between gap-4">
                <h2 class="text-lg font-semibold text-white">Available plans</h2>
                <a href="{{ route('investments.index') }}" class="text-sm text-brand-400 hover:text-brand-300">Invest</a>
            </div>

            @if ($plans->isEmpty())
                <x-empty-state title="No plans published"
                               message="Plans will appear here once an administrator publishes them." />
            @else
                <div class="overflow-hidden rounded-2xl border border-white/10">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium">Plan</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Amount</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Daily</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Term</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Total Return</th>
                                    <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach ($plans as $plan)
                                    <tr class="bg-ink-900/40">
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-white">{{ $plan->name }}</p>
                                            @if ($plan->return_capital)
                                                <p class="text-xs text-emerald-400">Capital returned</p>
                                            @endif
                                        </td>
                                        <td class="tabular px-4 py-3 whitespace-nowrap text-slate-400">
                                            {{ $plan->min_amount->formatWithSymbol() }}
                                        </td>
                                        <td class="tabular px-4 py-3 font-semibold text-brand-300">{{ $plan->dailyPayoutFor($plan->min_amount)->formatWithSymbol() }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-300">{{ $plan->durationLabel() }}</td>
                                        <td class="tabular px-4 py-3 font-semibold text-brand-300">{{ $plan->totalReturnFor($plan->min_amount)->formatWithSymbol() }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('investments.index') }}#plan-{{ $plan->id }}"
                                               class="text-brand-400 hover:text-brand-300">Invest</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Referrals + withdrawal window                                --}}
        {{-- ------------------------------------------------------------ --}}
        <div class="space-y-6">
            <section class="card">
                <h2 class="font-semibold text-white">Your referral code</h2>

                <div class="mt-3 flex items-center gap-2">
                    <code class="flex-1 truncate rounded-lg bg-ink-950/60 px-3 py-2 font-mono text-sm text-brand-300">
                        {{ auth()->user()->referral_code }}
                    </code>
                    <button type="button"
                            class="btn-ghost !px-3 !py-2 text-xs"
                            data-copy="{{ auth()->user()->referralLink() }}">
                        Copy link
                    </button>
                </div>

                <dl class="mt-4 space-y-2 border-t border-white/5 pt-4 text-sm">
                    @foreach ($referralTiers as $tier)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-400">
                                Tier {{ $tier['tier'] }}
                                <span class="tabular text-xs text-slate-500">
                                    ({{ $referralCounts[$tier['tier']] ?? 0 }} {{ str('person')->plural($referralCounts[$tier['tier']] ?? 0) }})
                                </span>
                            </dt>
                            <dd class="tabular font-semibold text-brand-300">{{ $tier['label'] }}</dd>
                        </div>
                    @endforeach
                </dl>

                <a href="{{ route('referrals') }}" class="btn-ghost mt-4 w-full">Referral dashboard</a>
            </section>

            <section class="card">
                <h2 class="font-semibold text-white">Withdrawal window</h2>

                @if ($window->isOpen())
                    <p class="mt-2 flex items-center gap-2 text-sm text-emerald-400">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Open now
                    </p>
                @else
                    <p class="mt-2 flex items-center gap-2 text-sm text-amber-400">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        Closed
                    </p>
                @endif

                <p class="mt-2 text-sm text-slate-400">{{ $window->summary() }}</p>

                @if ($reason = $window->closedReason())
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $reason }}</p>
                @endif

                <dl class="mt-4 space-y-2 border-t border-white/5 pt-4 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Minimum</dt>
                        <dd class="tabular font-medium text-white">{{ $withdrawalMin->formatWithSymbol() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Maximum</dt>
                        <dd class="tabular font-medium text-white">{{ $withdrawalMax->formatWithSymbol() }}</dd>
                    </div>
                </dl>

                <a href="{{ route('withdrawals.index') }}" class="btn-ghost mt-4 w-full">Request a withdrawal</a>
            </section>
        </div>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Recent activity                                                  --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-8">
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 class="text-lg font-semibold text-white">Recent activity</h2>
            <a href="{{ route('transactions') }}" class="text-sm text-brand-400 hover:text-brand-300">All transactions</a>
        </div>

        @if ($recentTransactions->isEmpty())
            <x-empty-state title="No activity yet"
                           message="Deposits, investments and returns will appear here." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <ul class="divide-y divide-white/5">
                    @foreach ($recentTransactions as $transaction)
                        <li class="flex items-center gap-4 bg-ink-900/40 px-4 py-3">
                            <span @class([
                                'grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-semibold',
                                'bg-emerald-500/15 text-emerald-400' => $transaction->isCredit(),
                                'bg-slate-500/15 text-slate-400' => ! $transaction->isCredit(),
                            ])>
                                {{ $transaction->isCredit() ? '+' : '−' }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-white">{{ $transaction->label() }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $transaction->created_at->format('j M Y, H:i') }}
                                    @if ($transaction->description)
                                        &middot; {{ $transaction->description }}
                                    @endif
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <p @class([
                                    'tabular text-sm font-semibold',
                                    'text-emerald-400' => $transaction->isCredit(),
                                    'text-slate-300' => ! $transaction->isCredit(),
                                ])>
                                    {{ $transaction->signedAmount() }}
                                </p>
                                <p class="text-xs text-slate-500 capitalize">{{ $transaction->bucket }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</x-layouts.app>
