<x-layouts.app title="My Investments"
               heading="My Running Investments"
               subheading="Track all your running active and completed investment subscriptions.">

    {{-- ---------------------------------------------------------------- --}}
    {{-- Running & Completed Investments History                          --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-8">
        <h2 class="mb-4 text-lg font-semibold text-white">Active & Completed Investments</h2>

        @if ($investments->isEmpty())
            <x-empty-state title="No active investments yet"
                           message="Choose an investment plan to start earning daily returns.">
                <a href="{{ route('plans.index') }}" class="btn-primary">Browse Plans</a>
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                                <th scope="col" class="px-4 py-3 font-medium">Plan</th>
                                <th scope="col" class="px-4 py-3 font-medium">Principal</th>
                                <th scope="col" class="px-4 py-3 font-medium">Paid</th>
                                <th scope="col" class="px-4 py-3 font-medium">Progress</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($investments as $investment)
                                <tr class="bg-ink-900/40 transition hover:bg-ink-900">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('investments.show', $investment) }}"
                                           class="font-mono text-xs text-brand-400 hover:text-brand-300">
                                            {{ $investment->reference }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-white">{{ $investment->plan->name ?? '-' }}</td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-white">{{ $investment->principal->formatWithSymbol() }}</td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-emerald-400">{{ $investment->total_roi_paid->formatWithSymbol() }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-white/10">
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $investments->links() }}</div>
        @endif
    </section>
</x-layouts.app>
