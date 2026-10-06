{{-- Kartu prediksi 7 hari. Butuh: $prediction (commodity, price, change, series) --}}
@php
    $series = $prediction['series'];
    $min    = min($series);
    $range  = max(max($series) - $min, 1);
    $change = $prediction['change'];
@endphp

<section class="card prediction dash__prediction">
    <p class="prediction__label">Prediksi 7 hari · {{ $prediction['commodity'] }}</p>

    <div class="prediction__price">
        <strong>Rp {{ number_format($prediction['price'], 0, ',', '.') }}</strong>
        <span class="pill {{ $change >= 0 ? 'pill--up' : 'pill--down' }}">
            {{ $change >= 0 ? '▲' : '▼' }} {{ number_format(abs($change), 1, ',', '.') }}%
        </span>
    </div>

    <div class="prediction__bars" role="img" aria-label="Grafik batang prediksi 7 hari">
        @foreach ($series as $i => $v)
            <span class="{{ $i >= count($series) - 2 ? 'is-hot' : '' }}"
                  style="--h: {{ 35 + (($v - $min) / $range) * 65 }}%; --o: {{ 0.28 + $i * 0.11 }}"
                  title="Hari {{ $i + 1 }}: Rp {{ number_format($v, 0, ',', '.') }}"></span>
        @endforeach
    </div>
</section>
