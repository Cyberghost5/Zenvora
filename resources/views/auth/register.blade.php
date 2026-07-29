<x-layouts.guest title="Create account"
                 heading="Create your account"
                 subheading="Fund a wallet, pick a plan, and track your returns.">

    {{-- Confirm whose link brought them here, so the referral is visibly captured. --}}
    @if ($referrer)
        <div class="mb-5 flex items-center gap-3 rounded-xl border border-brand-500/25 bg-brand-500/10 px-4 py-3 text-sm">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500/20 text-xs font-semibold text-brand-200">
                {{ $referrer->initials() }}
            </span>
            <p class="text-brand-100">
                Invited by <span class="font-semibold">{{ $referrer->name }}</span>
            </p>
        </div>
    @elseif ($referralCode)
        <div class="mb-5 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            We could not find the referral code <span class="font-mono font-semibold">{{ $referralCode }}</span>.
            You can still register, or correct the code below.
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="label">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}"
                   required autofocus autocomplete="name" class="input" placeholder="Ada Obi">
            <x-input-error for="name" />
        </div>

        <!-- <div>
            <label for="email" class="label">
                Email address <span class="font-normal text-slate-500">(optional)</span>
            </label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   autocomplete="username" inputmode="email" class="input" placeholder="you@example.com">
            <p class="mt-1.5 text-xs text-slate-500">Optional for notifications and account recovery.</p>
            <x-input-error for="email" />
        </div> -->

        <div>
            <label for="phone" class="label">Phone number</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                   required autocomplete="tel" inputmode="tel" class="input" placeholder="0800 000 0000">
            <x-input-error for="phone" />
        </div>

        <div>
            <label for="password" class="label">Password</label>
            <input id="password" name="password" type="password" required
                   autocomplete="new-password" class="input" placeholder="••••••••">
            <p class="mt-1.5 text-xs text-slate-500">At least 6 characters.</p>
            <x-input-error for="password" />
        </div>

        <div>
            <label for="password_confirmation" class="label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password" class="input" placeholder="••••••••">
            <x-input-error for="password_confirmation" />
        </div>

        <div>
            <label for="referral_code" class="label">
                Referral code <span class="font-normal text-slate-500">(optional)</span>
            </label>
            <input id="referral_code"
                   name="referral_code"
                   type="text"
                   value="{{ old('referral_code', $referralCode) }}"
                   class="input font-mono uppercase"
                   placeholder="ZVXXXXXX"
                   @if ($referrer) readonly @endif>
            <x-input-error for="referral_code" />
        </div>

        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-400">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) required
                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
            <span>
                I agree to the
                <a href="{{ route('terms') }}" target="_blank" class="text-brand-400 hover:text-brand-300">Terms of Service</a>
                and
                <a href="{{ route('privacy') }}" target="_blank" class="text-brand-400 hover:text-brand-300">Privacy Policy</a>,
                and I understand that invested capital is at risk.
            </span>
        </label>
        <x-input-error for="terms" />

        <button type="submit" class="btn-primary w-full">Create account</button>
    </form>

    <x-slot:footer>
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-brand-400 hover:text-brand-300">Sign in</a>
    </x-slot:footer>
</x-layouts.guest>
