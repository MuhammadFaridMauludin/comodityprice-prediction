{{-- Tampil di mobile/tablet (< 1024px). --}}
<header class="topbar glass">
    <x-brand />
    <div class="topbar__actions">
        {{-- <a href="{{ route('notif') }}" class="icon-btn" aria-label="Notifikasi"><x-icon name="bell" :size="20" /></a>
        <a href="{{ route('profil') }}" aria-label="Profil"> --}}
        <x-avatar :name="auth()->user()->name ?? 'Muhammad Farid'" :src="asset('images/avatar.jpg')" :size="40" />
        </a>
    </div>
</header>
