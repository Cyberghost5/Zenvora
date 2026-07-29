<x-layouts.admin :title="$investment->reference"
                 :heading="$investment->plan->name ?? 'Investment'"
                 :subheading="$investment->reference">

    <x-slot:actions>
        <a href="{{ route('admin.investments.index') }}" class="btn-ghost">&larr; All investments</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Principal" :value="$investment->principal->formatWithSymbol()" />
        <x-stat label="ROI paid" :value="$investment->total_roi_paid->formatWithSymbol()" tone="positive" />
        <x-stat label="ROI still owed" :value="$investment->outstandingRoi()->formatWithSymbol()" tone="warning" />
        <x-stat label="Total obligation"
                :value="$investment->totalReturn()->formatWithSymbol()"
                :hint="$investment->return_capital ? 'Includes capital return' : 'Capital inside payout'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-white">Contract</h2>
                <span @class([
                    'pill',
                    'bg-brand-500/10 text-brand-300 ring-brand-500/20' => $investment->status === 'active',
                    'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $investment->status === 'completed',
                    'bg-rose-500/10 text-rose-400 ring-rose-500/20' => $investment->status === 'cancelled',
                ])>
                    {{ ucfirst($investment->status) }}
                </span>
            </div>

            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-400">User</dt>
                    <dd class="text-right">
                        @if ($investment->user)
                            <a href="{{ route('admin.users.show', $investment->user) }}"
                               class="font-medium text-white hover:text-brand-300">{{ $investment->user->name }}</a>
                            <span class="block text-xs text-slate-500">{{ $investment->user->email }}</span>
                        @else
                            <span class="text-slate-500">Removed user</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Daily rate</dt>
                    <dd class="tabular font-semibold text-brand-300">{{ $investment->dailyRoiLabel() }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Daily payout</dt>
                    <dd class="tabular font-semibold text-white">{{ $investment->daily_payout->formatWithSymbol() }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Term</dt>
                    <dd class="font-medium text-white">{{ $investment->duration_days }} days</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Started</dt>
                    <dd class="font-medium text-white">{{ $investment->started_on->format('j M Y') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Matures</dt>
                    <dd class="font-medium text-white">{{ $investment->matures_on->format('j M Y') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Last accrued</dt>
                    <dd class="font-medium text-white">
                        {{ $investment->last_accrued_on?->format('j M Y') ?? 'Never' }}
                    </dd>
                </div>
            </dl>

            {{-- Surface a stalled contract explicitly: it usually means the
                 scheduler is not running, which is invisible otherwise. --}}
            @if ($investment->isActive()
                && $investment->last_accrued_on
                && $investment->last_accrued_on->lt(now()->subDays(2)->startOfDay()))
                <p class="mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-3 py-2.5 text-xs leading-relaxed text-amber-200">
                    No accrual for {{ $investment->last_accrued_on->diffInDays(now()) }} days. Check that
                    <code class="font-mono">php artisan schedule:run</code> is firing every minute.
                </p>
            @endif

            <p class="mt-4 border-t border-white/5 pt-4 text-xs leading-relaxed text-slate-500">
                These terms were snapshotted at subscription. Editing the plan does not change them.
            </p>
        </section>

        <div class="space-y-6 lg:col-span-2">
            {{-- Cancellation --}}
            @if ($investment->isActive())
                <section class="card">
                    <h2 class="font-semibold text-white">Cancel this investment</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Stops further accrual. ROI already paid stays with the user either way.
                    </p>

                    <form method="POST" action="{{ route('admin.investments.cancel', $investment) }}"
                          class="mt-4 space-y-4"
                          data-confirm="Cancel {{ $investment->reference }}? This cannot be undone.">
                        @csrf

                        <div>
                            <label for="reason" class="label">Reason</label>
                            <input id="reason" name="reason" type="text" value="{{ old('reason') }}"
                                   required class="input" placeholder="Recorded against the contract">
                            <x-input-error for="reason" />
                        </div>

                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="refund_principal" value="1" checked
                                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                            <span>
                                <span class="block text-sm font-medium text-white">
                                    Refund {{ $investment->principal->formatWithSymbol() }} to the deposit balance
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    Untick only if the principal is deliberately being withheld - record why in the
                                    reason above.
                                </span>
                            </span>
                        </label>

                        <button type="submit" class="btn-ghost text-rose-400 hover:!border-rose-500/40">
                            Cancel investment
                        </button>
                    </form>
                </section>
            @endif

            {{-- Commission this investment generated --}}
            <section class="card">
                <h2 class="font-semibold text-white">Referral commission paid</h2>

                @if ($commissions->isEmpty())
                    <p class="mt-2 text-sm text-slate-400">
                        No commission was paid on this investment.
                    </p>
                @else
                    <ul class="mt-3 divide-y divide-white/5">
                        @foreach ($commissions as $commission)
                            <li class="flex items-center gap-3 py-2.5 text-sm">
                                <span class="tabular grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-brand-500/15 text-xs font-semibold text-brand-300">
                                    {{ $commission->tier }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-white">{{ $commission->user->name ?? 'Removed user' }}</p>
                                    <p class="tabular text-xs text-slate-500">{{ $commission->rateLabel() }} commission</p>
                                </div>
                                <span class="tabular shrink-0 font-semibold text-emerald-400">
                                    {{ $commission->amount->formatWithSymbol() }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="tabular mt-3 border-t border-white/5 pt-3 text-sm">
                        <span class="text-slate-400">Total commission cost:</span>
                        <span class="font-semibold text-white">
                            {{ $commissions->reduce(fn ($c, $i) => $c->add($i->amount), \App\Support\Money::zero())->formatWithSymbol() }}
                        </span>
                    </p>
                @endif
            </section>
        </div>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Payout ledger                                                    --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold text-white">Accrual history</h2>

        @if ($payouts->isEmpty())
            <x-empty-state title="No payouts yet"
                           message="Nothing has accrued against this contract." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Day</th>
                                <th scope="col" class="px-4 py-3 font-medium">Accrual date</th>
                                <th scope="col" class="px-4 py-3 font-medium">Type</th>
                                <th scope="col" class="px-4 py-3 font-medium">Credited at</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($payouts as $payout)
                                <tr class="bg-ink-900/40">
                                    <td class="tabular px-4 py-3 text-slate-400">
                                        {{ $payout->kind === 'capital_return' ? '-' : $payout->day_index }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300">
                                        {{ $payout->accrual_date->format('j M Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'pill',
                                            'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $payout->kind === 'roi',
                                            'bg-brand-500/10 text-brand-300 ring-brand-500/20' => $payout->kind === 'capital_return',
                                        ])>
                                            {{ $payout->kind === 'roi' ? 'Daily ROI' : 'Capital' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                                        {{ $payout->created_at->format('j M Y, H:i') }}
                                    </td>
                                    <td class="tabular px-4 py-3 text-right font-semibold text-emerald-400">
                                        +{{ $payout->amount->formatWithSymbol() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $payouts->links() }}</div>
        @endif
    </section>
</x-layouts.admin>
