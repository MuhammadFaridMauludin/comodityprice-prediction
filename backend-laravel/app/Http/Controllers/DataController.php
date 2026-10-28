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
private const PER_PAGE = 10;

public function index(Request $request)
{
    $komoditas = Komoditas::orderBy('id_komoditas')->pluck('nama_komoditas', 'id_komoditas');
    abort_if($komoditas->isEmpty(), 404);

    // Komoditas aktif: ?komoditas=ID, kalau tidak valid pakai yang pertama
    $active = (int) $request->query('komoditas');
    if (! $komoditas->has($active)) {
        $active = $komoditas->keys()->first();
    }

    // Batas tanggal data untuk komoditas ini (kalau kosong, pakai hari ini)
    $minRaw = Harga::where('id_komoditas', $active)->min('tanggal');
    $maxRaw = Harga::where('id_komoditas', $active)->max('tanggal');
    $today  = now()->startOfDay();
    $min    = $minRaw ? Carbon::parse($minRaw)->startOfDay() : $today;
    $max    = $maxRaw ? Carbon::parse($maxRaw)->startOfDay() : $today;

    // Default 30 hari terakhir. Input tidak valid diganti nilai aman.
    $sampai = $this->parseDate($request->query('sampai'), $max, $min, $max);
    $dari   = $this->parseDate($request->query('dari'), $max->copy()->subDays(29), $min, $max);
    if ($dari->gt($sampai)) {
        [$dari, $sampai] = [$sampai, $dari];
    }

    $query = Harga::with('wilayah')
        ->where('id_komoditas', $active)
        ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);

    $total   = (clone $query)->count();
    $average = (int) round((clone $query)->avg('harga'));

    // Database hanya mengambil PER_PAGE baris per halaman.
    // Harga sebelumnya lewat subquery supaya Δ baris pertama juga benar.
    $rows = $query
        ->select('data_harga.*')
        ->selectSub(function ($s) {
            $s->from('data_harga as p')
                ->select('p.harga')
                ->whereColumn('p.id_komoditas', 'data_harga.id_komoditas')
                ->whereColumn('p.id_wilayah', 'data_harga.id_wilayah')
                ->whereColumn('p.tanggal', '<', 'data_harga.tanggal')
                ->orderByDesc('p.tanggal')
                ->limit(1);
        }, 'harga_sebelumnya')
        ->orderBy('tanggal', 'desc')
        ->paginate(self::PER_PAGE)
        ->onEachSide(1)
        ->withQueryString();

    $rows->through(fn ($r) => [
        'date'    => Carbon::parse($r->tanggal)->translatedFormat('d M Y'),
        'wilayah' => $r->wilayah->nama_wilayah ?? '-',
        'price'   => (int) $r->harga,
        'diff'    => $r->harga_sebelumnya
            ? ($r->harga - $r->harga_sebelumnya) / $r->harga_sebelumnya * 100
            : 0,
    ]);

    return view('data.index', [
        'tabs'    => $komoditas->all(),   // [id_komoditas => nama]
        'active'  => $active,
        'rows'    => $rows,
        'total'   => $total,
        'average' => $average,
        'dari'    => $dari->toDateString(),
        'sampai'  => $sampai->toDateString(),
        'min'     => $min->toDateString(),
        'max'     => $max->toDateString(),
    ]);
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
}