<x-layouts.app title="Profile"
               heading="Profile"
               subheading="Your details, password and the bank accounts we pay you into.">

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ------------------------------------------------------------ --}}
        {{-- Personal details                                             --}}
        {{-- ------------------------------------------------------------ --}}
        <section class="card lg:col-span-2">
            <h2 class="font-semibold text-white">Your details</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="label">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                           required autocomplete="name" class="input">
                    <x-input-error for="name" />
                </div>

                <div>
                    <label for="email" class="label">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                           required autocomplete="email" class="input">
                    <p class="mt-1.5 text-xs text-slate-500">Changing this will require you to verify the new address.</p>
                    <x-input-error for="email" />
                </div>

                <div>
                    <label for="phone" class="label">Phone number</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}"
                           required autocomplete="tel" class="input">
                    <x-input-error for="phone" />
                </div>

                <button type="submit" class="btn-primary">Save details</button>
            </form>
        </section>

        {{-- ------------------------------------------------------------ --}}
        {{-- Account summary                                              --}}
        {{-- ------------------------------------------------------------ --}}
        <aside class="card">
            <h2 class="font-semibold text-white">Account</h2>

            <dl class="mt-3 space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-500">Referral code</dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <code class="flex-1 truncate rounded-lg bg-ink-950/60 px-2.5 py-1.5 font-mono text-xs text-brand-300">
                            {{ $user->referral_code }}
                        </code>
                        <button type="button" class="btn-ghost !px-2 !py-1 text-xs"
                                data-copy="{{ $user->referralLink() }}">Copy</button>
                    </dd>
                </div>

                <div class="border-t border-white/5 pt-3">
                    <dt class="text-xs text-slate-500">Member since</dt>
                    <dd class="mt-0.5 font-medium text-white">{{ $user->created_at->format('j M Y') }}</dd>
                </div>

                @if ($user->referrer)
                    <div class="border-t border-white/5 pt-3">
                        <dt class="text-xs text-slate-500">Referred by</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->referrer->name }}</dd>
                    </div>
                @endif

                @if ($user->last_login_at)
                    <div class="border-t border-white/5 pt-3">
                        <dt class="text-xs text-slate-500">Last sign-in</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->last_login_at->format('j M Y, H:i') }}</dd>
                    </div>
                @endif

                <div class="border-t border-white/5 pt-3">
                    <dt class="text-xs text-slate-500">Email verified</dt>
                    <dd class="mt-0.5 font-medium {{ $user->email_verified_at ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $user->email_verified_at ? 'Yes' : 'Not yet' }}
                    </dd>
                </div>
            </dl>
        </aside>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Payout accounts                                                  --}}
    {{-- ---------------------------------------------------------------- --}}
    <section id="bank-accounts" class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <h2 class="font-semibold text-white">Bank accounts for payouts</h2>
            <p class="mt-1 text-sm text-slate-400">
                Withdrawals are sent to one of these accounts. Make sure the name matches your own - a
                mismatch will delay your payment.
            </p>

            @if ($accounts->isEmpty())
                <p class="mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                    You have no payout account saved. Add one before requesting a withdrawal.
                </p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($accounts as $account)
                        <li class="flex flex-wrap items-center gap-3 rounded-xl border border-white/10 bg-ink-950/50 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-white">{{ $account->bank_name }}</p>
                                    @if ($account->is_primary)
                                        <span class="pill bg-brand-500/10 text-brand-300 ring-brand-500/20">Default</span>
                                    @endif
                                </div>
                                <p class="tabular mt-0.5 font-mono text-sm text-slate-400">{{ $account->account_number }}</p>
                                <p class="text-xs text-slate-500">{{ $account->account_name }}</p>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                @unless ($account->is_primary)
                                    <form method="POST" action="{{ route('profile.bank-accounts.primary', $account) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-ghost !px-3 !py-1.5 text-xs">Make default</button>
                                    </form>
                                @endunless

                                <form method="POST"
                                      action="{{ route('profile.bank-accounts.destroy', $account) }}"
                                      data-confirm="Remove {{ $account->bank_name }} ending {{ substr($account->account_number, -4) }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost !px-3 !py-1.5 text-xs text-rose-400 hover:!border-rose-500/40">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-input-error for="account" />

            <form method="POST" action="{{ route('profile.bank-accounts.store') }}"
                  class="mt-6 space-y-4 border-t border-white/5 pt-6">
                @csrf

                <h3 class="text-sm font-semibold text-white">Add an account</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="bank_name" class="label">Bank name</label>
                        <input id="bank_name" name="bank_name" type="text" value="{{ old('bank_name') }}"
                               class="input" placeholder="e.g. Access Bank">
                        <x-input-error for="bank_name" />
                    </div>

                    <div>
                        <label for="account_number" class="label">Account number</label>
                        <input id="account_number" name="account_number" type="text" inputmode="numeric"
                               value="{{ old('account_number') }}" class="input tabular font-mono"
                               placeholder="0123456789">
                        <x-input-error for="account_number" />
                    </div>
                </div>

                <div>
                    <label for="account_name" class="label">Account name</label>
                    <input id="account_name" name="account_name" type="text" value="{{ old('account_name') }}"
                           class="input" placeholder="Exactly as it appears at your bank">
                    <x-input-error for="account_name" />
                </div>

                <button type="submit" class="btn-primary">Save account</button>
            </form>
        </div>

        {{-- ------------------------------------------------------------ --}}
        {{-- Password                                                     --}}
        {{-- ------------------------------------------------------------ --}}
        <div class="card">
            <h2 class="font-semibold text-white">Change password</h2>

            <form method="POST" action="{{ route('profile.password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="label">Current password</label>
                    <input id="current_password" name="current_password" type="password"
                           autocomplete="current-password" class="input">
                    <x-input-error for="current_password" />
                </div>

                <div>
                    <label for="new_password" class="label">New password</label>
                    <input id="new_password" name="password" type="password"
                           autocomplete="new-password" class="input">
                    <x-input-error for="password" />
                </div>

                <div>
                    <label for="new_password_confirmation" class="label">Confirm new password</label>
                    <input id="new_password_confirmation" name="password_confirmation" type="password"
                           autocomplete="new-password" class="input">
                </div>

                <button type="submit" class="btn-primary w-full">Update password</button>
            </form>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Close account                                                    --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mt-6 rounded-2xl border border-rose-500/20 bg-rose-500/5 p-5 sm:p-6">
        <h2 class="font-semibold text-white">Close your account</h2>
        <p class="mt-1 max-w-2xl text-sm text-slate-400">
            This is permanent. You cannot close your account while an investment is running, a withdrawal is
            being processed, or your wallet still holds a balance - withdraw everything first.
        </p>

        <button type="button"
                class="btn-ghost mt-4 text-rose-400 hover:!border-rose-500/40"
                data-toggle-target="#close-account"
                aria-expanded="false">
            I want to close my account
        </button>

        <form id="close-account"
              method="POST"
              action="{{ route('profile.destroy') }}"
              class="mt-4 hidden max-w-sm"
              data-confirm="This permanently closes your account. Continue?">
            @csrf
            @method('DELETE')

            <label for="delete_password" class="label">Confirm with your password</label>
            <input id="delete_password" name="password" type="password"
                   autocomplete="current-password" class="input" placeholder="••••••••">
            <x-input-error for="password" />

            <button type="submit" class="btn-danger mt-4">Permanently close account</button>
        </form>
    </section>
</x-layouts.app>
