@php
    $tabs = [
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'all' => 'Everything',
    ];
@endphp

<x-layouts.admin title="Investments"
                 heading="Investments"
                 subheading="Every contract on the platform and how far through its term it is.">

    <x-slot:actions>
        <form method="POST" action="{{ route('admin.investments.accrue-all') }}" data-confirm="Disburse daily ROI payouts to all active investments now?">
            @csrf
            <button type="submit" class="btn-primary flex items-center gap-2">
                <i class="fa-solid fa-coins"></i> Disburse ROI Payouts Now
            </button>
        </form>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Active contracts" :value="$counts['active']" tone="brand" />
        <x-stat label="Principal at work" :value="$activePrincipal->formatWithSymbol()" />
        <x-stat label="Completed" :value="$counts['completed']" tone="positive" />
    </div>

    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filter investments">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.investments.index', array_filter(['status' => $key, 'q' => $search])) }}"
               @class([
                   'pill transition',
                   'bg-brand-500/15 text-brand-300 ring-brand-500/25' => $activeStatus === $key,
                   'bg-white/5 text-slate-400 ring-white/10 hover:text-white' => $activeStatus !== $key,
               ])>
                {{ $label }}
                @if (($counts[$key] ?? 0) > 0)
                    <span class="tabular text-xs opacity-70">{{ $counts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.investments.index') }}" class="mt-4 flex flex-wrap gap-2">
        <input type="hidden" name="status" value="{{ $activeStatus }}">
        <input type="search" name="q" value="{{ $search }}"
               placeholder="Search by reference, name or email"
               class="input max-w-sm flex-1">
        <button type="submit" class="btn-ghost">Search</button>
        @if ($search)
            <a href="{{ route('admin.investments.index', ['status' => $activeStatus]) }}" class="btn-ghost">Clear</a>
        @endif
    </form>

    <section class="mt-6">
        @if ($investments->isEmpty())
            <x-empty-state title="No investments match" message="Try a different filter." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                                <th scope="col" class="px-4 py-3 font-medium">User</th>
                                <th scope="col" class="px-4 py-3 font-medium">Plan</th>
                                <th scope="col" class="px-4 py-3 font-medium">Principal</th>
                                <th scope="col" class="px-4 py-3 font-medium">Paid</th>
                                <th scope="col" class="px-4 py-3 font-medium">Owed</th>
                                <th scope="col" class="px-4 py-3 font-medium">Progress</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($investments as $investment)
                                <tr class="bg-ink-900/40 transition hover:bg-ink-900">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $investment->reference }}</td>
                                    <td class="px-4 py-3">
                                        @if ($investment->user)
                                            <a href="{{ route('admin.users.show', $investment->user) }}"
                                               class="font-medium text-white hover:text-brand-300">
                                                {{ $investment->user->name }}
                                            </a>
                                        @else
                                            <span class="text-slate-500">Removed user</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300">
                                        {{ $investment->plan->name ?? '-' }}
                                        <span class="tabular block text-xs text-slate-500">
                                            {{ $investment->dailyRoiLabel() }}/day
                                        </span>
                                    </td>
                                    <td class="tabular px-4 py-3 font-semibold whitespace-nowrap text-white">
                                        {{ $investment->principal->formatWithSymbol() }}
                                    </td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-emerald-400">
                                        {{ $investment->total_roi_paid->formatWithSymbol() }}
                                    </td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-amber-400">
                                        {{ $investment->outstandingRoi()->formatWithSymbol() }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-16 overflow-hidden rounded-full bg-white/10">
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
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.investments.show', $investment) }}"
                                           class="btn-ghost !px-3 !py-1.5 text-xs">View</a>
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
</x-layouts.admin>
