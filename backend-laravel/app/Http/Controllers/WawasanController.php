<?php

namespace App\Http\Controllers;

class WawasanController extends Controller
{
    public function index()
    {
        // 30 hari terakhir sampai hari ini
        $days   = collect(range(29, 0))->map(fn ($i) => now()->startOfDay()->subDays($i));
        $labels = $days->map(fn ($d) => $d->translatedFormat('d M'))->values()->all();

        // Data contoh untuk grafik. Ganti dengan data harga dari database.
        $beras = $days->keys()->map(fn ($i) => 12500 + $i * 10 + (int) (sin($i / 3) * 40))->all();
        $cabai = $days->keys()->map(fn ($i) => 49000 + $i * 100 + (int) (sin($i / 2.5) * 350))->all();

        // Data contoh perubahan harga hari ini. Ganti dengan data asli.
        $commodities = collect([
            ['name' => 'Gabah',         'price' => 6450,  'change' => 2.4],
            ['name' => 'Bawang Merah',  'price' => 38400, 'change' => 1.9],
            ['name' => 'Beras Premium', 'price' => 12800, 'change' => 1.2],
            ['name' => 'Gula Pasir',    'price' => 17200, 'change' => 0.6],
            ['name' => 'Minyak Goreng', 'price' => 18500, 'change' => -0.8],
            ['name' => 'Cabai Merah',   'price' => 52000, 'change' => -3.1],
        ])->sortByDesc('change')->values();

        return view('wawasan.index', [
            'labels'   => $labels,
            'datasets' => [
                ['label' => 'Beras Premium', 'values' => $beras, 'color' => '#1a5cff'],
                ['label' => 'Cabai Merah',   'values' => $cabai, 'color' => '#22d3e6'],
            ],
            'gainers'  => $commodities->take(3)->all(),                       // 3 tertinggi
            'losers'   => $commodities->reverse()->take(3)->values()->all(),  // 3 terendah
            'analysis' => 'Harga cabai merah paling fluktuatif dalam 30 hari terakhir, '
                        . 'sementara beras premium bergerak stabil dengan tren naik tipis. '
                        . 'Model BiLSTM dan GRU memberi galat paling kecil pada komoditas '
                        . 'dengan volatilitas tinggi.',
        ]);
    }
}