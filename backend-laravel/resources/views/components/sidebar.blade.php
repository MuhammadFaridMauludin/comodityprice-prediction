{{-- Tampil di desktop (>= 1024px). Disembunyikan di mobile lewat CSS. --}}
<aside class="sidebar glass">
    <x-brand />

    <nav class="sidebar__nav" aria-label="Menu utama">
        @foreach (config('menu') as $item)
            <a href="{{ route($item['route']) }}"
                class="sidebar__link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}"
                @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                <x-icon :name="$item['icon']" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="sidebar__user">
        <x-avatar :name="auth()->user()->name ?? 'Muhammad Farid'" :src="asset('images/avatar.jpg')" />
        <div class="sidebar__user-text">
            <strong>{{ auth()->user()->name ?? 'Muhammad Farid' }}</strong>
            <small>Analis Komoditas</small>
        </div>
        {{-- <a href="{{ route('notif') }}" class="icon-btn" aria-label="Notifikasi"><x-icon name="bell" :size="18" /></a> --}}
    </div>
</aside>
