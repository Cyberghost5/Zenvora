<x-layouts.admin title="Coupons"
                 heading="Coupons"
                 subheading="Codes that credit a user's deposit balance when redeemed.">

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- Issue                                                        --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card lg:col-span-1">
            <h2 class="font-semibold text-white">Issue coupons</h2>
            <p class="mt-1 text-sm text-slate-400">
                Each redemption credits the deposit balance, which the user can then invest.
            </p>

            <form method="POST" action="{{ route('admin.coupons.store') }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label for="amount" class="label">Value per coupon</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500">
                            {{ config('zenvora.currency_symbol') }}
                        </span>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01"
                               value="{{ old('amount') }}" required class="input tabular !pl-9">
                    </div>
                    <x-input-error for="amount" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="quantity" class="label">How many</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="100"
                               value="{{ old('quantity', 1) }}" required class="input tabular">
                        <x-input-error for="quantity" />
                    </div>

                    <div>
                        <label for="max_uses" class="label">Uses each</label>
                        <input id="max_uses" name="max_uses" type="number" min="1" max="10000"
                               value="{{ old('max_uses', 1) }}" required class="input tabular">
                        <p class="mt-1.5 text-xs text-slate-500">One redemption per user regardless.</p>
                        <x-input-error for="max_uses" />
                    </div>
                </div>

                <div>
                    <label for="code" class="label">
                        Custom code <span class="font-normal text-slate-500">(optional)</span>
                    </label>
                    <input id="code" name="code" type="text" value="{{ old('code') }}"
                           class="input font-mono uppercase" placeholder="Generated if left blank">
                    <p class="mt-1.5 text-xs text-slate-500">Only when creating a single coupon.</p>
                    <x-input-error for="code" />
                </div>

                <div>
                    <label for="expires_at" class="label">
                        Expires <span class="font-normal text-slate-500">(optional)</span>
                    </label>
                    <input id="expires_at" name="expires_at" type="datetime-local"
                           value="{{ old('expires_at') }}" class="input">
                    <x-input-error for="expires_at" />
                </div>

                <div>
                    <label for="note" class="label">
                        Internal note <span class="font-normal text-slate-500">(optional)</span>
                    </label>
                    <input id="note" name="note" type="text" value="{{ old('note') }}"
                           class="input" placeholder="e.g. Compensation for the 12 May outage">
                    <x-input-error for="note" />
                </div>

                <p class="rounded-xl border border-amber-500/25 bg-amber-500/10 px-3 py-2.5 text-xs leading-relaxed text-amber-200">
                    A coupon is real money once redeemed. Nothing external is charged - the platform absorbs the cost.
                </p>

                <button type="submit" class="btn-primary w-full">Create coupons</button>
            </form>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- List                                                         --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="lg:col-span-2">
            <h2 class="mb-3 text-lg font-semibold text-white">Issued coupons</h2>

            @if ($coupons->isEmpty())
                <x-empty-state title="No coupons issued yet"
                               message="Create one with the form to the left." />
            @else
                <div class="overflow-hidden rounded-2xl border border-white/10">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium">Code</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Value</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Used</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Expires</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach ($coupons as $coupon)
                                    <tr class="bg-ink-900/40">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <code class="font-mono text-xs text-brand-300">{{ $coupon->code }}</code>
                                                <button type="button" class="btn-ghost !px-2 !py-0.5 text-xs"
                                                        data-copy="{{ $coupon->code }}">Copy</button>
                                            </div>
                                            @if ($coupon->note)
                                                <p class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $coupon->note }}</p>
                                            @endif
                                        </td>
                                        <td class="tabular px-4 py-3 font-semibold whitespace-nowrap text-white">
                                            {{ $coupon->amount->formatWithSymbol() }}
                                        </td>
                                        <td class="tabular px-4 py-3 whitespace-nowrap text-slate-300">
                                            {{ $coupon->used_count }} / {{ $coupon->max_uses }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                            {{ $coupon->expires_at?->format('j M Y') ?? 'Never' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'pill',
                                                'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20' => $coupon->isRedeemable(),
                                                'bg-slate-500/10 text-slate-400 ring-slate-500/20' => ! $coupon->isRedeemable(),
                                            ])>
                                                {{ $coupon->statusLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.coupons.toggle', $coupon) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-ghost !px-3 !py-1.5 text-xs">
                                                        {{ $coupon->is_active ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}"
                                                      data-confirm="{{ $coupon->redemptions_count > 0
                                                          ? 'This coupon has been redeemed, so it will be disabled rather than deleted. Continue?'
                                                          : 'Delete coupon '.$coupon->code.'?' }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn-ghost !px-3 !py-1.5 text-xs text-rose-400 hover:!border-rose-500/40">
                                                        {{ $coupon->redemptions_count > 0 ? 'Retire' : 'Delete' }}
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

                <div class="mt-4">{{ $coupons->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.admin>
