<x-layouts.app title="Withdrawals"
               heading="Withdraw funds"
               subheading="Send your withdrawable balance to your bank account.">

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Withdrawable now"
                :value="$wallet->withdrawable_balance->formatWithSymbol()"
                hint="Returns, commissions and returned capital."
                tone="positive" />

        <x-stat label="Held in review"
                :value="$wallet->locked_balance->formatWithSymbol()"
                hint="Reserved against open requests."
                :tone="$wallet->locked_balance->isPositive() ? 'warning' : 'default'" />

        <x-stat label="Paid out to date"
                :value="$wallet->total_withdrawn->formatWithSymbol()"
                hint="Lifetime total settled." />
    </div>

    {{-- Window state is stated plainly before the form, so a user is not left
         guessing why their request was refused. --}}
    <div @class([
        'mt-6 rounded-xl border px-4 py-3.5 text-sm',
        'border-emerald-500/25 bg-emerald-500/10 text-emerald-200' => $window->isOpen(),
        'border-amber-500/25 bg-amber-500/10 text-amber-200' => ! $window->isOpen(),
    ])>
        <div class="flex flex-wrap items-center gap-2">
            <span @class([
                'h-2 w-2 rounded-full',
                'bg-emerald-400' => $window->isOpen(),
                'bg-amber-400' => ! $window->isOpen(),
            ])></span>
            <span class="font-semibold text-emerald-400">
                {{ $window->isOpen() ? 'The withdrawal window is open' : 'The withdrawal window is closed' }}
            </span>
            <span class="text-xs opacity-80 text-emerald-400">&middot; {{ $window->summary() }}</span>
        </div>

        @if ($reason = $window->closedReason())
            <p class="mt-1.5 text-xs leading-relaxed opacity-90">{{ $reason }}</p>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- Request form                                                 --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card lg:col-span-2">
            <h2 class="font-semibold text-white">New request</h2>

            @if ($accounts->isEmpty())
                {{-- No payout destination means the request cannot be paid, so
                     send them to the profile rather than showing a dead form. --}}
                <div class="mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3.5 text-sm text-amber-200">
                    <p class="font-medium">Add your bank details first</p>
                    <p class="mt-1 text-xs leading-relaxed opacity-90">
                        We need somewhere to send your money. Save a payout account in your profile, then come back.
                    </p>
                    <a href="{{ route('profile.edit') }}#bank-accounts" class="btn-ghost mt-3">
                        Add bank account
                    </a>
                </div>
            @elseif ($wallet->withdrawable_balance->isZero())
                <div class="mt-4 rounded-xl border border-white/10 bg-ink-950/50 px-4 py-3.5 text-sm text-slate-400">
                    <p class="font-medium text-slate-300">Nothing to withdraw yet</p>
                    <p class="mt-1 text-xs leading-relaxed">
                        Your withdrawable balance fills up from daily returns, referral commissions and capital
                        returned at maturity. Deposits cannot be withdrawn directly.
                    </p>
                    <a href="{{ route('investments.index') }}" class="btn-ghost mt-3">Invest to start earning</a>
                </div>
            @else
                <form method="POST" action="{{ route('withdrawals.store') }}" class="mt-4 space-y-5">
                    @csrf

                    <div>
                        <label for="amount" class="label">Amount to withdraw</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                                {{ config('zenvora.currency_symbol') }}
                            </span>
                            <input id="amount"
                                   name="amount"
                                   type="number"
                                   step="0.01"
                                   min="{{ $min->toMajor() }}"
                                   max="{{ min($max->toMajor(), $wallet->withdrawable_balance->toMajor()) }}"
                                   value="{{ old('amount') }}"
                                   required
                                   class="input tabular !pl-9"
                                   placeholder="{{ number_format($min->toMajor(), 2) }}">
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">
                            Between {{ $min->formatWithSymbol() }} and {{ $max->formatWithSymbol() }}.
                            You have {{ $wallet->withdrawable_balance->formatWithSymbol() }} available.
                        </p>
                        <x-input-error for="amount" />
                    </div>

                    <div>
                        <label for="bank_account_id" class="label">Pay into</label>
                        <select id="bank_account_id" name="bank_account_id" required class="input">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}"
                                        @selected(old('bank_account_id', $accounts->firstWhere('is_primary', true)?->id) == $account->id)>
                                    {{ $account->bank_name }} - {{ $account->account_number }} ({{ $account->account_name }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error for="bank_account_id" />
                    </div>

                    <p class="rounded-xl border border-white/10 bg-ink-950/50 px-4 py-3 text-xs leading-relaxed text-slate-400">
                        The amount is held against your balance as soon as you submit, so it cannot be requested
                        twice. If a request is rejected the full amount is returned to you.
                    </p>

                    <button type="submit"
                            class="btn-primary w-full sm:w-auto"
                            @disabled(! $window->isOpen())>
                        {{ $window->isOpen() ? 'Submit request' : 'Window closed' }}
                    </button>

                    @unless ($window->isOpen())
                        <p class="text-xs text-slate-500">
                            You can prepare the form now, but it can only be submitted during the window.
                        </p>
                    @endunless
                </form>
            @endif
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Limits                                                       --}}
        {{-- ------------------------------------------------------------ --}}
        <aside class="space-y-4">
            <div class="card">
                <h2 class="font-semibold text-white">Withdrawal rules</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Minimum</dt>
                        <dd class="tabular font-semibold text-white">{{ $min->formatWithSymbol() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                        <dt class="text-slate-400">Maximum</dt>
                        <dd class="tabular font-semibold text-white">{{ $max->formatWithSymbol() }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3 border-t border-white/5 pt-2.5">
                        <dt class="text-slate-400">Days</dt>
                        <dd class="text-right font-medium text-white">{{ ucfirst($window->permittedDaysLabel()) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                        <dt class="text-slate-400">Hours</dt>
                        <dd class="tabular font-medium text-white">{{ $window->opensAt() }} – {{ $window->closesAt() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                        <dt class="text-slate-400">Timezone</dt>
                        <dd class="font-medium text-white">{{ $window->timezoneLabel() }}</dd>
                    </div>
                </dl>

                <p class="mt-4 border-t border-white/5 pt-4 text-xs leading-relaxed text-slate-500">
                    Requests are reviewed manually before payment. Limits and the window are set by the
                    administrator and can change.
                </p>
            </div>

            <div class="card">
                <h2 class="font-semibold text-white">Payout accounts</h2>

                @if ($accounts->isEmpty())
                    <p class="mt-2 text-sm text-slate-400">None saved yet.</p>
                @else
                    <ul class="mt-3 space-y-2.5 text-sm">
                        @foreach ($accounts as $account)
                            <li class="rounded-xl border border-white/10 bg-ink-950/50 px-3 py-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-medium text-white">{{ $account->bank_name }}</p>
                                    @if ($account->is_primary)
                                        <span class="pill bg-brand-500/10 text-brand-300 ring-brand-500/20">Default</span>
                                    @endif
                                </div>
                                <p class="tabular mt-0.5 font-mono text-xs text-slate-400">{{ $account->maskedNumber() }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ route('profile.edit') }}#bank-accounts" class="btn-ghost mt-4 w-full">Manage accounts</a>
            </div>
        </aside>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- History                                                          --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-white">Request history</h2>

        @if ($withdrawals->isEmpty())
            <x-empty-state title="No withdrawal requests yet"
                           message="Your requests and their outcomes will be listed here." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                                <th scope="col" class="px-4 py-3 font-medium">Amount</th>
                                <th scope="col" class="px-4 py-3 font-medium">Fee</th>
                                <th scope="col" class="px-4 py-3 font-medium">Net</th>
                                <th scope="col" class="px-4 py-3 font-medium">Destination</th>
                                <th scope="col" class="px-4 py-3 font-medium">Requested</th>
                                <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($withdrawals as $withdrawal)
                                <tr class="bg-ink-900/40">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $withdrawal->reference }}</td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap font-semibold text-white">
                                        {{ $withdrawal->amount->formatWithSymbol() }}
                                    </td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $withdrawal->fee->isZero() ? '-' : $withdrawal->fee->formatWithSymbol() }}
                                    </td>
                                    <td class="tabular px-4 py-3 whitespace-nowrap text-emerald-400">
                                        {{ $withdrawal->net_amount->formatWithSymbol() }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300">
                                        {{ $withdrawal->bank_name }}
                                        <span class="tabular block font-mono text-xs text-slate-500">
                                            {{ $withdrawal->account_number }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $withdrawal->created_at->format('j M Y, H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="pill {{ $withdrawal->statusTone() }}">{{ $withdrawal->statusLabel() }}</span>
                                        @if ($withdrawal->rejection_reason)
                                            <span class="mt-1 block max-w-xs text-xs text-rose-300">
                                                {{ $withdrawal->rejection_reason }}
                                            </span>
                                        @endif
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
</x-layouts.app>
