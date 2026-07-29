<x-layouts.admin :title="$deposit->reference"
                 heading="Review deposit"
                 :subheading="$deposit->reference.' · '.$deposit->channelLabel()">

    <x-slot:actions>
        <a href="{{ route('admin.deposits.index') }}" class="btn-ghost">&larr; Deposit queue</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- The claim                                                    --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-400">Amount claimed</p>
                    <p class="tabular mt-1 text-3xl font-semibold text-white">{{ $deposit->amount->formatWithSymbol() }}</p>
                </div>
                <span class="pill {{ $deposit->statusTone() }}">{{ $deposit->statusLabel() }}</span>
            </div>

            <dl class="mt-6 grid gap-x-6 gap-y-3 border-t border-white/5 pt-6 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-500">User</dt>
                    <dd class="mt-0.5">
                        @if ($deposit->user)
                            <a href="{{ route('admin.users.show', $deposit->user) }}"
                               class="font-medium text-white hover:text-brand-300">{{ $deposit->user->name }}</a>
                            <span class="block text-xs text-slate-500">{{ $deposit->user->email }}</span>
                            <span class="block text-xs text-slate-500">{{ $deposit->user->phone }}</span>
                        @else
                            <span class="text-slate-500">Removed user</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-slate-500">Method</dt>
                    <dd class="mt-0.5 font-medium text-white">{{ $deposit->channelLabel() }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-slate-500">Submitted</dt>
                    <dd class="mt-0.5 font-medium text-white">{{ $deposit->created_at->format('j M Y, H:i') }}</dd>
                </div>

                @if ($deposit->depositor_name)
                    <div>
                        <dt class="text-xs text-slate-500">Name on sending account</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $deposit->depositor_name }}</dd>
                    </div>
                @endif

                @if ($deposit->paid_on)
                    <div>
                        <dt class="text-xs text-slate-500">Stated transfer date</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $deposit->paid_on->format('j M Y') }}</dd>
                    </div>
                @endif

                @if ($deposit->paid_to_account)
                    <div>
                        <dt class="text-xs text-slate-500">Paid into</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $deposit->paid_to_account }}</dd>
                    </div>
                @endif

                @if ($deposit->coupon)
                    <div>
                        <dt class="text-xs text-slate-500">Coupon</dt>
                        <dd class="mt-0.5 font-mono font-medium text-white">{{ $deposit->coupon->code }}</dd>
                    </div>
                @endif

                @if ($deposit->gateway_reference)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500">Gateway reference</dt>
                        <dd class="mt-0.5 font-mono text-xs break-all text-slate-300">{{ $deposit->gateway_reference }}</dd>
                    </div>
                @endif

                @if ($deposit->reviewer)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500">Reviewed by</dt>
                        <dd class="mt-0.5 font-medium text-white">
                            {{ $deposit->reviewer->name }}
                            <span class="text-xs text-slate-500">
                                on {{ $deposit->reviewed_at?->format('j M Y, H:i') }}
                            </span>
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($deposit->rejection_reason)
                <p class="mt-5 rounded-xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <strong class="font-semibold">Note:</strong> {{ $deposit->rejection_reason }}
                </p>
            @endif

            {{-- The receipt is the whole basis for approving a manual transfer,
                 so it gets prominence rather than a buried link. --}}
            @if ($deposit->proof_path)
                <div class="mt-6 border-t border-white/5 pt-6">
                    <h2 class="text-sm font-semibold text-white">Uploaded receipt</h2>

                    @php $ext = strtolower(pathinfo($deposit->proof_path, PATHINFO_EXTENSION)); @endphp

                    @if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true))
                        <a href="{{ Storage::url($deposit->proof_path) }}" target="_blank" rel="noopener"
                           class="mt-3 block overflow-hidden rounded-xl border border-white/10">
                            <img src="{{ Storage::url($deposit->proof_path) }}"
                                 alt="Payment receipt uploaded by the user"
                                 class="max-h-96 w-full bg-ink-950 object-contain">
                        </a>
                        <p class="mt-2 text-xs text-slate-500">Click to open at full size.</p>
                    @else
                        <a href="{{ Storage::url($deposit->proof_path) }}" target="_blank" rel="noopener"
                           class="btn-ghost mt-3">Open receipt (PDF)</a>
                    @endif
                </div>
            @elseif ($deposit->channel === 'manual')
                <p class="mt-5 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                    No receipt was uploaded with this claim.
                </p>
            @endif
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Actions                                                      --}}
        {{-- ------------------------------------------------------------ --}}
        <aside class="space-y-4">
            @if ($deposit->user?->wallet)
                <div class="card">
                    <h2 class="font-semibold text-white">User's wallet</h2>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-400">Deposit</dt>
                            <dd class="tabular font-semibold text-white">
                                {{ $deposit->user->wallet->deposit_balance->formatWithSymbol() }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">Withdrawable</dt>
                            <dd class="tabular font-semibold text-emerald-400">
                                {{ $deposit->user->wallet->withdrawable_balance->formatWithSymbol() }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">Funded to date</dt>
                            <dd class="tabular font-medium text-slate-300">
                                {{ $deposit->user->wallet->total_deposited->formatWithSymbol() }}
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if ($deposit->isSuccessful())
                <div class="card">
                    <h2 class="font-semibold text-emerald-400">Already credited</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        {{ $deposit->amount->formatWithSymbol() }} was credited on
                        {{ $deposit->credited_at?->format('j M Y, H:i') }}. Crediting twice is blocked, so this
                        cannot be re-approved.
                    </p>
                </div>
            @else
                {{-- Approve, with the option to correct the amount, because a manual
                     transfer often arrives for a different figure than claimed. --}}
                <div class="card">
                    <h2 class="font-semibold text-white">Approve and credit</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Only approve once you have confirmed the money actually arrived in your account.
                    </p>

                    <form method="POST" action="{{ route('admin.deposits.approve', $deposit) }}"
                          class="mt-4"
                          data-confirm="Credit this user's wallet? This moves real money onto their balance.">
                        @csrf

                        <label for="amount" class="label">Amount to credit</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                                {{ config('zenvora.currency_symbol') }}
                            </span>
                            <input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                   value="{{ old('amount', number_format($deposit->amount->toMajor(), 2, '.', '')) }}"
                                   class="input tabular !pl-9">
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">
                            Adjust if the amount received differs from the claim.
                        </p>
                        <x-input-error for="amount" />

                        <button type="submit" class="btn-primary mt-4 w-full">Approve and credit</button>
                    </form>
                </div>

                <div class="card">
                    <h2 class="font-semibold text-white">Reject</h2>

                    <form method="POST" action="{{ route('admin.deposits.reject', $deposit) }}" class="mt-3">
                        @csrf

                        <label for="reason" class="label">Reason</label>
                        <textarea id="reason" name="reason" rows="3" class="input"
                                  placeholder="Shown to the user, so be specific">{{ old('reason') }}</textarea>
                        <x-input-error for="reason" />

                        <button type="submit" class="btn-ghost mt-3 w-full text-rose-400 hover:!border-rose-500/40">
                            Reject deposit
                        </button>
                    </form>
                </div>

                @if (in_array($deposit->channel, ['paystack', 'flutterwave'], true))
                    <div class="card">
                        <h2 class="font-semibold text-white">Re-check with gateway</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Ask {{ $deposit->channelLabel() }} directly what happened. Use this when a callback
                            was lost and the deposit is stuck.
                        </p>

                        <form method="POST" action="{{ route('admin.deposits.reverify', $deposit) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn-ghost w-full">Re-verify payment</button>
                        </form>
                    </div>
                @endif
            @endif
        </aside>
    </div>
</x-layouts.admin>
