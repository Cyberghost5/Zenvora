<x-layouts.app title="Referrals"
               heading="Referrals"
               subheading="Share your link and earn commission across three levels when your network invests.">

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Commission earned"
                :value="$wallet->total_referral_earned->formatWithSymbol()"
                hint="Credited to your withdrawable balance."
                tone="positive" />

        @foreach ($tiers as $tier)
            <x-stat :label="'Tier '.$tier['tier'].' network'"
                    :value="$counts[$tier['tier']] ?? 0"
                    :hint="$tier['label'].' of each investment they place'"
                    tone="brand" />
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- Sharing                                                      --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card lg:col-span-2">
            <h2 class="font-semibold text-white">Your invite link</h2>
            <p class="mt-1 text-sm text-slate-400">
                Anyone registering through this link is placed in your network automatically.
            </p>

            <div class="mt-4">
                <label for="referral-link" class="label">Link</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="referral-link"
                           type="text"
                           value="{{ $link }}"
                           readonly
                           class="input flex-1 text-sm"
                           onclick="this.select()">
                    <button type="button" class="btn-primary shrink-0" data-copy="{{ $link }}">
                        Copy link
                    </button>
                </div>
            </div>

            <div class="mt-4">
                <label for="referral-code" class="label">Or share your code</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="referral-code"
                           type="text"
                           value="{{ auth()->user()->referral_code }}"
                           readonly
                           class="input flex-1 font-mono text-lg tracking-wider text-brand-300"
                           onclick="this.select()">
                    <button type="button" class="btn-ghost shrink-0" data-copy="{{ auth()->user()->referral_code }}">
                        Copy code
                    </button>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-2 border-t border-white/5 pt-5">
                @php
                    $shareText = rawurlencode('Join me on '.config('app.name').' and start earning daily returns: '.$link);
                @endphp

                <a href="https://wa.me/?text={{ $shareText }}"
                   target="_blank" rel="noopener"
                   class="btn-ghost text-xs">Share on WhatsApp</a>

                <a href="https://twitter.com/intent/tweet?text={{ $shareText }}"
                   target="_blank" rel="noopener"
                   class="btn-ghost text-xs">Share on X</a>

                <a href="https://t.me/share/url?url={{ rawurlencode($link) }}"
                   target="_blank" rel="noopener"
                   class="btn-ghost text-xs">Share on Telegram</a>

                <a href="mailto:?subject={{ rawurlencode('Join me on '.config('app.name')) }}&body={{ $shareText }}"
                   class="btn-ghost text-xs">Share by email</a>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Rates                                                        --}}
        {{-- ------------------------------------------------------------ --}}
        <aside class="card">
            <h2 class="font-semibold text-white">Commission rates</h2>

            <ul class="mt-3 space-y-2.5">
                @foreach ($tiers as $tier)
                    <li class="flex items-center gap-3 rounded-xl border border-white/10 bg-ink-950/50 px-3 py-2.5">
                        <span class="tabular grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-500/15 text-sm font-semibold text-brand-300">
                            {{ $tier['tier'] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-white">
                                {{ ['Direct', 'Second level', 'Third level'][$tier['tier'] - 1] ?? 'Tier '.$tier['tier'] }}
                            </p>
                            <p class="text-xs text-slate-500">{{ $counts[$tier['tier']] ?? 0 }} in network</p>
                        </div>
                        <span class="tabular font-semibold text-brand-300">{{ $tier['label'] }}</span>
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 border-t border-white/5 pt-4 text-xs leading-relaxed text-slate-500">
                Commission is calculated on the amount your network <strong class="text-slate-400">invests</strong>,
                not on what they deposit, and is credited the moment their investment is placed.
            </p>
        </aside>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Commission ledger                                                --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-white">Commission history</h2>

        @if ($commissions->isEmpty())
            <x-empty-state title="No commission earned yet"
                           message="You'll see a row here each time somebody in your network places an investment." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">From</th>
                                <th scope="col" class="px-4 py-3 font-medium">Tier</th>
                                <th scope="col" class="px-4 py-3 font-medium">Rate</th>
                                <th scope="col" class="px-4 py-3 font-medium">Date</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Earned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($commissions as $commission)
                                <tr class="bg-ink-900/40">
                                    <td class="px-4 py-3 whitespace-nowrap text-white">
                                        {{ $commission->sourceUser->name ?? 'Removed account' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="pill bg-white/5 text-slate-300 ring-white/10">
                                            {{ $commission->tierLabel() }}
                                        </span>
                                    </td>
                                    <td class="tabular px-4 py-3 text-slate-400">{{ $commission->rateLabel() }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $commission->created_at->format('j M Y') }}
                                    </td>
                                    <td class="tabular px-4 py-3 text-right font-semibold text-emerald-400">
                                        +{{ $commission->amount->formatWithSymbol() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Direct network                                                   --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-white">People you referred directly</h2>

        @if ($directReferrals->isEmpty())
            <x-empty-state title="Nobody has joined through your link yet"
                           message="Share your link above to start building your network." />
        @else
            <div class="overflow-hidden rounded-2xl border border-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-900/80 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Name</th>
                                <th scope="col" class="px-4 py-3 font-medium">Joined</th>
                                <th scope="col" class="px-4 py-3 font-medium">Investments</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($directReferrals as $referral)
                                <tr class="bg-ink-900/40">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white/5 text-xs font-semibold text-slate-300">
                                                {{ $referral->initials() }}
                                            </span>
                                            {{-- Only the first name: a referrer has no need for
                                                 their downline's full contact details. --}}
                                            <span class="text-white">{{ str($referral->name)->before(' ') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $referral->created_at->format('j M Y') }}
                                    </td>
                                    <td class="tabular px-4 py-3 text-slate-300">{{ $referral->investments_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $directReferrals->links() }}</div>
        @endif
    </section>
</x-layouts.app>
