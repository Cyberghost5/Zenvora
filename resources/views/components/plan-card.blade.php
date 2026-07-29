@props([
    'plan',
    'featured' => false,
    'href' => null,
    'cta' => 'Invest now',
])

<div {{ $attributes->merge([
    'class' => 'relative flex flex-col rounded-2xl border p-6 '
        . ($featured
            ? 'border-brand-500/40 bg-gradient-to-b from-brand-500/10 to-ink-900/70'
            : 'border-white/10 bg-ink-900/70'),
]) }}>
    @if ($featured)
        <span class="pill absolute -top-3 left-6 bg-brand-500 text-ink-950 ring-brand-400">
            Most popular
        </span>
    @endif

    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-white">{{ $plan->name }}</h3>
            @if ($plan->tagline)
                <p class="mt-0.5 text-sm text-slate-400">{{ $plan->tagline }}</p>
            @endif
        </div>
    </div>

    {{-- The daily rate is the headline figure, since that is what the plan sells. --}}
    <div class="mt-5 flex items-baseline gap-1.5">
        <span class="tabular text-4xl font-semibold tracking-tight text-brand-300">{{ $plan->dailyRoiLabel() }}</span>
        <span class="text-sm text-slate-400">/ day</span>
    </div>

    <p class="mt-1 text-sm text-slate-400">
        {{ $plan->totalRoiLabel() }} total over {{ $plan->durationLabel() }}
    </p>

    <dl class="mt-5 space-y-2.5 border-t border-white/5 pt-5 text-sm">
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-400">Amount</dt>
            <dd class="tabular font-medium text-white">{{ $plan->min_amount->formatWithSymbol() }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-400">Duration</dt>
            <dd class="font-medium text-white">{{ $plan->durationLabel() }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-400">Capital</dt>
            <dd class="font-medium {{ $plan->return_capital ? 'text-emerald-400' : 'text-slate-300' }}">
                {{ $plan->return_capital ? 'Returned at maturity' : 'Included in payout' }}
            </dd>
        </div>
    </dl>

    @if ($plan->description)
        <p class="mt-4 text-sm leading-relaxed text-slate-400">{{ $plan->description }}</p>
    @endif

    {{-- mt-auto pins the button to the bottom, so cards with differing amounts of
         text still line their buttons up across the grid. --}}
    @if ($href)
        <div class="mt-auto pt-6">
            <a href="{{ $href }}" class="{{ $featured ? 'btn-primary' : 'btn-ghost' }} w-full">
                {{ $cta }}
            </a>
        </div>
    @endif

    {{ $slot }}
</div>
