<?php

namespace App\Http\Controllers;

use App\Models\Harga;
use App\Models\Komoditas;
use App\Models\Wilayah;
use App\Models\Cuaca;
use App\Models\KoordinatCuaca;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DataController extends Controller
{
    public function harga(Request $request)
    {
        //query dengan Eager Loading untuk memanggil relasi
        $query = Harga::with(['komoditas', 'wilayah']);
        //Filter komoditas
        $query->when($request->filled('komoditas'), function ($q) use ($request) {
            $q->where('id_komoditas', $request->komoditas);
        });
        //filter wilayah
        $query->when($request->filled('wilayah'), function ($q) use ($request) {
            $q->where('id_wilayah', $request->wilayah);
        });
        // Filter tgl mulai
        $query->when($request->filled('tgl_mulai'), function ($q) use ($request) {
        $q->whereDate('tanggal', '>=', $request->tgl_mulai);
        });
        // Filter tgl selesai
        $query->when($request->filled('tgl_selesai'), function ($q) use ($request) {
        $q->whereDate('tanggal', '<=', $request->tgl_selesai);
        });
        //pagination tgl terbaru
        $rows = $query->orderBy('tanggal', 'desc')->paginate(10)->withQueryString();
        //pilihan dropdown
        $selects = [
            'komoditas' => Komoditas::pluck('nama_komoditas', 'id_komoditas'),
            'wilayah'   => Wilayah::pluck('nama_wilayah', 'id_wilayah'),
        ];

        $terakhir = Harga::max('tanggal');

        return view('data.indexadmin', [
            'type'    => 'harga',
            'rows'    => $rows,
            'selects' => $selects,
            'scrape'  => [
                'route'    => route('harga.scrape'),
                'label'    => 'Scraping Harga',
                'terakhir' => $terakhir,
            ],
            'columns' => [
                'No', 'Tanggal', 'Komoditas', 'Wilayah',
                ['label' => 'Harga', 'align' => 'right'],
            ],
        ]);
    }
    public function scrapeHarga()
    {
        try {
            $res = Http::withHeaders(['X-API-Key' => config('services.ml_service.key')])
                ->timeout(10)
                ->post(config('services.ml_service.url') . '/scrape/harga');
        if ($res->failed()) {
            dd([
                'status_code' => $res->status(),
                'response_body' => $res->body(),
            ]);
        }
        } catch (ConnectionException $e) {
            return back()->with('error', 'ML service tidak bisa dihubungi. Pastikan sudah dijalankan.');
        }

        if ($res->status() === 409) {
            return back()->with('warning', 'Scraping masih berjalan, tunggu sampai selesai.');
        }

        if ($res->failed()) {
            return back()->with('error', 'Gagal memulai scraping.');
        }

        return back()->with('success', 'Scraping dimulai. Pantau hasilnya di Log Sistem.');
    }
    public function cuaca(Request $request)
    {
        //query dengan Eager Loading untuk memanggil relasi
        $query = Cuaca::with(['koordinat']);
        //Filter koordinat
        $query->when($request->filled('koordinat_cuaca'), function ($q) use ($request) {
            $q->where('id_koordinat', $request->koordinat_cuaca);
        });
        // Filter tgl mulai
        $query->when($request->filled('tgl_mulai'), function ($q) use ($request) {
        $q->whereDate('tanggal', '>=', $request->tgl_mulai);
        });
        // Filter tgl selesai
        $query->when($request->filled('tgl_selesai'), function ($q) use ($request) {
        $q->whereDate('tanggal', '<=', $request->tgl_selesai);
        });
        //pagination tgl terbaru
        $rows = $query->orderBy('tanggal', 'desc')->paginate(10)->withQueryString();
        //pilihan dropdown
        $selects = [
            'koordinat_cuaca' => KoordinatCuaca::pluck('nama_koordinat', 'id_koordinat'),
        ];
        $terakhir = Cuaca::max('tanggal');

        return view('data.indexadmin', [
            'type'    => 'cuaca',
            'rows'    => $rows,
            'selects' => $selects,
            'scrape'  => [
            'route'    => route('cuaca.scrape'), // pastikan route ini sudah didefinisikan
            'label'    => 'Scraping Cuaca',
            'terakhir' => $terakhir,
        ],
            'columns' => [
                'No',
                'Tanggal',
                'Lokasi / Koordinat',
                ['label' => 'Suhu', 'align' => 'right'],
                ['label' => 'Curah Hujan', 'align' => 'right'],
                ['label' => 'Kelembapan', 'align' => 'right'],
                // 'Status',
                // ['label' => 'Aksi', 'align' => 'right'],
            ],
        ]);
    }
    public function scrapeCuaca()
{
    try {
        $res = Http::withHeaders(['X-API-Key' => config('services.ml_service.key')])
            ->timeout(10)
            ->post(config('services.ml_service.url') . '/scrape/cuaca');
    } catch (ConnectionException $e) {
        return back()->with('error', 'ML service tidak bisa dihubungi.');
    }

    if ($res->status() === 409) {
        return back()->with('warning', 'Scraping cuaca masih berjalan.');
    }

    if ($res->failed()) {
        return back()->with('error', 'Gagal memulai scraping cuaca.');
    }

    return back()->with('success', 'Scraping cuaca dimulai.');
}
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

        return view('data.index', [
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