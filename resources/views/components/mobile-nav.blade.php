@php
    $items = [
        [
            'label' => 'Home',
            'route' => 'dashboard',
            'match' => 'dashboard',
            'icon'  => 'fa-solid fa-house',
        ],
        [
            'label' => 'Plans',
            'route' => 'plans.index',
            'match' => 'plans.*',
            'icon'  => 'fa-solid fa-layer-group',
        ],
        [
            'label' => 'Investments',
            'route' => 'investments.index',
            'match' => 'investments.*',
            'icon'  => 'fa-solid fa-chart-line',
        ],
        [
            'label' => 'Referrals',
            'route' => 'referrals',
            'match' => 'referrals',
            'icon'  => 'fa-solid fa-users',
        ],
        [
            'label' => 'Wallet',
            'route' => 'deposits.create',
            'match' => 'deposits.*',
            'icon'  => 'fa-solid fa-wallet',
        ],
        [
            'label' => 'Profile',
            'route' => 'profile.edit',
            'match' => 'profile.*',
            'icon'  => 'fa-solid fa-user',
        ],
    ];
@endphp

<div class="fixed bottom-0 inset-x-0 z-40 lg:hidden border-t border-white/10 bg-ink-950/95 backdrop-blur-md px-2 py-2 shadow-2xl">
    <nav class="flex items-center justify-around" aria-label="Mobile Navigation">
        @foreach ($items as $item)
            @php
                $active = request()->routeIs($item['match']);
            @endphp
            <a href="{{ route($item['route']) }}"
               @class([
                   'flex flex-col items-center gap-1 px-3 py-1.5 rounded-xl transition text-[11px] font-medium min-w-[60px] text-center',
                   'text-brand-400 font-semibold bg-brand-500/10' => $active,
                   'text-slate-400 hover:text-white' => !$active,
               ])>
                <i class="{{ $item['icon'] }} text-base {{ $active ? 'text-brand-400' : 'text-slate-400' }}"></i>
                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
