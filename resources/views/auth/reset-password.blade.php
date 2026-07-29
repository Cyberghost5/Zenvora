<x-layouts.guest title="Set a new password"
                 heading="Set a new password"
                 subheading="Choose something you haven't used on this account before.">

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="label">Email address</label>
            <input id="email"
                   name="email"
                   type="email"
                   value="{{ old('email', $email) }}"
                   required
                   autocomplete="username"
                   class="input">
            <x-input-error for="email" />
        </div>

        <div>
            <label for="password" class="label">New password</label>
            <input id="password" name="password" type="password" required autofocus
                   autocomplete="new-password" class="input" placeholder="••••••••">
            <p class="mt-1.5 text-xs text-slate-500">At least 8 characters, including a letter and a number.</p>
            <x-input-error for="password" />
        </div>

        <div>
            <label for="password_confirmation" class="label">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password" class="input" placeholder="••••••••">
            <x-input-error for="password_confirmation" />
        </div>

        <button type="submit" class="btn-primary w-full">Save new password</button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-medium text-brand-400 hover:text-brand-300">
            &larr; Back to sign in
        </a>
    </x-slot:footer>
</x-layouts.guest>
