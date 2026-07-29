<x-layouts.app :title="$deposit->reference"
               heading="Deposit details"
               :subheading="$deposit->reference">

    <x-slot:actions>
        <a href="{{ route('deposits.index') }}" class="btn-ghost">&larr; All deposits</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-400">Amount</p>
                    <p class="tabular mt-1 text-3xl font-semibold text-white">{{ $deposit->amount->formatWithSymbol() }}</p>
                </div>
                <span class="pill {{ $deposit->statusTone() }}">{{ $deposit->statusLabel() }}</span>
            </div>

            <dl class="mt-6 space-y-3 border-t border-white/5 pt-6 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-400">Method</dt>
                    <dd class="font-medium text-white">{{ $deposit->channelLabel() }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                    <dt class="text-slate-400">Requested</dt>
                    <dd class="font-medium text-white">{{ $deposit->created_at->format('j M Y, H:i') }}</dd>
                </div>

                @if ($deposit->credited_at)
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                        <dt class="text-slate-400">Credited</dt>
                        <dd class="font-medium text-emerald-400">{{ $deposit->credited_at->format('j M Y, H:i') }}</dd>
                    </div>
                @endif

                @if ($deposit->coupon)
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                        <dt class="text-slate-400">Coupon</dt>
                        <dd class="font-mono font-medium text-white">{{ $deposit->coupon->code }}</dd>
                    </div>
                @endif

                @if ($deposit->channel === 'manual')
                    @if ($deposit->depositor_name)
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                            <dt class="text-slate-400">Sent by</dt>
                            <dd class="font-medium text-white">{{ $deposit->depositor_name }}</dd>
                        </div>
                    @endif
                    @if ($deposit->paid_on)
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                            <dt class="text-slate-400">Transfer date</dt>
                            <dd class="font-medium text-white">{{ $deposit->paid_on->format('j M Y') }}</dd>
                        </div>
                    @endif
                    @if ($deposit->paid_to_account)
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                            <dt class="text-slate-400">Paid into</dt>
                            <dd class="text-right font-medium text-white">{{ $deposit->paid_to_account }}</dd>
                        </div>
                    @endif
                @endif

                @if ($deposit->gateway_reference)
                    <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
                        <dt class="text-slate-400">Gateway reference</dt>
                        <dd class="font-mono text-xs break-all text-slate-300">{{ $deposit->gateway_reference }}</dd>
                    </div>
                @endif
            </dl>

            @if ($deposit->rejection_reason)
                <p class="mt-6 rounded-xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ $deposit->rejection_reason }}
                </p>
            @endif

            @if ($deposit->proof_path)
                <div class="mt-6 border-t border-white/5 pt-6">
                    <p class="mb-2 text-sm text-slate-400">Your receipt</p>
                    <a href="{{ Storage::url($deposit->proof_path) }}"
                       target="_blank"
                       rel="noopener"
                       class="btn-ghost">
                        View uploaded receipt
                    </a>
                </div>
            @endif
        </section>

        <aside class="space-y-4">
            @if ($deposit->status === 'awaiting_review')
                <div class="card">
                    <h2 class="font-semibold text-white">Under review</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        An administrator is confirming this payment. Your wallet is credited as soon as it clears.
                        You don't need to do anything else.
                    </p>
                </div>
            @elseif ($deposit->status === 'pending')
                <div class="card">
                    <h2 class="font-semibold text-white">Awaiting payment</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        We haven't received confirmation from the payment provider. If you completed the payment
                        and were debited, contact support quoting
                        <span class="font-mono text-slate-300">{{ $deposit->reference }}</span>.
                    </p>
                    <a href="{{ route('deposits.create') }}" class="btn-ghost mt-4 w-full">Try again</a>
                </div>
            @elseif ($deposit->isSuccessful())
                <div class="card">
                    <h2 class="font-semibold text-white">Credited</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        This amount is in your deposit balance and ready to invest.
                    </p>
                    <a href="{{ route('investments.index') }}" class="btn-primary mt-4 w-full">Choose a plan</a>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.app>
