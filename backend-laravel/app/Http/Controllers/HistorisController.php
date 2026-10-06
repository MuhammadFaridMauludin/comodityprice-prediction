<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class HistorisController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request)
    {
        $commodities = $this->commodities();
        $slug = $request->query('komoditas', 'gabah');
        if (! array_key_exists($slug, $commodities)) {
            $slug = array_key_first($commodities);
        }

        $series = $this->series($slug, $commodities[$slug]['base']);
        $min    = $series->first()['date'];
        $max    = $series->last()['date'];

        // Rentang tanggal: default 30 hari terakhir. Input tidak valid diganti nilai aman.
        $sampai = $this->parseDate($request->query('sampai'), $max, $min, $max);
        $dari   = $this->parseDate($request->query('dari'), $max->copy()->subDays(29), $min, $max);
        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $inRange = $series->filter(fn ($r) => $r['date']->between($dari, $sampai))->values();
        $total   = $inRange->count();
        $average = $total ? (int) round($inRange->avg('price')) : 0;

        // Pagination: hanya PER_PAGE baris yang dikirim ke tampilan.
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page     = min(max(1, LengthAwarePaginator::resolveCurrentPage()), $lastPage);

        $items = $inRange->forPage($page, self::PER_PAGE)->map(fn ($r) => [
            'date'  => $r['date']->translatedFormat('d M Y'),
            'price' => $r['price'],
            'diff'  => $r['diff'],
        ])->values();

        $rows = (new LengthAwarePaginator($items, $total, self::PER_PAGE, $page, ['path' => $request->url()]))
            ->onEachSide(1)
            ->withQueryString();

        return view('historis.index', [
            'tabs'    => collect($commodities)->map(fn ($c) => $c['name'])->all(),
            'active'  => $slug,
            'rows'    => $rows,
            'total'   => $total,
            'average' => $average,
            'dari'    => $dari->toDateString(),
            'sampai'  => $sampai->toDateString(),
            'min'     => $min->toDateString(),
            'max'     => $max->toDateString(),
        ]);

        /*
         * VERSI DATABASE (yang benar-benar meringankan loading):
         *
         *   $q = Harga::where('commodity_id', $id)->whereBetween('tanggal', [$dari, $sampai]);
         *   $average = (clone $q)->avg('harga');
         *   $rows    = $q->orderBy('tanggal')->paginate(15)->withQueryString();
         *
         * Dengan paginate(), database hanya mengambil 15 baris per halaman.
         */
    }

    private function parseDate(?string $value, Carbon $fallback, Carbon $min, Carbon $max): Carbon
    {
        try {
            $date = $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : $fallback->copy();
        } catch (\Throwable $e) {
            $date = $fallback->copy();
        }

        return $date->lt($min) ? $min->copy() : ($date->gt($max) ? $max->copy() : $date);
    }

    /** Data contoh: satu harga per hari sejak 1 Jan 2025 sampai hari ini. Ganti dengan database. */
    private function series(string $slug, int $base): Collection
    {
        $start = Carbon::create(2025, 1, 1)->startOfDay();
        $days  = (int) $start->diffInDays(now()->startOfDay());
        $out   = collect();
        $prev  = null;

        for ($i = 0; $i <= $days; $i++) {
            $noise = (abs(crc32($slug.$i)) % 21) - 10;                 // -10 .. 10
            $price = $base + $base * 0.03 * sin($i / 9) + $noise * ($base / 1000);
            $price = (int) (round($price / 50) * 50);

            $out->push([
                'date'  => $start->copy()->addDays($i),
                'price' => $price,
                'diff'  => $prev ? ($price - $prev) / $prev * 100 : 0,
            ]);
            $prev = $price;
        }

        return $out;
    }

    private function commodities(): array
    {
        return [
            'gabah'         => ['name' => 'Gabah',         'base' => 6100],
            'beras-premium' => ['name' => 'Beras Premium', 'base' => 12700],
            'minyak-goreng' => ['name' => 'Minyak Goreng', 'base' => 18800],
            'gula-pasir'    => ['name' => 'Gula Pasir',    'base' => 17000],
            'cabai-merah'   => ['name' => 'Cabai Merah',   'base' => 52000],
        ];
    }
}
