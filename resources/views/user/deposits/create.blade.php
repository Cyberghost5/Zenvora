<x-layouts.app title="Fund wallet"
               heading="Fund your wallet"
               subheading="Money you add lands in your deposit balance, ready to invest.">

    <x-slot:actions>
        <a href="{{ route('deposits.index') }}" class="btn-ghost">Deposit history</a>
    </x-slot:actions>

    @if (empty($channels))
        <x-empty-state title="No funding methods are available"
                       message="An administrator has not enabled any payment method yet. Please check back shortly or contact support." />
    @else
        <form method="POST"
              action="{{ route('deposits.store') }}"
              enctype="multipart/form-data"
              class="grid gap-6 lg:grid-cols-3">
            @csrf

            {{-- ---------------------------------------------------------- --}}
            {{-- Channel picker                                             --}}
            {{-- ---------------------------------------------------------- --}}
            <div class="lg:col-span-2">
                <fieldset>
                    <legend class="label">Choose how to pay</legend>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($channels as $index => $channel)
                            <label data-channel-card="{{ $channel['key'] }}"
                                   class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-ink-900/60 p-4 transition hover:border-white/25">
                                <input type="radio"
                                       name="channel"
                                       value="{{ $channel['key'] }}"
                                       @checked(old('channel', $channels[0]['key']) === $channel['key'])
                                       class="mt-0.5 h-4 w-4 shrink-0 border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-white">{{ $channel['label'] }}</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">{{ $channel['note'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <x-input-error for="channel" />

                {{-- ------------------------------------------------------ --}}
                {{-- Per-channel fields. JS shows only the active one and    --}}
                {{-- toggles `required` so a hidden field cannot block       --}}
                {{-- submission with an error the user cannot see.           --}}
                {{-- ------------------------------------------------------ --}}

                @foreach (['paystack', 'flutterwave'] as $gateway)
                    @if (collect($channels)->contains('key', $gateway))
                        @php
                            $activeGateway = old('channel', $channels[0]['key'] ?? '') === $gateway;
                        @endphp
                        <div data-channel-fields="{{ $gateway }}" class="mt-6 {{ $activeGateway ? '' : 'hidden' }}">
                            <div class="card">
                                <label for="amount-{{ $gateway }}" class="label">Amount to fund</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                                        {{ config('zenvora.currency_symbol') }}
                                    </span>
                                    <input id="amount-{{ $gateway }}"
                                           name="amount"
                                           type="number"
                                           step="0.01"
                                           min="{{ $min->toMajor() }}"
                                           max="{{ $max->toMajor() }}"
                                           value="{{ old('amount') }}"
                                           placeholder="{{ number_format($min->toMajor(), 2) }}"
                                           class="input tabular !pl-9"
                                           data-required
                                           {{ $activeGateway ? '' : 'disabled' }}>
                                </div>
                                <p class="mt-1.5 text-xs text-slate-500">
                                    Between {{ $min->formatWithSymbol() }} and {{ $max->formatWithSymbol() }}.
                                </p>
                                <x-input-error for="amount" />

                                <p class="mt-4 text-xs leading-relaxed text-slate-500">
                                    You'll be taken to {{ ucfirst($gateway) }}'s secure checkout. Your card details are
                                    never sent to {{ config('app.name') }}. Your wallet is credited as soon as the
                                    payment is confirmed.
                                </p>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if (collect($channels)->contains('key', 'manual'))
                    @php
                        $activeManual = old('channel', $channels[0]['key'] ?? '') === 'manual';
                    @endphp
                    <div data-channel-fields="manual" class="mt-6 {{ $activeManual ? '' : 'hidden' }}">
                        <div class="card">
                            {{-- Show the destination account before asking for proof,
                                 so the user pays the right place first. --}}
                            <h3 class="font-semibold text-white">1. Transfer to this account</h3>

                            <dl class="mt-3 space-y-2.5 rounded-xl border border-brand-500/25 bg-brand-500/5 p-4 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-slate-400">Bank</dt>
                                    <dd class="font-semibold text-white">{{ $bank['name'] ?: '-' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                                    <dt class="text-slate-400">Account number</dt>
                                    <dd class="flex items-center gap-2">
                                        <span class="tabular font-mono font-semibold text-white">{{ $bank['number'] ?: '-' }}</span>
                                        @if ($bank['number'])
                                            <button type="button"
                                                    class="btn-ghost !px-2 !py-1 text-xs"
                                                    data-copy="{{ $bank['number'] }}">Copy</button>
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                                    <dt class="text-slate-400">Account name</dt>
                                    <dd class="font-semibold text-white">{{ $bank['account'] ?: '-' }}</dd>
                                </div>
                            </dl>

                            @if ($bank['instructions'])
                                <p class="mt-3 text-xs leading-relaxed text-slate-500">{{ $bank['instructions'] }}</p>
                            @endif

                            <h3 class="mt-6 font-semibold text-white">2. Tell us about the transfer</h3>

                            <div class="mt-3 space-y-4">
                                <div>
                                    <label for="amount-manual" class="label">Amount transferred</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                                            {{ config('zenvora.currency_symbol') }}
                                        </span>
                                        <input id="amount-manual"
                                               name="amount"
                                               type="number"
                                               step="0.01"
                                               min="{{ $min->toMajor() }}"
                                               max="{{ $max->toMajor() }}"
                                               value="{{ old('amount') }}"
                                               class="input tabular !pl-9"
                                               data-required
                                               {{ $activeManual ? '' : 'disabled' }}>
                                    </div>
                                    <x-input-error for="amount" />
                                </div>

                                <div>
                                    <label for="depositor_name" class="label">Name on the sending account</label>
                                    <input id="depositor_name"
                                           name="depositor_name"
                                           type="text"
                                           value="{{ old('depositor_name') }}"
                                           class="input"
                                           placeholder="As it appears on your bank account"
                                           data-required
                                           {{ $activeManual ? '' : 'disabled' }}>
                                    <x-input-error for="depositor_name" />
                                </div>

                                <div>
                                    <label for="paid_on" class="label">
                                        Date of transfer <span class="font-normal text-slate-500">(optional)</span>
                                    </label>
                                    <input id="paid_on"
                                           name="paid_on"
                                           type="date"
                                           max="{{ now()->toDateString() }}"
                                           value="{{ old('paid_on') }}"
                                           class="input"
                                           {{ $activeManual ? '' : 'disabled' }}>
                                    <x-input-error for="paid_on" />
                                </div>

                                <div>
                                    <label for="proof" class="label">Payment receipt</label>
                                    <input id="proof"
                                           name="proof"
                                           type="file"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           class="input file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-sm file:text-slate-200"
                                           data-required
                                           {{ $activeManual ? '' : 'disabled' }}>
                                    <p class="mt-1.5 text-xs text-slate-500">
                                        A screenshot or PDF, up to 5MB. Make sure the amount and date are legible.
                                    </p>
                                    <x-input-error for="proof" />
                                </div>
                            </div>

                            <p class="mt-5 rounded-xl border border-amber-500/25 bg-amber-500/10 px-3 py-2.5 text-xs leading-relaxed text-amber-200">
                                Bank transfers are credited only after an administrator confirms the money arrived.
                                Nothing is added to your balance until then.
                            </p>
                        </div>
                    </div>
                @endif

                @if (collect($channels)->contains('key', 'coupon'))
                    @php
                        $activeCoupon = old('channel', $channels[0]['key'] ?? '') === 'coupon';
                    @endphp
                    <div data-channel-fields="coupon" class="mt-6 {{ $activeCoupon ? '' : 'hidden' }}">
                        <div class="card">
                            <label for="coupon_code" class="label">Coupon code</label>
                            <input id="coupon_code"
                                   name="coupon_code"
                                   type="text"
                                   value="{{ old('coupon_code') }}"
                                   placeholder="ZVC-XXXXXXXX"
                                   autocomplete="off"
                                   class="input font-mono uppercase"
                                   data-required
                                   {{ $activeCoupon ? '' : 'disabled' }}>
                            <p class="mt-1.5 text-xs text-slate-500">
                                Codes are issued by support. The value is credited immediately. Click <a href="https://wa.me/2349031704109">here</a> to get one from our support.
                            </p>
                            <x-input-error for="coupon_code" />
                        </div>
                    </div>
                @endif

                <button type="submit" class="btn-primary mt-6 w-full sm:w-auto">Continue</button>
            </div>

            {{-- ---------------------------------------------------------- --}}
            {{-- Aside                                                      --}}
            {{-- ---------------------------------------------------------- --}}
            <aside class="space-y-4">
                <div class="card">
                    <h2 class="font-semibold text-white">Deposit limits</h2>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-400">Minimum</dt>
                            <dd class="tabular font-semibold text-white">{{ $min->formatWithSymbol() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-2.5">
                            <dt class="text-slate-400">Maximum</dt>
                            <dd class="tabular font-semibold text-white">{{ $max->formatWithSymbol() }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card">
                    <h2 class="font-semibold text-white">What happens next</h2>
                    <ol class="mt-3 space-y-3 text-sm text-slate-400">
                        <li class="flex gap-3">
                            <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/5 text-xs font-semibold text-slate-300">1</span>
                            <span>Funds land in your <strong class="font-medium text-white">deposit balance</strong>.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/5 text-xs font-semibold text-slate-300">2</span>
                            <span>Choose a plan and invest that money.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/5 text-xs font-semibold text-slate-300">3</span>
                            <span>Daily returns arrive in your <strong class="font-medium text-white">withdrawable balance</strong>.</span>
                        </li>
                    </ol>

                    <p class="mt-4 border-t border-white/5 pt-4 text-xs leading-relaxed text-slate-500">
                        A deposit cannot be withdrawn directly - it has to be invested first.
                    </p>
                </div>
            </aside>
        </form>
    @endif
</x-layouts.app>
