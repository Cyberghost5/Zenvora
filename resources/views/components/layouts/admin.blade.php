@props(['title' => null, 'heading' => null, 'subheading' => null])

@php
    use App\Models\Deposit;
    use App\Models\Withdrawal;

    $user = auth()->user();

    // Queue depths in the nav, so an admin sees what needs attention without
    // opening each section.
    $depositQueue = Deposit::query()->awaitingReview()->count();
    $withdrawalQueue = Withdrawal::query()->pending()->count();

    $nav = [
        ['route' => 'admin.dashboard', 'label' => 'Overview', 'match' => 'admin.dashboard'],
        ['route' => 'admin.deposits.index', 'label' => 'Deposits', 'match' => 'admin.deposits.*', 'badge' => $depositQueue],
        ['route' => 'admin.withdrawals.index', 'label' => 'Withdrawals', 'match' => 'admin.withdrawals.*', 'badge' => $withdrawalQueue],
        ['route' => 'admin.investments.index', 'label' => 'Investments', 'match' => 'admin.investments.*'],
        ['route' => 'admin.plans.index', 'label' => 'Plans', 'match' => 'admin.plans.*'],
        ['route' => 'admin.users.index', 'label' => 'Users', 'match' => 'admin.users.*'],
        ['route' => 'admin.coupons.index', 'label' => 'Coupons', 'match' => 'admin.coupons.*'],
        ['route' => 'admin.settings.edit', 'label' => 'Settings', 'match' => 'admin.settings.*'],
        ['route' => 'admin.audit', 'label' => 'Audit log', 'match' => 'admin.audit'],
    ];
@endphp

<x-layouts.base :title="$title ? 'Admin · '.$title : 'Admin'" :noindex="true">
    <div class="min-h-dvh lg:flex">
        <aside class="lg:fixed lg:inset-y-0 lg:left-0 lg:w-64 lg:overflow-y-auto lg:border-r lg:border-white/5 lg:bg-ink-900/40">
            <div class="flex items-center justify-between border-b border-white/5 px-4 py-4 lg:border-b-0">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2" aria-label="Admin overview">
                    <x-logo />
                </a>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />

                    <button type="button"
                            data-nav-toggle
                            aria-expanded="false"
                            aria-controls="admin-nav"
                            class="btn-ghost !px-3 !py-2 lg:hidden">
                        <span class="sr-only">Toggle navigation</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 5A.75.75 0 0 1 2.75 9h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 9.75Zm0 5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="admin-nav" data-nav-panel class="hidden border-b border-white/5 lg:block lg:border-b-0">
                <p class="mx-4 mt-4 rounded-lg bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-500/20">
                    Administrator access
                </p>

                <nav class="space-y-1 p-4" aria-label="Admin">
                    @foreach ($nav as $item)
                        <x-nav-link :href="route($item['route'])"
                                    :active="request()->routeIs($item['match'])"
                                    :badge="($item['badge'] ?? 0) ?: null">
                            {{ $item['label'] }}
                        </x-nav-link>
                    @endforeach
                </nav>

                <div class="border-t border-white/5 p-4">
                    <a href="{{ route('dashboard') }}" class="btn-ghost mb-2 w-full">My wallet</a>

                    <div class="flex items-center gap-3 rounded-xl px-1 py-2">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-amber-500/15 text-sm font-semibold text-amber-300">
                            {{ $user->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">Admin</p>
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
                <div class="mx-auto max-w-7xl">
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
        </div>
    </div>
</x-layouts.base>
