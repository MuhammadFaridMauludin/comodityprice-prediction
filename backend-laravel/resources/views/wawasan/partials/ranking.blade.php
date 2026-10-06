{{-- Daftar peringkat komoditas. Butuh: $title, $items (name, price, change) --}}
<section class="card rank">
    <h2 class="rank__title">{{ $title }}</h2>

    <ul class="rank__list">
        @foreach ($items as $item)
            <li>
                <span class="rank__name">{{ $item['name'] }}</span>
                <span class="rank__price">Rp {{ number_format($item['price'], 0, ',', '.') }}</span>
                <x-change-badge :value="$item['change']" />
            </li>
        @endforeach
    </ul>
</section>
