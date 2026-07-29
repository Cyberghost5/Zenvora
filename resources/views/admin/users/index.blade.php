@php
    $filters = [
        'all' => 'All users',
        'investors' => 'Investors',
        'blocked' => 'Suspended',
        'admins' => 'Administrators',
    ];
@endphp

<x-layouts.admin title="Users"
                 heading="Users"
                 subheading="Balances, networks and account status.">

    <nav class="flex flex-wrap gap-2" aria-label="Filter users">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.users.index', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $search])) }}"
               @class([
                   'pill transition',
                   'bg-brand-500/15 text-brand-300 ring-brand-500/25' => $activeFilter === $key,
                   'bg-white/5 text-slate-400 ring-white/10 hover:text-white' => $activeFilter !== $key,
               ])>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-4 flex flex-wrap gap-2">
        @if ($activeFilter !== 'all')
            <input type="hidden" name="filter" value="{{ $activeFilter }}">
        @endif
        <input type="search" name="q" value="{{ $search }}"
               placeholder="Search by name, email, phone or referral code"
               class="input max-w-sm flex-1">
        <button type="submit" class="btn-ghost">Search</button>
        @if ($search)
            <a href="{{ route('admin.users.index', array_filter(['filter' => $activeFilter === 'all' ? null : $activeFilter])) }}"
               class="btn-ghost">Clear</a>
        @endif
    </form>

    <section class="mt-6">
        @if ($users->isEmpty())
            <x-empty-state title="No users match"
                           message="Try a different search or filter." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">User</th>
                                <th scope="col" class="px-4 py-3 font-medium">Deposit</th>
                                <th scope="col" class="px-4 py-3 font-medium">Withdrawable</th>
                                <th scope="col" class="px-4 py-3 font-medium">Referrals</th>
                                <th scope="col" class="px-4 py-3 font-medium">Joined</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($users as $user)
                                <tr class="bg-ink-900/40 transition hover:bg-ink-900">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/5 text-xs font-semibold text-slate-300">
                                                {{ $user->initials() }}
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.users.show', $user) }}"
                                                   class="block truncate font-medium text-white hover:text-brand-300">
                                                    {{ $user->name }}
                                                </a>
                                                <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                                                <span class="block font-mono text-xs text-slate-600">{{ $user->referral_code }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-white">
                                        {{ $user->wallet?->deposit_balance->formatWithSymbol() ?? '-' }}
                                    </td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-emerald-400">
                                        {{ $user->wallet?->withdrawable_balance->formatWithSymbol() ?? '-' }}
                                    </td>
                                    <td class="tabular px-4 py-3 text-slate-300">{{ $user->referrals_count }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $user->created_at->format('j M Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            @if ($user->is_blocked)
                                                <span class="pill bg-rose-500/10 text-rose-400 ring-rose-500/20">Suspended</span>
                                            @else
                                                <span class="pill bg-emerald-500/10 text-emerald-400 ring-emerald-500/20">Active</span>
                                            @endif

                                            @if ($user->is_admin)
                                                <span class="pill bg-amber-500/10 text-amber-400 ring-amber-500/20">Admin</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.users.show', $user) }}"
                                           class="btn-ghost !px-3 !py-1.5 text-xs">Manage</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
