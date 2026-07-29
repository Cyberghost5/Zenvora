@props(['title' => null, 'heading' => null, 'subheading' => null])

@php
    $user = auth()->user();
    $wallet = $user->ensureWallet();

    // Counts of things the user is waiting on, surfaced in the nav so they do
    // not have to visit each page to find out.
    $pendingDeposits = $user->deposits()->whereIn('status', ['pending', 'awaiting_review'])->count();
    $pendingWithdrawals = $user->withdrawals()->pending()->count();

    $nav = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'match' => 'dashboard'],
        ['route' => 'investments.index', 'label' => 'Investments', 'match' => 'investments.*'],
        ['route' => 'deposits.index', 'label' => 'Fund wallet', 'match' => 'deposits.*', 'badge' => $pendingDeposits],
        ['route' => 'withdrawals.index', 'label' => 'Withdraw', 'match' => 'withdrawals.*', 'badge' => $pendingWithdrawals],
        ['route' => 'referrals', 'label' => 'Referrals', 'match' => 'referrals'],
        ['route' => 'transactions', 'label' => 'Transactions', 'match' => 'transactions'],
        ['route' => 'profile.edit', 'label' => 'Profile', 'match' => 'profile.*'],
    ];
@endphp

<x-layouts.base :title="$title" :noindex="true">
    <div class="min-h-dvh lg:flex">
        {{-- Sidebar: a fixed rail from lg up, a collapsing panel below that. --}}
        <aside class="lg:fixed lg:inset-y-0 lg:left-0 lg:w-64 lg:overflow-y-auto lg:border-r lg:border-white/5 lg:bg-ink-900/40">
            <div class="flex items-center justify-between border-b border-white/5 px-4 py-4 lg:border-b-0">
                <a href="{{ route('dashboard') }}" aria-label="{{ config('app.name') }} dashboard">
                    <x-logo />
                </a>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />

                    <button type="button"
                            data-nav-toggle
                            aria-expanded="false"
                            aria-controls="primary-nav"
                            class="btn-ghost !px-3 !py-2 lg:hidden">
                        <span class="sr-only">Toggle navigation</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 5A.75.75 0 0 1 2.75 9h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 9.75Zm0 5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="primary-nav" data-nav-panel class="hidden border-b border-white/5 lg:block lg:border-b-0">
                {{-- Balance summary, so the two figures are always one glance away. --}}
                <div class="mx-4 mt-4 rounded-xl border border-white/10 bg-ink-950/50 p-4">
                    <p class="text-xs text-slate-500">Withdrawable</p>
                    <p class="tabular mt-0.5 text-xl font-semibold text-emerald-400">
                        {{ $wallet->withdrawable_balance->formatWithSymbol() }}
                    </p>

                    <div class="mt-3 border-t border-white/5 pt-3">
                        <p class="text-xs text-slate-500">Deposit balance</p>
                        <p class="tabular mt-0.5 text-base font-semibold text-white">
                            {{ $wallet->deposit_balance->formatWithSymbol() }}
                        </p>
                    </div>

                    @if ($wallet->locked_balance->isPositive())
                        <div class="mt-3 border-t border-white/5 pt-3">
                            <p class="text-xs text-slate-500">Held for withdrawal</p>
                            <p class="tabular mt-0.5 text-sm font-semibold text-amber-400">
                                {{ $wallet->locked_balance->formatWithSymbol() }}
                            </p>
                        </div>
                    @endif
                </div>

                <nav class="space-y-1 p-4" aria-label="Main">
                    @foreach ($nav as $item)
                        <x-nav-link :href="route($item['route'])"
                                    :active="request()->routeIs($item['match'])"
                                    :badge="($item['badge'] ?? 0) ?: null">
                            {{ $item['label'] }}
                        </x-nav-link>
                    @endforeach
                </nav>

                <div class="border-t border-white/5 p-4">
                    @if ($user->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn-ghost mb-2 w-full">
                            Admin panel
                        </a>
                    @endif

                    <div class="flex items-center gap-3 rounded-xl px-1 py-2">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500/15 text-sm font-semibold text-brand-300">
                            {{ $user->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="btn-ghost w-full">Sign out</button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
            <main id="main" class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-6xl">
                    @if ($heading)
                        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $heading }}</h1>
                                @if ($subheading)
                                    <p class="mt-1.5 text-sm text-slate-400">{{ $subheading }}</p>
                                @endif
                            </div>

                            @isset($actions)
                                <div class="flex flex-wrap gap-3">{{ $actions }}</div>
                            @endisset
                        </div>
                    @endif

                    <x-alerts />

                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-white/5 px-4 py-5 text-xs text-slate-500 sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2">
                    <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
                    <p class="flex gap-3">
                        <a href="{{ route('terms') }}" class="hover:text-slate-300">Terms</a>
                        <a href="{{ route('privacy') }}" class="hover:text-slate-300">Privacy</a>
                    </p>
                </div>
            </footer>
        </div>
    </div>
    <x-announcement-popup />
</x-layouts.base>
