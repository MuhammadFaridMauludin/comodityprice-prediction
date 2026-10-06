<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PrediksiController extends Controller
{
    public function index(Request $request)
    {
        $all  = $this->dummyData();
        $slug = $request->query('komoditas', 'gabah');

        // Jika slug tidak dikenal, pakai komoditas pertama.
        if (! array_key_exists($slug, $all)) {
            $slug = array_key_first($all);
        }
        $c = $all[$slug];

        // 8 hari terakhir (aktual) dan 7 hari ke depan (prediksi)
        $today         = now()->startOfDay();
        $actualDates   = collect(range(7, 0))->map(fn ($i) => $today->copy()->subDays($i));
        $forecastDates = collect(range(1, 7))->map(fn ($i) => $today->copy()->addDays($i));

        $labels = $actualDates->merge($forecastDates)
            ->map(fn ($d) => $d->translatedFormat('d M'))->values()->all();

        $lastActual = end($c['actual']);

        // Garis aktual: 8 nilai + 7 kosong. Garis prediksi: 7 kosong + titik sambung + 7 prediksi.
        $actual   = array_merge($c['actual'], array_fill(0, 7, null));
        $forecast = array_merge(array_fill(0, 7, null), [$lastActual], $c['forecast']);

        // Tabel: selisih terhadap hari sebelumnya (hari pertama dibandingkan dengan data aktual terakhir)
        $rows = [];
        $prev = $lastActual;
        foreach ($c['forecast'] as $i => $price) {
            $rows[] = [
                'date'  => $forecastDates[$i]->translatedFormat('d M'),
                'price' => $price,
                'diff'  => ($price - $prev) / $prev * 100,
            ];
            $prev = $price;
        }

        return view('prediksi.index', [
            'tabs'      => collect($all)->map(fn ($item) => $item['name'])->all(),
            'active'    => $slug,
            'name'      => $c['name'],
            'modelName' => 'GRU',
            'labels'    => $labels,
            'datasets'  => [
                ['label' => 'Aktual',   'values' => $actual,   'color' => '#1a5cff'],
                ['label' => 'Prediksi', 'values' => $forecast, 'color' => '#22d3e6', 'dashed' => true],
            ],
            'rows'      => $rows,
        ]);
    }

    /** Data contoh. Ganti dengan query database / hasil model prediksi. */
    private function dummyData(): array
    {
        return [
            'gabah' => ['name' => 'Gabah',
                'actual'   => [6000, 6020, 6060, 6050, 6080, 6100, 6090, 6100],
                'forecast' => [6150, 6200, 6200, 6250, 6200, 6200, 6200]],
            'beras-premium' => ['name' => 'Beras Premium',
                'actual'   => [12600, 12620, 12650, 12700, 12680, 12720, 12750, 12800],
                'forecast' => [12850, 12900, 12880, 12950, 13000, 13020, 13050]],
            'minyak-goreng' => ['name' => 'Minyak Goreng',
                'actual'   => [18800, 18750, 18700, 18650, 18600, 18580, 18550, 18500],
                'forecast' => [18450, 18420, 18400, 18380, 18350, 18350, 18300]],
            'gula-pasir' => ['name' => 'Gula Pasir',
                'actual'   => [17000, 17020, 17050, 17100, 17080, 17150, 17180, 17200],
                'forecast' => [17250, 17300, 17280, 17350, 17400, 17420, 17450]],
            'cabai-merah' => ['name' => 'Cabai Merah',
                'actual'   => [58000, 56500, 55200, 54800, 53500, 53000, 52400, 52000],
                'forecast' => [51000, 50200, 49500, 50000, 49200, 48800, 48500]],
        ];
    }
}
