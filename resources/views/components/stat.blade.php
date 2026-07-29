@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default',
    'icon' => null,
])

@php
    $toneClasses = match ($tone) {
        'positive' => 'text-emerald-400',
        'warning' => 'text-amber-400',
        'negative' => 'text-rose-400',
        'brand' => 'text-brand-400',
        default => 'text-white',
    };
@endphp

<div {{ $attributes->merge(['class' => 'card']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm text-slate-400">{{ $label }}</p>
        @if ($icon)
            <span class="shrink-0 text-slate-500">{!! $icon !!}</span>
        @endif
    </div>

    {{-- break-words so a large figure wraps instead of forcing the card wider. --}}
    <p class="tabular mt-2 text-2xl font-semibold break-words {{ $toneClasses }}">{{ $value }}</p>

    @if ($hint)
        <p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>
    @endif

    {{ $slot }}
</div>
