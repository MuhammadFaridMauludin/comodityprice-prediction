{{-- Kartu model terbaik + diagram batang RMSE. Butuh: $models, $best, $maxRmse --}}
<section class="card best">
    <h2 class="best__title">Model terbaik: {{ $best['name'] }}</h2>
    <p class="best__desc">
        Dipilih berdasarkan RMSE terendah ({{ number_format($best['rmse'], 1, ',', '.') }})
        dengan MAPE {{ number_format($best['mape'], 2, ',', '.') }}%
        dan R² {{ number_format($best['r2'], 3, ',', '.') }}.
    </p>

    <ul class="bars">
        @foreach ($models as $m)
            <li class="bars__row {{ $m['name'] === $best['name'] ? 'is-best' : '' }}">
                <span class="bars__label">{{ $m['name'] }}</span>
                <span class="bars__track">
                    <span class="bars__fill" style="--w: {{ ($m['rmse'] / $maxRmse) * 100 }}%"></span>
                </span>
                <span class="bars__value">{{ number_format($m['rmse'], 1, ',', '.') }}</span>
            </li>
        @endforeach
    </ul>
</section>
