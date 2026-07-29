@props(['class' => 'h-9'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 font-semibold tracking-tight text-white']) }}>
    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" class="{{ $class }} w-auto object-contain">
    <span class="text-lg">{{ $slot->isEmpty() ? config('app.name') : $slot }}</span>
</span>
