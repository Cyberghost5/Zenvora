@php
    $siteName = config('app.name');
@endphp

<x-layouts.base :description="'Fund your '.$siteName.' wallet, choose an investment plan, and track your daily returns.'">

    {{-- ---------------------------------------------------------------- --}}
    {{-- Header                                                           --}}
    {{-- ---------------------------------------------------------------- --}}
    <header class="sticky top-0 z-40 border-b border-white/5 bg-ink-950/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
            <a href="{{ route('home') }}" aria-label="{{ $siteName }} home">
                <x-logo />
            </a>

            <nav class="hidden items-center gap-7 text-sm md:flex" aria-label="Sections">
                <a href="#how" class="text-slate-400 transition hover:text-white">How it works</a>
                <a href="#plans" class="text-slate-400 transition hover:text-white">Plans</a>
                <a href="#referrals" class="text-slate-400 transition hover:text-white">Referrals</a>
                <a href="#faq" class="text-slate-400 transition hover:text-white">FAQ</a>
            </nav>

            <div class="flex items-center gap-2.5">
                <x-theme-toggle />
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary !px-3.5 !py-2 text-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden text-sm font-medium text-slate-300 transition hover:text-white sm:block">
                        Sign in
                    </a>
                    <a href="{{ route('register') }}" class="btn-primary !px-3.5 !py-2 text-sm">Get started</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main">
        {{-- ------------------------------------------------------------ --}}
        {{-- Hero                                                         --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="relative overflow-hidden">
            {{-- Decorative glow. aria-hidden so it is not announced. --}}
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="absolute -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-brand-500/15 blur-3xl"></div>
            </div>

            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24 lg:py-28">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="pill bg-brand-500/10 text-brand-300 ring-brand-500/25">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-400"></span>
                        Wallet-based investing
                    </span>

                    <h1 class="mt-5 text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Put your wallet to work,
                        <span class="text-brand-300">every single day</span>
                    </h1>

                    <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-slate-400 sm:text-lg">
                        Fund your {{ $siteName }} wallet, choose a plan that matches your appetite, and watch your
                        returns credit daily. Withdraw to your bank when the window opens.
                    </p>

                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary w-full sm:w-auto">Go to dashboard</a>
                            <a href="#plans" class="btn-ghost w-full sm:w-auto">Browse plans</a>
                        @else
                            <a href="{{ route('register') }}" class="btn-primary w-full sm:w-auto">Create free account</a>
                            <a href="#how" class="btn-ghost w-full sm:w-auto">See how it works</a>
                        @endauth
                    </div>

                    <p class="mt-5 text-xs text-slate-500">
                        Minimum deposit {{ $depositMin->formatWithSymbol() }} &middot;
                        Minimum withdrawal {{ $withdrawalMin->formatWithSymbol() }}
                    </p>
                </div>

                {{-- Key figures. Real settings, not invented statistics. --}}
                <dl class="mx-auto mt-14 grid max-w-4xl grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    @php
                        $highlights = [
                            ['label' => 'Plans available', 'value' => $plans->count() ?: '-'],
                            ['label' => 'Payouts', 'value' => 'Daily'],
                            ['label' => 'Referral tiers', 'value' => count($tiers)],
                            ['label' => 'Top tier bonus', 'value' => $tiers[0]['label'] ?? '-'],
                        ];
                    @endphp

                    @foreach ($highlights as $item)
                        <div class="rounded-2xl border border-white/10 bg-ink-900/60 p-4 text-center sm:p-5">
                            <dd class="tabular text-2xl font-semibold text-white sm:text-3xl">{{ $item['value'] }}</dd>
                            <dt class="mt-1 text-xs text-slate-500 sm:text-sm">{{ $item['label'] }}</dt>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- How it works                                                 --}}
        {{-- ------------------------------------------------------------ --}}
        <section id="how" class="border-y border-white/5 bg-ink-900/30 py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">How it works</h2>
                    <p class="mt-3 text-slate-400">
                        Four steps from signing up to being paid out. Your wallet keeps funded money and
                        earnings separate, so you always know what is actually withdrawable.
                    </p>
                </div>

                <ol class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @php
                        $steps = [
                            [
                                'title' => 'Create your account',
                                'body' => 'Register with your email and phone. Add a referral code if somebody invited you.',
                            ],
                            [
                                'title' => 'Fund your wallet',
                                'body' => 'Pay by card or transfer through Paystack or Flutterwave, redeem a coupon, or send a manual bank transfer.',
                            ],
                            [
                                'title' => 'Choose a plan',
                                'body' => 'Invest from your deposit balance. Your plan\'s terms are locked in the moment you subscribe.',
                            ],
                            [
                                'title' => 'Earn and withdraw',
                                'body' => 'Returns credit daily to your withdrawable balance. Request a payout during the withdrawal window.',
                            ],
                        ];
                    @endphp

                    @foreach ($steps as $index => $step)
                        <li class="card">
                            <span class="tabular grid h-9 w-9 place-items-center rounded-xl bg-brand-500/15 text-sm font-semibold text-brand-300">
                                {{ $index + 1 }}
                            </span>
                            <h3 class="mt-4 font-semibold text-white">{{ $step['title'] }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-400">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>

                {{-- The two-balance model is the thing users most often misunderstand,
                     so it is explained on the public page rather than only in-app. --}}
                <div class="mt-10 grid gap-4 sm:grid-cols-2">
                    <div class="card">
                        <div class="flex items-center gap-2.5">
                            <span class="h-2 w-2 rounded-full bg-brand-400"></span>
                            <h3 class="font-semibold text-white">Deposit balance</h3>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            Money you have funded. Use it to open investments. It cannot be withdrawn
                            directly - it has to be put to work first.
                        </p>
                    </div>

                    <div class="card">
                        <div class="flex items-center gap-2.5">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            <h3 class="font-semibold text-white">Withdrawable balance</h3>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">
                            Daily returns, referral commissions and returned capital land here. This is
                            what you can send to your bank account.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Plans                                                        --}}
        {{-- ------------------------------------------------------------ --}}
        <section id="plans" class="py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Investment plans</h2>
                    <p class="mt-3 text-slate-400">
                        Every plan pays a fixed daily percentage of your principal for its full term.
                        Terms are frozen when you subscribe, so a later change never affects a running plan.
                    </p>
                </div>

                @if ($plans->isEmpty())
                    <x-empty-state class="mt-10"
                                   title="No plans are published yet"
                                   message="Plans will appear here as soon as an administrator publishes them.">
                        <a href="{{ route('register') }}" class="btn-primary">Create an account</a>
                    </x-empty-state>
                @else
                    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($plans as $plan)
                            {{-- Highlight the middle plan when there are three or more. --}}
                            <x-plan-card :plan="$plan"
                                         :featured="$plans->count() >= 3 && $loop->index === 1"
                                         :href="auth()->check() ? route('investments.index') : route('register')"
                                         :cta="auth()->check() ? 'Invest now' : 'Get started'" />
                        @endforeach
                    </div>
                @endif

                <p class="mt-8 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-200/90">
                    <strong class="font-semibold">Capital is at risk.</strong>
                    Returns are targets, not guarantees. Never invest money you cannot afford to lose.
                </p>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Referrals                                                    --}}
        {{-- ------------------------------------------------------------ --}}
        <section id="referrals" class="border-y border-white/5 bg-ink-900/30 py-16 sm:py-20">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:items-center">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        Earn from your network
                    </h2>
                    <p class="mt-3 text-slate-400">
                        Share your personal link or code. When someone you introduced invests, a commission is
                        credited straight to your withdrawable balance - across three levels.
                    </p>

                    <ul class="mt-6 space-y-3">
                        @foreach ($tiers as $tier)
                            <li class="flex items-center gap-4 rounded-xl border border-white/10 bg-ink-950/40 px-4 py-3">
                                <span class="tabular grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-500/15 text-sm font-semibold text-brand-300">
                                    {{ $tier['tier'] }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-white">
                                        {{ ['Direct referrals', 'Their referrals', 'Third level'][$tier['tier'] - 1] ?? 'Tier '.$tier['tier'] }}
                                    </p>
                                    <p class="text-xs text-slate-500">of each investment they place</p>
                                </div>
                                <span class="tabular text-lg font-semibold text-brand-300">{{ $tier['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card">
                    <h3 class="font-semibold text-white">Funding and withdrawals</h3>

                    <dl class="mt-4 space-y-3.5 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-400">Deposit range</dt>
                            <dd class="tabular text-right font-medium text-white">
                                {{ $depositMin->formatWithSymbol() }} – {{ $depositMax->formatWithSymbol() }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 border-t border-white/5 pt-3.5">
                            <dt class="text-slate-400">Funding methods</dt>
                            <dd class="text-right font-medium text-white">Paystack, Flutterwave, coupon, bank transfer</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 border-t border-white/5 pt-3.5">
                            <dt class="text-slate-400">Minimum withdrawal</dt>
                            <dd class="tabular text-right font-medium text-white">{{ $withdrawalMin->formatWithSymbol() }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 border-t border-white/5 pt-3.5">
                            <dt class="text-slate-400">Withdrawal window</dt>
                            <dd class="text-right font-medium text-white">{{ $withdrawalWindow }}</dd>
                        </div>
                    </dl>

                    <p class="mt-5 text-xs leading-relaxed text-slate-500">
                        Withdrawal requests are reviewed by our team before payout. Amounts are held against
                        your balance while a request is open, so nothing can be requested twice.
                    </p>
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- FAQ -- native <details> so it works without JavaScript        --}}
        {{-- ------------------------------------------------------------ --}}
        <section id="faq" class="py-16 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Common questions</h2>

                @php
                    $faqs = [
                        [
                            'q' => 'How soon do returns start?',
                            'a' => 'Daily accrual begins the day after you subscribe. Each payout is credited to your withdrawable balance and listed against the investment, so you can check every single day\'s return.',
                        ],
                        [
                            'q' => 'Why can\'t I withdraw my deposit directly?',
                            'a' => 'Funded money sits in your deposit balance and is meant for investing. Once you invest, your daily returns - and your capital at maturity, where the plan includes it - arrive in your withdrawable balance, which is what pays out to your bank.',
                        ],
                        [
                            'q' => 'When can I withdraw?',
                            'a' => 'During the withdrawal window set by the administrator: currently '.$withdrawalWindow.'. Requests made outside it are declined, so check the window before requesting.',
                        ],
                        [
                            'q' => 'How long does a payout take?',
                            'a' => 'Requests are reviewed manually. Once approved, funds are sent to the bank account saved in your profile. Add your account details before requesting so nothing is delayed.',
                        ],
                        [
                            'q' => 'What happens if my bank transfer deposit isn\'t confirmed?',
                            'a' => 'Manual transfers need an administrator to confirm the payment arrived. Upload a clear receipt when you submit, and the deposit stays visible in your history with its current status throughout.',
                        ],
                        [
                            'q' => 'Is my capital guaranteed?',
                            'a' => 'No. Advertised returns are targets rather than guarantees, and invested capital is at risk. Only invest what you can afford to lose.',
                        ],
                    ];
                @endphp

                <div class="mt-8 divide-y divide-white/5 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ($faqs as $faq)
                        <details class="group bg-ink-900/50">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-medium text-white transition hover:bg-white/5">
                                {{ $faq['q'] }}
                                <svg class="h-4 w-4 shrink-0 text-slate-500 transition group-open:rotate-180"
                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                                </svg>
                            </summary>
                            <p class="px-5 pb-4 text-sm leading-relaxed text-slate-400">{{ $faq['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Closing call to action                                       --}}
        {{-- ------------------------------------------------------------ --}}
        @guest
            <section class="px-4 pb-16 sm:px-6 sm:pb-20">
                <div class="mx-auto max-w-6xl overflow-hidden rounded-3xl border border-brand-500/25 bg-gradient-to-br from-brand-500/15 via-ink-900 to-ink-900 px-6 py-12 text-center sm:px-12 sm:py-16">
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        Ready to get started?
                    </h2>
                    <p class="mx-auto mt-3 max-w-xl text-slate-400">
                        Create an account in under a minute. Fund your wallet whenever you are ready.
                    </p>
                    <div class="mt-7 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="btn-primary w-full sm:w-auto">Create free account</a>
                        <a href="{{ route('login') }}" class="btn-ghost w-full sm:w-auto">I already have one</a>
                    </div>
                </div>
            </section>
        @endguest
    </main>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Footer                                                           --}}
    {{-- ---------------------------------------------------------------- --}}
    <footer class="border-t border-white/5 bg-ink-900/40">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <x-logo />
                    <p class="mt-3 max-w-sm text-sm leading-relaxed text-slate-400">
                        A wallet-based investment platform. Fund, invest, track your daily returns and
                        withdraw to your bank.
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-white">Platform</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-400">
                        <li><a href="#how" class="hover:text-white">How it works</a></li>
                        <li><a href="#plans" class="hover:text-white">Plans</a></li>
                        <li><a href="#referrals" class="hover:text-white">Referrals</a></li>
                        <li><a href="#faq" class="hover:text-white">FAQ</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-white">Account &amp; support</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-400">
                        <li><a href="{{ route('login') }}" class="hover:text-white">Sign in</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-white">Create account</a></li>
                        <li><a href="mailto:{{ $supportEmail }}" class="hover:text-white">{{ $supportEmail }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 flex flex-col gap-4 border-t border-white/5 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
                <p class="flex gap-4 text-xs text-slate-500">
                    <a href="{{ route('terms') }}" class="hover:text-slate-300">Terms of Service</a>
                    <a href="{{ route('privacy') }}" class="hover:text-slate-300">Privacy Policy</a>
                </p>
            </div>

            <p class="mt-6 text-xs leading-relaxed text-slate-600">
                Risk warning: investing carries risk and past performance does not indicate future results.
                Advertised returns are targets, not guarantees, and you may lose some or all of the money you
                invest. {{ $siteName }} is not a bank and deposits are not insured. Please seek independent
                financial advice if you are unsure.
            </p>
        </div>
    </footer>
</x-layouts.base>
