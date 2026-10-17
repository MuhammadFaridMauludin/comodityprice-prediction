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

        return view('data.index', [
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

        return view('data.index', [
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
}