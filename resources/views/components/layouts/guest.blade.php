@props(['title' => null, 'heading' => null, 'subheading' => null])

{{-- Centred single-column shell for the authentication screens. --}}
<x-layouts.base :title="$title" :noindex="true">
    <div class="flex min-h-dvh flex-col">
        <header class="border-b border-white/5">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="rounded-lg" aria-label="{{ config('app.name') }} home">
                    <x-logo />
                </a>

                <a href="{{ route('home') }}" class="text-sm text-slate-400 transition hover:text-white">
                    &larr; Back to site
                </a>
            </div>
        </header>

        <main id="main" class="flex flex-1 items-center justify-center px-4 py-10 sm:px-6 sm:py-14">
            <div class="w-full max-w-md">
                @if ($heading)
                    <div class="mb-7 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $heading }}</h1>
                        @if ($subheading)
                            <p class="mt-2 text-sm text-slate-400">{{ $subheading }}</p>
                        @endif
                    </div>
                @endif

                <div class="card">
                    <x-alerts />
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="mt-6 text-center text-sm text-slate-400">{{ $footer }}</div>
                @endisset
            </div>
        </main>

        <footer class="border-t border-white/5 px-4 py-5 text-center text-xs text-slate-500 sm:px-6">
            <p>
                &copy; {{ date('Y') }} {{ config('app.name') }}.
                <a href="{{ route('terms') }}" class="underline decoration-white/20 hover:text-slate-300">Terms</a>
                &middot;
                <a href="{{ route('privacy') }}" class="underline decoration-white/20 hover:text-slate-300">Privacy</a>
            </p>
        </footer>
    </div>
</x-layouts.base>
