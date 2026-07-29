@props(['href', 'active' => false, 'badge' => null])

@php
    $classes = $active
        ? 'bg-brand-500/12 text-white ring-1 ring-inset ring-brand-500/25'
        : 'text-slate-400 hover:bg-white/5 hover:text-white';
@endphp

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   {{ $attributes->merge(['class' => "flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {$classes}"]) }}>
    <span class="flex-1">{{ $slot }}</span>

    {{-- A count of things waiting on the user, e.g. deposits under review. --}}
    @if ($badge)
        <span class="tabular rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-semibold text-amber-400">
            {{ $badge }}
        </span>
    @endif
</a>
