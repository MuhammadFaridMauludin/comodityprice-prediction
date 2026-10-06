{{-- Kartu pintasan ke halaman lain. Butuh: $links (href, icon, title, subtitle) --}}
<div class="dash__links">
    @foreach ($links as $l)
        <a href="{{ $l['href'] }}" class="card link-card">
            <span class="link-card__icon"><x-icon :name="$l['icon']" :size="24" /></span>
            <span class="link-card__text">
                <strong>{{ $l['title'] }}</strong>
                <small>{{ $l['subtitle'] }}</small>
            </span>
            <x-icon name="chevron" :size="20" class="link-card__arrow" />
        </a>
    @endforeach
</div>
