{{-- Empat kartu harga komoditas. Butuh: $commodities (name, price, change) --}}
<div class="dash__commodities">
    @foreach ($commodities as $c)
        <article class="card commodity">
            <p class="commodity__name">{{ $c['name'] }}</p>
            <p class="commodity__price">Rp {{ number_format($c['price'], 0, ',', '.') }}</p>
            <x-change-badge :value="$c['change']" suffix="hari ini" />
        </article>
    @endforeach
</div>
