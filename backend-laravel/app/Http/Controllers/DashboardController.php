<?php

namespace App\Http\Controllers;

use App\Support\DummyData;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Data dummy. Ganti dengan query ke database / hasil model prediksi.
        $prediction = [
            'commodity' => 'Gabah',
            'price'     => 6200,
            'change'    => 2.4,
            'series'    => [6050, 6080, 6110, 6130, 6160, 6185, 6200], // 7 hari ke depan
        ];

        $labels = collect(range(0, 29))->map(fn ($i) => now()->startOfYear()->addDays($i)->translatedFormat('d M'))->all();
        $trend  = collect(range(0, 29))->map(fn ($i) => 6000 + (int) (sin($i / 4) * 80) + $i * 5)->all();

        $commodities = [
            ['name' => 'Beras Premium', 'price' => 12800, 'change' => 1.2],
            ['name' => 'Minyak Goreng', 'price' => 18500, 'change' => -0.8],
            ['name' => 'Gula Pasir',    'price' => 17200, 'change' => 0.6],
            ['name' => 'Cabai Merah',   'price' => 52000, 'change' => -3.1],
        ];

        $links = [
            ['href' => route('evaluasi'), 'icon' => 'gauge', 'title' => 'Model terbaik: GRU',
             'subtitle' => 'RMSE '.number_format(195.6, 1, ',', '.').' · MAPE '.number_format(2.33, 2, ',', '.').'%'],
            ['href' => route('notif'), 'icon' => 'bell', 'title' => 'Notifikasi Harga',
             'subtitle' => '2 peringatan ambang harga aktif'],
        ];

        return view('dashboard.index', [
            'prediction'  => $prediction,
            'labels'      => $labels,
            'datasets'    => [['label' => 'Harga', 'values' => $trend, 'color' => '#1a5cff']],
            'commodities' => $commodities,
            'links'       => $links,
        ]);
    }

    public function dashboardadmin(Request $request)
    {
        $logs = DummyData::filter(
            DummyData::logs(),
            $request,
            ['proses' => 'process', 'status' => 'status']
        );

        return view('dashboard.dashboardadmin', [
            'pipeline'  => DummyData::pipeline(),
            'updatedAt' => now(),
            'logs'      => DummyData::paginate($logs, $request, 10),
            'selects'   => [
                'proses' => DummyData::processes(),
                'status' => ['sukses', 'peringatan', 'gagal'],
            ],
        ]);
    }
}