<x-layouts.app title="Deposits"
               heading="Deposits"
               subheading="Every attempt to fund your wallet, with its current status.">

    <x-slot:actions>
        <a href="{{ route('deposits.create') }}" class="btn-primary">Fund wallet</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Deposit limits"
                :value="$min->formatCompact().' – '.$max->formatCompact()"
                hint="Per transaction." />

        <x-stat label="Funded to date"
                :value="auth()->user()->ensureWallet()->total_deposited->formatWithSymbol()"
                hint="Total successfully credited." />

        <x-stat label="Methods available"
                :value="count($channels)"
                :hint="collect($channels)->pluck('key')->map(fn ($c) => ucfirst($c))->join(', ') ?: 'None enabled'" />
    </div>

    <section class="mt-8">
        @if ($deposits->isEmpty())
            <x-empty-state title="No deposits yet"
                           message="Fund your wallet to start investing.">
                <a href="{{ route('deposits.create') }}" class="btn-primary">Fund wallet</a>
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                                <th scope="col" class="px-4 py-3 font-medium">Method</th>
                                <th scope="col" class="px-4 py-3 font-medium">Amount</th>
                                <th scope="col" class="px-4 py-3 font-medium">Date</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($deposits as $deposit)
                                <tr class="bg-ink-900/40 transition hover:bg-ink-900">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('deposits.show', $deposit) }}"
                                           class="font-mono text-xs text-brand-400 hover:text-brand-300">
                                            {{ $deposit->reference }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300">{{ $deposit->channelLabel() }}</td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap font-semibold text-white">
                                        {{ $deposit->amount->formatWithSymbol() }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $deposit->created_at->format('j M Y, H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="pill {{ $deposit->statusTone() }}">{{ $deposit->statusLabel() }}</span>
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
</x-layouts.app>
