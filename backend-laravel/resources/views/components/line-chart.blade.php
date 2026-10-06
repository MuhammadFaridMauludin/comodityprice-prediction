@props(['title', 'subtitle' => '', 'id' => null, 'labels' => [], 'datasets' => [], 'legend' => false, 'zero' => false])

{{--
    Grafik garis serba guna (satu garis atau lebih).
    $datasets = [['label' => 'Aktual', 'values' => [...], 'color' => '#1a5cff', 'dashed' => false], ...]
    Digambar oleh partials/script.blade.php.
--}}
@php $id = $id ?? 'chart-'.\Illuminate\Support\Str::random(6); @endphp

<section {{ $attributes->class(['card', 'chart']) }}>
    <header class="chart__head">
        <h2>{{ $title }}</h2>
        <span>{{ $subtitle }}</span>
    </header>

    <div class="chart__canvas">
        <canvas id="{{ $id }}" data-line-chart data-zero="{{ $zero ? 1 : 0 }}"
                data-labels='@json($labels)' data-datasets='@json($datasets)'></canvas>
    </div>

    @if ($legend)
        <ul class="legend">
            @foreach ($datasets as $d)
                <li>
                    <i class="legend__line {{ ($d['dashed'] ?? false) ? 'legend__line--dashed' : '' }}"
                       style="--c: {{ $d['color'] ?? '#1a5cff' }}"></i>
                    {{ $d['label'] ?? '' }}
                </li>
            @endforeach
        </ul>
    @endif
</section>
