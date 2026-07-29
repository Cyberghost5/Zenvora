@php
    $tabs = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'paid' => 'Paid',
        'rejected' => 'Rejected',
        'all' => 'Everything',
    ];
@endphp

<x-layouts.admin title="Withdrawals"
                 heading="Withdrawals"
                 subheading="Pay out approved requests, or reject and return the funds.">

    <nav class="flex flex-wrap gap-2" aria-label="Filter withdrawals">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.withdrawals.index', array_filter(['status' => $key, 'q' => $search])) }}"
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

    <form method="GET" action="{{ route('admin.withdrawals.index') }}" class="mt-4 flex flex-wrap gap-2">
        <input type="hidden" name="status" value="{{ $activeStatus }}">
        <input type="search" name="q" value="{{ $search }}"
               placeholder="Search by reference, account number, name or email"
               class="input max-w-sm flex-1">
        <button type="submit" class="btn-ghost">Search</button>
        @if ($search)
            <a href="{{ route('admin.withdrawals.index', ['status' => $activeStatus]) }}" class="btn-ghost">Clear</a>
        @endif
    </form>

    <section class="mt-6">
        @if ($withdrawals->isEmpty())
            <x-empty-state title="Nothing here"
                           :message="$activeStatus === 'pending'
                               ? 'No withdrawal requests are waiting. Nothing to do.'
                               : 'No requests match this filter.'" />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                                <th scope="col" class="px-4 py-3 font-medium">User</th>
                                <th scope="col" class="px-4 py-3 font-medium">Amount</th>
                                <th scope="col" class="px-4 py-3 font-medium">Net to pay</th>
                                <th scope="col" class="px-4 py-3 font-medium">Destination</th>
                                <th scope="col" class="px-4 py-3 font-medium">Requested</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($withdrawals as $withdrawal)
                                <tr class="bg-ink-900/40 transition hover:bg-ink-900">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $withdrawal->reference }}</td>
                                    <td class="px-4 py-3">
                                        @if ($withdrawal->user)
                                            <a href="{{ route('admin.users.show', $withdrawal->user) }}"
                                               class="font-medium text-white hover:text-brand-300">
                                                {{ $withdrawal->user->name }}
                                            </a>
                                            <span class="block truncate text-xs text-slate-500">{{ $withdrawal->user->email }}</span>
                                        @else
                                            <span class="text-slate-500">Removed user</span>
                                        @endif
                                    </td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-slate-300">
                                        {{ $withdrawal->amount->formatWithSymbol() }}
                                    </td>
                                    <td class="tabular px-4 py-3 font-semibold whitespace-nowrap text-white">
                                        {{ $withdrawal->net_amount->formatWithSymbol() }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-slate-300">{{ $withdrawal->bank_name }}</span>
                                        <span class="tabular block font-mono text-xs text-slate-500">
                                            {{ $withdrawal->account_number }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $withdrawal->created_at->format('j M, H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="pill {{ $withdrawal->statusTone() }}">{{ $withdrawal->statusLabel() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.withdrawals.show', $withdrawal) }}"
                                           class="btn-ghost !px-3 !py-1.5 text-xs">
                                            {{ $withdrawal->isOpen() ? 'Process' : 'View' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $withdrawals->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
