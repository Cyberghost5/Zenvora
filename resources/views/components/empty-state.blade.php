@props(['title', 'message' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-white/10 px-6 py-12 text-center']) }}>
    <p class="font-medium text-slate-300">{{ $title }}</p>

    @if ($message)
        <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">{{ $message }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-5 flex flex-wrap justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
