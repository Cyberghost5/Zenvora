<x-layouts.guest title="Reset password"
                 heading="Forgot your password?"
                 subheading="Enter your email and we'll send you a link to set a new one.">

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="label">Email address</label>
            <input id="email"
                   name="email"
                   type="email"
                   value="{{ old('email') }}"
                   required
                   autofocus
                   autocomplete="username"
                   inputmode="email"
                   class="input"
                   placeholder="you@example.com">
            <x-input-error for="email" />
        </div>

        <button type="submit" class="btn-primary w-full">Email me a reset link</button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-medium text-brand-400 hover:text-brand-300">
            &larr; Back to sign in
        </a>
    </x-slot:footer>
</x-layouts.guest>
