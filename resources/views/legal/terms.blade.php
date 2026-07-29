<x-layouts.base title="Terms of Service">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
        <a href="{{ route('home') }}" class="text-sm text-slate-400 hover:text-white">&larr; Back to site</a>

        <h1 class="mt-6 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Terms of Service</h1>
        <p class="mt-2 text-sm text-slate-500">Last updated {{ date('j F Y') }}</p>

        <div class="mt-8 space-y-7 text-sm leading-relaxed text-slate-300">
            <section>
                <h2 class="text-lg font-semibold text-white">1. Agreement</h2>
                <p class="mt-2">
                    By creating an account you agree to these terms. If you do not agree, do not use
                    {{ config('app.name') }}.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">2. Eligibility</h2>
                <p class="mt-2">
                    You must be at least 18 years old and legally able to enter contracts in your
                    jurisdiction. You confirm that the funds you deposit are lawfully yours.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">3. Your wallet</h2>
                <p class="mt-2">
                    Your account holds a deposit balance and a withdrawable balance. Funded money enters
                    the deposit balance and may be used to open investments. Returns, referral commissions
                    and returned capital enter the withdrawable balance, which is the only balance
                    eligible for payout.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">4. Investment risk</h2>
                <p class="mt-2">
                    Advertised returns are targets and not guarantees. Invested capital is at risk and you
                    may lose some or all of it. {{ config('app.name') }} is not a bank, and balances are
                    not insured or protected by any deposit guarantee scheme.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">5. Deposits</h2>
                <p class="mt-2">
                    Deposits are subject to minimum and maximum limits set by the administrator. Manual
                    bank transfers require confirmation before they are credited. Payments made to any
                    account other than the one shown in your funding screen may not be recoverable.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">6. Withdrawals</h2>
                <p class="mt-2">
                    Withdrawal requests may only be submitted during the published withdrawal window and
                    are subject to minimum and maximum limits. Requests are reviewed before payment. The
                    requested amount is held against your balance while a request is open. We may decline
                    a request where we suspect fraud, error or a breach of these terms.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">7. Referrals</h2>
                <p class="mt-2">
                    Commission is paid across up to three levels when a referred user invests. Rates are
                    published in your referral dashboard and may change for future investments.
                    Self-referral and the creation of duplicate accounts to earn commission are prohibited
                    and may result in forfeiture.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">8. Suspension</h2>
                <p class="mt-2">
                    We may suspend or close an account that breaches these terms, or where required by law.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">9. Changes</h2>
                <p class="mt-2">
                    These terms may be updated. Investment terms already in progress are not altered by a
                    change to a plan's published rates.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">10. Contact</h2>
                <p class="mt-2">
                    Questions about these terms can be sent to
                    <a href="mailto:{{ config('zenvora.defaults.support_email') }}" class="text-brand-400 hover:text-brand-300">
                        {{ config('zenvora.defaults.support_email') }}
                    </a>.
                </p>
            </section>
        </div>
    </div>
</x-layouts.base>
