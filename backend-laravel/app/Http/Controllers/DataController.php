<?php

namespace App\Http\Controllers;

use App\Models\Harga;
use App\Models\Komoditas;
use App\Models\Wilayah;
use App\Models\cuaca;
use App\Models\koordinatcuaca;
use App\Support\DummyData;
use App\Support\DummyUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

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

        return view('data.index', [
            'type'    => 'harga',
            'rows'    => $rows,
            'selects' => $selects,
            'columns' => [
                'No', 'Tanggal', 'Komoditas', 'Wilayah',
                ['label' => 'Harga', 'align' => 'right'],
                // 'status',
                // ['label' => 'Aksi', 'align' => 'right'],
            ],
        ]);
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

        return view('data.index', [
            'type'    => 'cuaca',
            'rows'    => $rows,
            'selects' => $selects,
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
}