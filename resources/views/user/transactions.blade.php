<x-layouts.app title="Transactions"
               heading="Transactions"
               subheading="Every movement in and out of your wallet, with the balance either side.">

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Deposit balance" :value="$wallet->deposit_balance->formatWithSymbol()" tone="brand" />
        <x-stat label="Withdrawable balance" :value="$wallet->withdrawable_balance->formatWithSymbol()" tone="positive" />
        <x-stat label="Held" :value="$wallet->locked_balance->formatWithSymbol()"
                :tone="$wallet->locked_balance->isPositive() ? 'warning' : 'default'" />
    </div>

    {{-- Filters as links rather than a form, so each view is bookmarkable. --}}
    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filter transactions">
        @foreach ($types as $key => $label)
            <a href="{{ $key === 'all' ? route('transactions') : route('transactions', ['type' => $key]) }}"
               @class([
                   'pill transition',
                   'bg-brand-500/15 text-brand-300 ring-brand-500/25' => $activeType === $key,
                   'bg-white/5 text-slate-400 ring-white/10 hover:text-white' => $activeType !== $key,
               ])
               @if ($activeType === $key) aria-current="page" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <section class="mt-6">
        @if ($transactions->isEmpty())
            <x-empty-state title="Nothing to show"
                           :message="$activeType === 'all'
                               ? 'Your wallet activity will appear here once you fund it.'
                               : 'No transactions of this type yet.'">
                @if ($activeType !== 'all')
                    <a href="{{ route('transactions') }}" class="btn-ghost">Clear filter</a>
                @else
                    <a href="{{ route('deposits.create') }}" class="btn-primary">Fund wallet</a>
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Date</th>
                                <th scope="col" class="px-4 py-3 font-medium">Type</th>
                                <th scope="col" class="px-4 py-3 font-medium">Details</th>
                                <th scope="col" class="px-4 py-3 font-medium">Bucket</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Balance after</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($transactions as $transaction)
                                <tr class="bg-ink-900/40">
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $transaction->created_at->format('j M Y') }}
                                        <span class="tabular block text-xs text-slate-600">
                                            {{ $transaction->created_at->format('H:i') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-white">
                                        {{ $transaction->label() }}
                                    </td>
                                    <td class="max-w-xs px-4 py-3">
                                        <p class="truncate text-slate-400">{{ $transaction->description ?: '-' }}</p>
                                        <p class="font-mono text-xs text-slate-600">{{ $transaction->reference }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'pill',
                                            'bg-brand-500/10 text-brand-300 ring-brand-500/20' => $transaction->bucket === 'deposit',
                                            'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $transaction->bucket === 'withdrawable',
                                            'bg-amber-500/10 text-amber-400 ring-amber-500/20' => $transaction->bucket === 'locked',
                                        ])>
                                            {{ ucfirst($transaction->bucket) }}
                                        </span>
                                    </td>
                                    <td @class([
                                        'tabular px-4 py-3 text-right font-semibold whitespace-nowrap',
                                        'text-emerald-400' => $transaction->isCredit(),
                                        'text-slate-300' => ! $transaction->isCredit(),
                                    ])>
                                        {{ $transaction->signedAmount() }}
                                    </td>
                                    {{-- The recorded balance after the write, which is what makes
                                         the ledger auditable rather than merely descriptive. --}}
                                    <td class="tabular px-4 py-3 text-right whitespace-nowrap text-slate-400">
                                        {{ $transaction->balance_after->formatWithSymbol() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $transactions->links() }}</div>
        @endif
    </section>
</x-layouts.app>
