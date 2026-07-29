<x-layouts.admin :title="$withdrawal->reference"
                 heading="Process withdrawal"
                 :subheading="$withdrawal->reference">

    <x-slot:actions>
        <a href="{{ route('admin.withdrawals.index') }}" class="btn-ghost">&larr; Withdrawal queue</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-400">Send to the user</p>
                    <p class="tabular mt-1 text-3xl font-semibold text-white">
                        {{ $withdrawal->net_amount->formatWithSymbol() }}
                    </p>
                    @unless ($withdrawal->fee->isZero())
                        <p class="tabular mt-1 text-xs text-slate-500">
                            {{ $withdrawal->amount->formatWithSymbol() }} requested,
                            less {{ $withdrawal->fee->formatWithSymbol() }} fee
                        </p>
                    @endunless
                </div>
                <span class="pill {{ $withdrawal->statusTone() }}">{{ $withdrawal->statusLabel() }}</span>
            </div>

            {{-- The destination is the operative detail: an admin copies these
                 numbers into a banking app, so they are large and copyable. --}}
            <div class="mt-6 rounded-xl border border-brand-500/25 bg-brand-500/5 p-4">
                <h2 class="text-sm font-semibold text-white">Pay into this account</h2>

                <dl class="mt-3 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Bank</dt>
                        <dd class="font-semibold text-white">{{ $withdrawal->bank_name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                        <dt class="text-slate-400">Account number</dt>
                        <dd class="flex items-center gap-2">
                            <span class="tabular font-mono text-base font-semibold text-white">
                                {{ $withdrawal->account_number }}
                            </span>
                            <button type="button" class="btn-ghost !px-2 !py-1 text-xs"
                                    data-copy="{{ $withdrawal->account_number }}">Copy</button>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                        <dt class="text-slate-400">Account name</dt>
                        <dd class="font-semibold text-white">{{ $withdrawal->account_name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                        <dt class="text-slate-400">Amount to transfer</dt>
                        <dd class="flex items-center gap-2">
                            <span class="tabular font-semibold text-white">
                                {{ $withdrawal->net_amount->formatWithSymbol() }}
                            </span>
                            <button type="button" class="btn-ghost !px-2 !py-1 text-xs"
                                    data-copy="{{ number_format($withdrawal->net_amount->toMajor(), 2, '.', '') }}">Copy</button>
                        </dd>
                    </div>
                </dl>

                <p class="mt-3 text-xs text-slate-500">
                    These details were snapshotted when the request was made, so they show where the money was
                    actually meant to go even if the user has since edited their profile.
                </p>
            </div>

            <dl class="mt-6 grid gap-x-6 gap-y-3 border-t border-white/5 pt-6 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-500">User</dt>
                    <dd class="mt-0.5">
                        @if ($withdrawal->user)
                            <a href="{{ route('admin.users.show', $withdrawal->user) }}"
                               class="font-medium text-white hover:text-brand-300">{{ $withdrawal->user->name }}</a>
                            <span class="block text-xs text-slate-500">{{ $withdrawal->user->email }}</span>
                            <span class="block text-xs text-slate-500">{{ $withdrawal->user->phone }}</span>
                        @else
                            <span class="text-slate-500">Removed user</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-slate-500">Requested</dt>
                    <dd class="mt-0.5 font-medium text-white">{{ $withdrawal->created_at->format('j M Y, H:i') }}</dd>
                </div>

                @if ($withdrawal->processor)
                    <div>
                        <dt class="text-xs text-slate-500">Handled by</dt>
                        <dd class="mt-0.5 font-medium text-white">
                            {{ $withdrawal->processor->name }}
                            <span class="block text-xs text-slate-500">
                                {{ $withdrawal->processed_at?->format('j M Y, H:i') }}
                            </span>
                        </dd>
                    </div>
                @endif

                @if ($withdrawal->payment_note)
                    <div>
                        <dt class="text-xs text-slate-500">Payment note</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $withdrawal->payment_note }}</dd>
                    </div>
                @endif
            </dl>

            @if ($withdrawal->rejection_reason)
                <p class="mt-5 rounded-xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <strong class="font-semibold">Rejected:</strong> {{ $withdrawal->rejection_reason }}
                </p>
            @endif
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Actions                                                      --}}
        {{-- ------------------------------------------------------------ --}}
        <aside class="space-y-4">
            @if ($withdrawal->user?->wallet)
                @php $wallet = $withdrawal->user->wallet; @endphp

                <div class="card">
                    <h2 class="font-semibold text-white">User's wallet</h2>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-400">Withdrawable</dt>
                            <dd class="tabular font-semibold text-emerald-400">
                                {{ $wallet->withdrawable_balance->formatWithSymbol() }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">Held</dt>
                            <dd class="tabular font-semibold text-amber-400">
                                {{ $wallet->locked_balance->formatWithSymbol() }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">Paid out to date</dt>
                            <dd class="tabular font-medium text-slate-300">
                                {{ $wallet->total_withdrawn->formatWithSymbol() }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">Funded to date</dt>
                            <dd class="tabular font-medium text-slate-300">
                                {{ $wallet->total_deposited->formatWithSymbol() }}
                            </dd>
                        </div>
                    </dl>

                    {{-- Withdrawing more than was ever deposited is not fraud by
                         itself -- it is what a profitable investment looks like --
                         but it is worth an admin's eye before paying. --}}
                    @if ($wallet->total_withdrawn->add($withdrawal->amount)->greaterThan($wallet->total_deposited))
                        <p class="mt-3 rounded-lg border border-amber-500/25 bg-amber-500/10 px-3 py-2 text-xs text-amber-200">
                            Paying this brings lifetime withdrawals above lifetime deposits for this user.
                            Expected on a profitable account - worth a glance regardless.
                        </p>
                    @endif
                </div>
            @endif

            @if ($withdrawal->isOpen())
                @if ($withdrawal->status === 'pending')
                    <div class="card">
                        <h2 class="font-semibold text-white">Claim this request</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Mark it as being processed so another administrator does not pay the same request.
                        </p>

                        <form method="POST" action="{{ route('admin.withdrawals.processing', $withdrawal) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn-ghost w-full">Mark as processing</button>
                        </form>
                    </div>
                @endif

                <div class="card">
                    <h2 class="font-semibold text-white">Confirm payment sent</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Only do this after the bank transfer has actually gone out. The held funds are released
                        permanently and cannot be clawed back automatically.
                    </p>

                    <form method="POST" action="{{ route('admin.withdrawals.paid', $withdrawal) }}"
                          class="mt-4"
                          data-confirm="Confirm you have sent {{ $withdrawal->net_amount->formatWithSymbol() }} to {{ $withdrawal->account_number }}?">
                        @csrf

                        <label for="note" class="label">
                            Reference <span class="font-normal text-slate-500">(optional)</span>
                        </label>
                        <input id="note" name="note" type="text" value="{{ old('note') }}"
                               class="input" placeholder="Bank transfer reference">
                        <x-input-error for="note" />

                        <button type="submit" class="btn-primary mt-4 w-full">Mark as paid</button>
                    </form>
                </div>

                <div class="card">
                    <h2 class="font-semibold text-white">Reject and refund</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        The full {{ $withdrawal->amount->formatWithSymbol() }} returns to the user's withdrawable
                        balance.
                    </p>

                    <form method="POST" action="{{ route('admin.withdrawals.reject', $withdrawal) }}" class="mt-3">
                        @csrf

                        <label for="reason" class="label">Reason</label>
                        <textarea id="reason" name="reason" rows="3" class="input"
                                  placeholder="Shown to the user">{{ old('reason') }}</textarea>
                        <x-input-error for="reason" />

                        <button type="submit" class="btn-ghost mt-3 w-full text-rose-400 hover:!border-rose-500/40">
                            Reject and return funds
                        </button>
                    </form>
                </div>
            @else
                <div class="card">
                    <h2 class="font-semibold text-white">Settled</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        This request was {{ $withdrawal->status === 'paid' ? 'paid' : 'rejected' }}
                        @if ($withdrawal->processed_at)
                            on {{ $withdrawal->processed_at->format('j M Y, H:i') }}
                        @endif
                        and can no longer be changed.
                    </p>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.admin>
