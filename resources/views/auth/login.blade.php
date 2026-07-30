<x-layouts.guest title="Sign in"
                 heading="Welcome back"
                 subheading="Sign in to manage your wallet and investments.">

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="phone" class="label">Phone number</label>
            <input id="phone"
                   name="phone"
                   type="tel"
                   value="{{ old('phone') }}"
                   required
                   autofocus
                   autocomplete="username"
                   inputmode="tel"
                   class="input"
                   placeholder="e.g. 08012345678">
            <x-input-error for="phone" />
        </div>

        <div>
            <!-- <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <label for="password" class="label !mb-0">Password</label>
                <a href="{{ route('password.request') }}" class="text-sm text-brand-400 hover:text-brand-300">
                    Forgot password?
                </a>
            </div> -->
            <label for="password" class="label">Password</label>
            <input id="password"
                   name="password"
                   type="password"
                   required
                   autocomplete="current-password"
                   class="input"
                   placeholder="••••••••">
            <x-input-error for="password" />
        </div>

        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-400">
            <input type="checkbox"
                   name="remember"
                   value="1"
                   @checked(old('remember'))
                   class="h-4 w-4 rounded border-white/20 bg-ink-950 text-brand-500 focus:ring-brand-500/40">
            Keep me signed in
        </label>

        <button type="submit" class="btn-primary w-full">Sign in</button>
    </form>

    <x-slot:footer>
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-brand-400 hover:text-brand-300">Create one</a>
    </x-slot:footer>
</x-layouts.guest>
