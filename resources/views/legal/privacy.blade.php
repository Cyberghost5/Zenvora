<x-layouts.base title="Privacy Policy">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
        <a href="{{ route('home') }}" class="text-sm text-slate-400 hover:text-white">&larr; Back to site</a>

        <h1 class="mt-6 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Privacy Policy</h1>
        <p class="mt-2 text-sm text-slate-500">Last updated {{ date('j F Y') }}</p>

        <div class="mt-8 space-y-7 text-sm leading-relaxed text-slate-300">
            <section>
                <h2 class="text-lg font-semibold text-white">What we collect</h2>
                <ul class="mt-2 list-inside list-disc space-y-1.5">
                    <li>Account details: your name, email address and phone number.</li>
                    <li>Payout details: the bank name, account number and account name you save.</li>
                    <li>Transaction records: deposits, investments, returns, commissions and withdrawals.</li>
                    <li>Payment evidence: receipts you upload for manual bank transfers.</li>
                    <li>Technical data: IP address and sign-in timestamps, kept for security.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">Why we collect it</h2>
                <p class="mt-2">
                    To operate your account and wallet, process deposits and withdrawals, calculate returns
                    and referral commissions, detect fraud, and meet our legal and record-keeping
                    obligations.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">Who we share it with</h2>
                <p class="mt-2">
                    Payment processors (Paystack and Flutterwave) receive the data needed to take a
                    payment. We do not sell your personal data. We may disclose information where the law
                    requires it.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">Card details</h2>
                <p class="mt-2">
                    Card numbers are entered on the payment provider's own hosted checkout and are never
                    transmitted to or stored by {{ config('app.name') }}.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">Retention</h2>
                <p class="mt-2">
                    Transaction and audit records are kept for as long as the law requires, which may be
                    after your account is closed.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">Your rights</h2>
                <p class="mt-2">
                    You may request access to, correction of, or deletion of your personal data. Some
                    records must be retained for legal reasons even after an account is closed.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-white">Contact</h2>
                <p class="mt-2">
                    Privacy enquiries can be sent to
                    <a href="mailto:{{ config('zenvora.defaults.support_email') }}" class="text-brand-400 hover:text-brand-300">
                        {{ config('zenvora.defaults.support_email') }}
                    </a>.
                </p>
            </section>
        </div>
    </div>
</x-layouts.base>
