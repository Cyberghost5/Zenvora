@php
    $tabs = [
        'awaiting_review' => 'Awaiting review',
        'pending' => 'Awaiting payment',
        'successful' => 'Credited',
        'failed' => 'Failed',
        'all' => 'Everything',
    ];
@endphp

<x-layouts.admin title="Deposits"
                 heading="Deposits"
                 subheading="Confirm manual transfers and re-check gateway payments.">

    <nav class="flex flex-wrap gap-2" aria-label="Filter deposits">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.deposits.index', array_filter(['status' => $key, 'q' => $search])) }}"
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

    <form method="GET" action="{{ route('admin.deposits.index') }}" class="mt-4 flex flex-wrap gap-2">
        <input type="hidden" name="status" value="{{ $activeStatus }}">
        <input type="search" name="q" value="{{ $search }}"
               placeholder="Search by reference, name or email"
               class="input max-w-sm flex-1">
        <button type="submit" class="btn-ghost">Search</button>
        @if ($search)
            <a href="{{ route('admin.deposits.index', ['status' => $activeStatus]) }}" class="btn-ghost">Clear</a>
        @endif
    </form>

    <section class="mt-6">
        @if ($deposits->isEmpty())
            <x-empty-state title="Nothing here"
                           :message="$activeStatus === 'awaiting_review'
                               ? 'No deposits are waiting for review. Nothing to do.'
                               : 'No deposits match this filter.'" />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                                <th scope="col" class="px-4 py-3 font-medium">User</th>
                                <th scope="col" class="px-4 py-3 font-medium">Method</th>
                                <th scope="col" class="px-4 py-3 font-medium">Amount</th>
                                <th scope="col" class="px-4 py-3 font-medium">Submitted</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($deposits as $deposit)
                                <tr class="bg-ink-900/40 transition hover:bg-ink-900">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $deposit->reference }}</td>
                                    <td class="px-4 py-3">
                                        @if ($deposit->user)
                                            <a href="{{ route('admin.users.show', $deposit->user) }}"
                                               class="font-medium text-white hover:text-brand-300">
                                                {{ $deposit->user->name }}
                                            </a>
                                            <span class="block truncate text-xs text-slate-500">{{ $deposit->user->email }}</span>
                                        @else
                                            <span class="text-slate-500">Removed user</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300">{{ $deposit->channelLabel() }}</td>
                                    <td class="tabular px-4 py-3 font-semibold whitespace-nowrap text-white">
                                        {{ $deposit->amount->formatWithSymbol() }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $deposit->created_at->format('j M, H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="pill {{ $deposit->statusTone() }}">{{ $deposit->statusLabel() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.deposits.show', $deposit) }}"
                                           class="btn-ghost !px-3 !py-1.5 text-xs">
                                            {{ $deposit->isPending() ? 'Review' : 'View' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $deposits->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
