<x-layouts.admin title="Plans"
                 heading="Investment plans"
                 subheading="Rates, limits and terms. Editing a plan never alters investments already running.">

    <x-slot:actions>
        <a href="{{ route('admin.plans.create') }}" class="btn-primary">New plan</a>
    </x-slot:actions>

    @if ($plans->isEmpty())
        <x-empty-state title="No plans yet"
                       message="Create a plan so users have something to invest in.">
            <a href="{{ route('admin.plans.create') }}" class="btn-primary">Create the first plan</a>
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-2xl border border-white/10">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Plan</th>
                            <th scope="col" class="px-4 py-3 font-medium">Daily</th>
                            <th scope="col" class="px-4 py-3 font-medium">Total</th>
                            <th scope="col" class="px-4 py-3 font-medium">Term</th>
                            <th scope="col" class="px-4 py-3 font-medium">Range</th>
                            <th scope="col" class="px-4 py-3 font-medium">Capital</th>
                            <th scope="col" class="px-4 py-3 font-medium">Active now</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($plans as $plan)
                            <tr class="bg-ink-900/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-white">{{ $plan->name }}</p>
                                    <p class="font-mono text-xs text-slate-500">{{ $plan->slug }}</p>
                                </td>
                                <td class="tabular px-4 py-3 font-semibold whitespace-nowrap text-brand-300">
                                    {{ $plan->dailyRoiLabel() }}
                                </td>
                                <td class="tabular px-4 py-3 whitespace-nowrap text-slate-300">{{ $plan->totalRoiLabel() }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-300">{{ $plan->duration_days }}d</td>
                                <td class="tabular px-4 py-3 whitespace-nowrap text-slate-400">
                                    {{ $plan->min_amount->formatCompact() }} – {{ $plan->max_amount->formatCompact() }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="{{ $plan->return_capital ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ $plan->return_capital ? 'Returned' : 'In payout' }}
                                    </span>
                                </td>
                                <td class="tabular px-4 py-3 text-slate-300">{{ $plan->investments_count }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'pill',
                                        'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $plan->is_active,
                                        'bg-slate-500/10 text-slate-400 ring-slate-500/20' => ! $plan->is_active,
                                    ])>
                                        {{ $plan->is_active ? 'Active' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.plans.edit', $plan) }}"
                                           class="btn-ghost !px-3 !py-1.5 text-xs">Edit</a>

                                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}"
                                              data-confirm="{{ $plan->investments_count > 0
                                                  ? 'This plan has investments, so it will be deactivated rather than deleted. Continue?'
                                                  : 'Delete the plan “'.$plan->name.'”?' }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn-ghost !px-3 !py-1.5 text-xs text-rose-400 hover:!border-rose-500/40">
                                                {{ $plan->investments_count > 0 ? 'Deactivate' : 'Delete' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 rounded-xl border border-white/10 bg-ink-900/60 px-4 py-3 text-xs leading-relaxed text-slate-400">
            A plan with investment history is deactivated instead of deleted, so historical contracts keep a
            resolvable parent record.
        </p>
    @endif
</x-layouts.admin>
