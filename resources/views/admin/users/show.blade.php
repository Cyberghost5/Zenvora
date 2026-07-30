@php $wallet = $user->wallet; @endphp

<x-layouts.admin :title="$user->name"
                 :heading="$user->name"
                 :subheading="$user->email.' · '.$user->phone">

    <x-slot:actions>
        <a href="{{ route('admin.users.index') }}" class="btn-ghost">&larr; All users</a>
    </x-slot:actions>

    @if ($user->is_blocked)
        <div class="mb-6 rounded-xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
            <strong class="font-semibold">This account is suspended.</strong>
            {{ $user->blocked_reason }}
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Balances                                                         --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Deposit balance"
                :value="$wallet?->deposit_balance->formatWithSymbol() ?? '-'"
                tone="brand" />

        <x-stat label="Withdrawable"
                :value="$wallet?->withdrawable_balance->formatWithSymbol() ?? '-'"
                tone="positive" />

        <x-stat label="Held for withdrawal"
                :value="$wallet?->locked_balance->formatWithSymbol() ?? '-'"
                :tone="$wallet?->locked_balance->isPositive() ? 'warning' : 'default'" />

        <x-stat label="Total held"
                :value="$wallet?->totalBalance()->formatWithSymbol() ?? '-'"
                hint="Across all three buckets." />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Deposited" :value="$wallet?->total_deposited->formatWithSymbol() ?? '-'" />
        <x-stat label="Withdrawn" :value="$wallet?->total_withdrawn->formatWithSymbol() ?? '-'" />
        <x-stat label="ROI earned" :value="$wallet?->total_roi_earned->formatWithSymbol() ?? '-'" tone="positive" />
        <x-stat label="Commission earned" :value="$wallet?->total_referral_earned->formatWithSymbol() ?? '-'" tone="positive" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- Account facts                                                --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card">
            <h2 class="font-semibold text-white">Account</h2>

            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-400">Referral code</dt>
                    <dd class="font-mono text-brand-300">{{ $user->referral_code }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Referred by</dt>
                    <dd class="text-right font-medium text-white">
                        @if ($user->referrer)
                            <a href="{{ route('admin.users.show', $user->referrer) }}" class="hover:text-brand-300">
                                {{ $user->referrer->name }}
                            </a>
                        @else
                            <span class="text-slate-500">Nobody</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Joined</dt>
                    <dd class="font-medium text-white">{{ $user->created_at->format('j M Y') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Last sign-in</dt>
                    <dd class="text-right font-medium text-white">
                        {{ $user->last_login_at?->format('j M Y, H:i') ?? 'Never' }}
                        @if ($user->last_login_ip)
                            <span class="block font-mono text-xs text-slate-500">{{ $user->last_login_ip }}</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Email verified</dt>
                    <dd class="font-medium {{ $user->email_verified_at ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $user->email_verified_at ? 'Yes' : 'No' }}
                    </dd>
                </div>
            </dl>

            <h3 class="mt-5 border-t border-white/5 pt-5 text-sm font-semibold text-white flex items-center justify-between">
                <span>Payout Account</span>
                <span class="pill bg-brand-500/10 text-brand-300 ring-brand-500/20 text-[10px]">Admin Editable</span>
            </h3>

            @php
                $primaryAccount = $user->bankAccounts->first();
            @endphp

            @if ($primaryAccount)
                <div class="mt-2 rounded-lg bg-ink-950/50 p-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-white">{{ $primaryAccount->bank_name }}</span>
                        <span class="pill bg-emerald-500/10 text-emerald-400 ring-emerald-500/20 text-[10px]">Active</span>
                    </div>
                    <span class="tabular block font-mono text-sm text-brand-300 font-semibold mt-0.5">{{ $primaryAccount->account_number }}</span>
                    <span class="block text-xs text-slate-400 mt-0.5">{{ $primaryAccount->account_name }}</span>
                </div>
            @else
                <p class="mt-2 text-xs text-slate-500">No bank account set by user yet.</p>
            @endif

            <form method="POST" action="{{ route('admin.users.bank-account', $user) }}" class="mt-4 space-y-3 border-t border-white/5 pt-3">
                @csrf
                <p class="text-xs font-medium text-slate-300">{{ $primaryAccount ? 'Update Bank Account Details' : 'Set Bank Account for User' }}</p>

                <div>
                    <label for="admin_bank_name" class="label text-xs">Bank name</label>
                    <input id="admin_bank_name" name="bank_name" type="text"
                           value="{{ old('bank_name', $primaryAccount?->bank_name) }}"
                           required class="input !py-1.5 text-xs" placeholder="e.g. Access Bank">
                    <x-input-error for="bank_name" />
                </div>

                <div>
                    <label for="admin_account_number" class="label text-xs">Account number</label>
                    <input id="admin_account_number" name="account_number" type="text" inputmode="numeric"
                           value="{{ old('account_number', $primaryAccount?->account_number) }}"
                           required class="input tabular font-mono !py-1.5 text-xs" placeholder="0123456789">
                    <x-input-error for="account_number" />
                </div>

                <div>
                    <label for="admin_account_name" class="label text-xs">Account name</label>
                    <input id="admin_account_name" name="account_name" type="text"
                           value="{{ old('account_name', $primaryAccount?->account_name) }}"
                           required class="input !py-1.5 text-xs" placeholder="Full Account Name">
                    <x-input-error for="account_name" />
                </div>

                <button type="submit" class="btn-ghost !px-3 !py-1.5 text-xs w-full">
                    Save Bank Details
                </button>
            </form>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Admin actions                                                --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="space-y-4 lg:col-span-2">
            {{-- Wallet adjustment. The bucket choice is spelt out because
                 crediting `withdrawable` creates immediately cashable money. --}}
            <div class="card">
                <h2 class="font-semibold text-white">Adjust wallet</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Manual correction. Every adjustment is written to the ledger and the audit log.
                </p>

                <form method="POST" action="{{ route('admin.users.adjust-wallet', $user) }}"
                      class="mt-4 space-y-4"
                      data-confirm="Apply this wallet adjustment? It changes a real balance.">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="amount" class="label">Amount</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                                    {{ config('zenvora.currency_symbol') }}
                                </span>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                       value="{{ old('amount') }}" required class="input tabular !pl-9">
                            </div>
                            <x-input-error for="amount" />
                        </div>

                        <div>
                            <label for="direction" class="label">Direction</label>
                            <select id="direction" name="direction" required class="input">
                                <option value="credit" @selected(old('direction') === 'credit')>Credit (add)</option>
                                <option value="debit" @selected(old('direction') === 'debit')>Debit (remove)</option>
                            </select>
                            <x-input-error for="direction" />
                        </div>

                        <div>
                            <label for="bucket" class="label">Balance</label>
                            <select id="bucket" name="bucket" required class="input">
                                <option value="deposit" @selected(old('bucket') === 'deposit')>Deposit (investable)</option>
                                <option value="withdrawable" @selected(old('bucket') === 'withdrawable')>Withdrawable (cashable)</option>
                            </select>
                            <x-input-error for="bucket" />
                        </div>
                    </div>

                    <div>
                        <label for="reason" class="label">Reason</label>
                        <input id="reason" name="reason" type="text" value="{{ old('reason') }}"
                               required class="input"
                               placeholder="e.g. Goodwill credit for failed deposit DEP-XXXX">
                        <x-input-error for="reason" />
                    </div>

                    <p class="rounded-xl border border-amber-500/25 bg-amber-500/10 px-3 py-2.5 text-xs leading-relaxed text-amber-200">
                        Crediting the <strong class="font-semibold">withdrawable</strong> balance creates money the
                        user can cash out immediately. Credit <strong class="font-semibold">deposit</strong> instead
                        if it should be invested first.
                    </p>

                    <button type="submit" class="btn-primary">Apply adjustment</button>
                </form>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                {{-- Suspension --}}
                <div class="card">
                    <h2 class="font-semibold text-white">Account access</h2>

                    @if ($user->is_blocked)
                        <p class="mt-2 text-sm text-slate-400">
                            Suspended: {{ $user->blocked_reason }}
                        </p>

                        <form method="POST" action="{{ route('admin.users.unblock', $user) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn-ghost w-full">Restore access</button>
                        </form>
                    @else
                        <p class="mt-2 text-sm text-slate-400">
                            Suspending ends their session on the next page load and blocks sign-in.
                        </p>

                        <form method="POST" action="{{ route('admin.users.block', $user) }}" class="mt-3">
                            @csrf
                            <label for="block_reason" class="label">Reason</label>
                            <input id="block_reason" name="reason" type="text" value="{{ old('reason') }}"
                                   class="input" placeholder="Shown to the user at sign-in">
                            <x-input-error for="reason" />

                            <button type="submit" class="btn-ghost mt-3 w-full text-rose-400 hover:!border-rose-500/40">
                                Suspend account
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Admin rights --}}
                <div class="card">
                    <h2 class="font-semibold text-white">Administrator rights</h2>
                    <p class="mt-2 text-sm text-slate-400">
                        {{ $user->is_admin
                            ? 'This user can approve money movements and change platform settings.'
                            : 'Grant full access to the admin panel, including approving deposits and withdrawals.' }}
                    </p>

                    <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}"
                          class="mt-3"
                          data-confirm="{{ $user->is_admin
                              ? 'Revoke admin access from '.$user->name.'?'
                              : 'Grant '.$user->name.' full administrator access?' }}">
                        @csrf
                        <button type="submit" class="btn-ghost w-full">
                            {{ $user->is_admin ? 'Revoke admin access' : 'Make administrator' }}
                        </button>
                    </form>

                    <x-input-error for="user" />
                </div>
            </div>

            {{-- Password Reset --}}
            <div class="card">
                <h2 class="font-semibold text-white">Reset User Password</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Automatically generate a new random password or type a custom password for this user.
                </p>

                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                      class="mt-4 space-y-3"
                      data-confirm="Reset password for {{ $user->name }}?">
                    @csrf

                    <div class="grid gap-3 sm:grid-cols-3 sm:items-end">
                        <div class="sm:col-span-2">
                            <label for="custom_password" class="label">Custom Password <span class="font-normal text-slate-500">(optional)</span></label>
                            <input id="custom_password" name="password" type="text"
                                   class="input font-mono" placeholder="Leave blank to auto-generate">
                            <x-input-error for="password" />
                        </div>

                        <button type="submit" class="btn-primary w-full">
                            Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Activity                                                         --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 text-lg font-semibold text-white">Investments</h2>

            @if ($investments->isEmpty())
                <x-empty-state title="No investments" />
            @else
                <ul class="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ($investments as $investment)
                        <li class="bg-ink-900/40">
                            <a href="{{ route('admin.investments.show', $investment) }}"
                               class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-900">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-white">
                                        {{ $investment->plan->name ?? 'Plan' }}
                                    </p>
                                    <p class="truncate font-mono text-xs text-slate-500">{{ $investment->reference }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="tabular text-sm font-semibold text-white">
                                        {{ $investment->principal->formatWithSymbol() }}
                                    </p>
                                    <p class="tabular text-xs text-slate-500">
                                        {{ $investment->days_paid }}/{{ $investment->duration_days }} days
                                    </p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-white">Deposits</h2>

            @if ($deposits->isEmpty())
                <x-empty-state title="No deposits" />
            @else
                <ul class="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ($deposits as $deposit)
                        <li class="bg-ink-900/40">
                            <a href="{{ route('admin.deposits.show', $deposit) }}"
                               class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-900">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-white">{{ $deposit->channelLabel() }}</p>
                                    <p class="truncate font-mono text-xs text-slate-500">{{ $deposit->reference }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="tabular text-sm font-semibold text-white">
                                        {{ $deposit->amount->formatWithSymbol() }}
                                    </p>
                                    <span class="pill mt-0.5 {{ $deposit->statusTone() }}">{{ $deposit->statusLabel() }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-white">Withdrawals</h2>

            @if ($withdrawals->isEmpty())
                <x-empty-state title="No withdrawals" />
            @else
                <ul class="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ($withdrawals as $withdrawal)
                        <li class="bg-ink-900/40">
                            <a href="{{ route('admin.withdrawals.show', $withdrawal) }}"
                               class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-900">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-white">{{ $withdrawal->bank_name }}</p>
                                    <p class="truncate font-mono text-xs text-slate-500">{{ $withdrawal->reference }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="tabular text-sm font-semibold text-white">
                                        {{ $withdrawal->amount->formatWithSymbol() }}
                                    </p>
                                    <span class="pill mt-0.5 {{ $withdrawal->statusTone() }}">{{ $withdrawal->statusLabel() }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-white">Direct referrals</h2>

            @if ($directReferrals->isEmpty())
                <x-empty-state title="No referrals" />
            @else
                <ul class="divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ($directReferrals as $referral)
                        <li class="bg-ink-900/40">
                            <a href="{{ route('admin.users.show', $referral) }}"
                               class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-900">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white/5 text-xs font-semibold text-slate-300">
                                    {{ $referral->initials() }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-white">{{ $referral->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $referral->email }}</p>
                                </div>
                                <span class="shrink-0 text-xs text-slate-500">
                                    {{ $referral->created_at->format('j M Y') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Ledger                                                           --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold text-white">Wallet ledger</h2>

        @if ($transactions->isEmpty())
            <x-empty-state title="No wallet activity" />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Date</th>
                                <th scope="col" class="px-4 py-3 font-medium">Type</th>
                                <th scope="col" class="px-4 py-3 font-medium">Bucket</th>
                                <th scope="col" class="px-4 py-3 font-medium">Details</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Before</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">After</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($transactions as $transaction)
                                <tr class="bg-ink-900/40">
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $transaction->created_at->format('j M, H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-white">{{ $transaction->label() }}</td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap text-slate-400 capitalize">
                                        {{ $transaction->bucket }}
                                    </td>
                                    <td class="max-w-xs px-4 py-3">
                                        <p class="truncate text-slate-400">{{ $transaction->description ?: '-' }}</p>
                                        <p class="font-mono text-xs text-slate-600">{{ $transaction->reference }}</p>
                                    </td>
                                    <td @class([
                                        'tabular px-4 py-3 text-right font-semibold whitespace-nowrap',
                                        'text-emerald-400' => $transaction->isCredit(),
                                        'text-slate-300' => ! $transaction->isCredit(),
                                    ])>
                                        {{ $transaction->signedAmount() }}
                                    </td>
                                    <td class="tabular px-4 py-3 text-right whitespace-nowrap text-slate-500">
                                        {{ $transaction->balance_before->format() }}
                                    </td>
                                    <td class="tabular px-4 py-3 text-right whitespace-nowrap text-slate-400">
                                        {{ $transaction->balance_after->format() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
</x-layouts.admin>
