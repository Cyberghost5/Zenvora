@props([
    'title' => null,
    'description' => 'Fund your wallet, choose a plan, and track your returns daily.',
    'noindex' => false,
])

{{--
    The single <html> document every other layout uses, so meta tags, fonts and
    asset bundling live in one place.

    Values arrive as props rather than @yield sections: Blade components are
    rendered in isolation, so a section defined in a child would not reach here.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#1a1d24">

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">

    <script>
        (function() {
            const saved = localStorage.getItem('theme');
            if (saved === 'light' || (!saved && window.matchMedia('(prefers-color-scheme: light)').matches)) {
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    {{-- Signed-in pages have no business in search results. --}}
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-ink-950 font-sans text-slate-200">
    {{-- Keyboard users can jump the navigation on every page. --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-brand-500 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-ink-950">
        Skip to content
    </a>

    {{ $slot }}
</body>
</html>
