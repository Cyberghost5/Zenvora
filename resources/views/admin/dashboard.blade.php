<x-layouts.admin title="Overview"
                 heading="Platform overview"
                 subheading="Money in, money out, and what is waiting on you.">

    {{-- Queues first: these are the things that block users. --}}
    @if ($pendingDeposits > 0 || $pendingWithdrawals > 0)
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            @if ($pendingDeposits > 0)
                <a href="{{ route('admin.deposits.index') }}"
                   class="flex items-center gap-4 rounded-2xl border border-amber-500/25 bg-amber-500/10 px-5 py-4 transition hover:bg-amber-500/15">
                    <span class="tabular grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-500/20 text-lg font-semibold text-amber-300">
                        {{ $pendingDeposits }}
                    </span>
                    <div>
                        <p class="font-semibold text-amber-100">
                            {{ str('deposit')->plural($pendingDeposits) }} awaiting review
                        </p>
                        <p class="text-xs text-amber-200/70">Users are waiting to be credited.</p>
                    </div>
                </a>
            @endif

            @if ($pendingWithdrawals > 0)
                <a href="{{ route('admin.withdrawals.index') }}"
                   class="flex items-center gap-4 rounded-2xl border border-amber-500/25 bg-amber-500/10 px-5 py-4 transition hover:bg-amber-500/15">
                    <span class="tabular grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-500/20 text-lg font-semibold text-amber-300">
                        {{ $pendingWithdrawals }}
                    </span>
                    <div>
                        <p class="font-semibold text-amber-100">
                            {{ str('withdrawal')->plural($pendingWithdrawals) }} to process
                        </p>
                        <p class="tabular text-xs text-amber-200/70">
                            {{ $pendingWithdrawalValue->formatWithSymbol() }} held pending payout.
                        </p>
                    </div>
                </a>
            @endif
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Liability -- the figure that matters most                        --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Wallet liability"
                :value="$walletLiability->formatWithSymbol()"
                hint="Everything users currently hold across all balances."
                tone="warning" />

        <x-stat label="Outstanding ROI"
                :value="$outstandingRoi->formatWithSymbol()"
                hint="Still owed on active investments."
                tone="warning" />

        <x-stat label="Total deposited"
                :value="$totalDeposited->formatWithSymbol()"
                hint="Lifetime funds received." />

        <x-stat label="Total withdrawn"
                :value="$totalWithdrawn->formatWithSymbol()"
                hint="Lifetime funds paid out." />
    </div>

    <p class="mt-4 rounded-xl border border-white/10 bg-ink-900/60 px-4 py-3 text-xs leading-relaxed text-slate-400">
        <strong class="font-semibold text-slate-300">Wallet liability</strong> plus
        <strong class="font-semibold text-slate-300">outstanding ROI</strong> is what the platform currently owes
        its users: <span class="tabular font-semibold text-white">{{ $walletLiability->add($outstandingRoi)->formatWithSymbol() }}</span>.
        Keep this against the funds you actually hold.
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Users" :value="$userCount"
                :hint="$blockedCount > 0 ? $blockedCount.' suspended' : 'None suspended'" />

        <x-stat label="Active investments" :value="$activeInvestments"
                :hint="$activePrincipal->formatWithSymbol().' principal at work'" tone="brand" />

        <x-stat label="ROI paid out" :value="$totalRoiPaid->formatWithSymbol()"
                hint="Credited to withdrawable balances." tone="positive" />

        <x-stat label="Referral commission" :value="$totalCommissions->formatWithSymbol()"
                hint="Paid across all tiers." tone="positive" />
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Withdrawal window state                                          --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-6 card">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-white">Withdrawal window</h2>
                <p class="mt-1 flex items-center gap-2 text-sm">
                    <span @class([
                        'h-2 w-2 rounded-full',
                        'bg-emerald-400' => $window->isOpen(),
                        'bg-amber-400' => ! $window->isOpen(),
                    ])></span>
                    <span class="{{ $window->isOpen() ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $window->isOpen() ? 'Open now' : 'Closed' }}
                    </span>
                    <span class="text-slate-500">&middot; {{ $window->summary() }}</span>
                </p>
                @if ($reason = $window->closedReason())
                    <p class="mt-1.5 text-xs text-slate-500">{{ $reason }}</p>
                @endif
            </div>

            <a href="{{ route('admin.settings.edit') }}" class="btn-ghost">Change window</a>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Recent activity                                                  --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section>
            <div class="mb-3 flex items-end justify-between gap-4">
                <h2 class="text-lg font-semibold text-white">Latest deposits</h2>
                <a href="{{ route('admin.deposits.index', ['status' => 'all']) }}"
                   class="text-sm text-brand-400 hover:text-brand-300">View all</a>
            </div>

            @if ($recentDeposits->isEmpty())
                <x-empty-state title="No deposits yet" />
            @else
                <ul class="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ($recentDeposits as $deposit)
                        <li class="bg-ink-900/40">
                            <a href="{{ route('admin.deposits.show', $deposit) }}"
                               class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-900">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-white">
                                        {{ $deposit->user->name ?? 'Removed user' }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $deposit->channelLabel() }} &middot; {{ $deposit->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="tabular text-sm font-semibold text-white">
                                        {{ $deposit->amount->formatWithSymbol() }}
                                    </p>
                                    <span class="pill mt-0.5 {{ $deposit->statusTone() }}">{{ $deposit->statusLabel() }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section>
            <div class="mb-3 flex items-end justify-between gap-4">
                <h2 class="text-lg font-semibold text-white">Latest withdrawals</h2>
                <a href="{{ route('admin.withdrawals.index', ['status' => 'all']) }}"
                   class="text-sm text-brand-400 hover:text-brand-300">View all</a>
            </div>

            @if ($recentWithdrawals->isEmpty())
                <x-empty-state title="No withdrawals yet" />
            @else
                <ul class="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ($recentWithdrawals as $withdrawal)
                        <li class="bg-ink-900/40">
                            <a href="{{ route('admin.withdrawals.show', $withdrawal) }}"
                               class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-900">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-white">
                                        {{ $withdrawal->user->name ?? 'Removed user' }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $withdrawal->bank_name }} &middot; {{ $withdrawal->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="tabular text-sm font-semibold text-white">
                                        {{ $withdrawal->amount->formatWithSymbol() }}
                                    </p>
                                    <span class="pill mt-0.5 {{ $withdrawal->statusTone() }}">{{ $withdrawal->statusLabel() }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Audit trail                                                      --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-6">
        <div class="mb-3 flex items-end justify-between gap-4">
            <h2 class="text-lg font-semibold text-white">Recent admin actions</h2>
            <a href="{{ route('admin.audit') }}" class="text-sm text-brand-400 hover:text-brand-300">Full audit log</a>
        </div>

        @if ($recentAudit->isEmpty())
            <x-empty-state title="No admin actions recorded yet"
                           message="Approvals, settings changes and adjustments are all logged here." />
        @else
            <ul class="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                @foreach ($recentAudit as $log)
                    <li class="flex flex-wrap items-center gap-3 bg-ink-900/40 px-4 py-3 text-sm">
                        <span class="pill bg-white/5 text-slate-300 ring-white/10">{{ $log->action }}</span>
                        <span class="min-w-0 flex-1 truncate text-slate-300">{{ $log->description }}</span>
                        <span class="shrink-0 text-xs text-slate-500">
                            {{ $log->admin_name ?? 'System' }} &middot; {{ $log->created_at->diffForHumans() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.admin>
