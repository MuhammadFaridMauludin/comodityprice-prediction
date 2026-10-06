{{-- Tampil di mobile/tablet (< 1024px). --}}
<nav class="bottom-nav glass" aria-label="Menu utama">
    @foreach (config('menu') as $item)
        <a href="{{ route($item['route']) }}"
           class="bottom-nav__link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}"
           @if(request()->routeIs($item['route'])) aria-current="page" @endif>
            <x-icon :name="$item['icon']" :size="22" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
