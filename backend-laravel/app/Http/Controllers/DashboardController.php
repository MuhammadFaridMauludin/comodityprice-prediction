<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            // ['href' => route('notif'), 'icon' => 'bell', 'title' => 'Notifikasi Harga',
            //  'subtitle' => '2 peringatan ambang harga aktif'],
        ];

        return view('dashboard.dashboard', [
            'prediction'  => $prediction,
            'labels'      => $labels,
            'datasets'    => [['label' => 'Harga', 'values' => $trend, 'color' => '#1a5cff']],
            'commodities' => $commodities,
            'links'       => $links,
        ]);
    }

    public function dashboardadmin(Request $request)
    {
        $logs = SystemLog::query()
            ->when($request->proses, fn ($q, $v) => $q->where('process', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest('logged_at')
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.dashboardadmin', [
            'pipeline'  => $this->pipeline(),
            'updatedAt' => now(),
            'logs'      => $logs,
            'selects'   => [
                'proses' => SystemLog::distinct()->orderBy('process')->pluck('process')->all(),
                'status' => ['sukses', 'peringatan', 'gagal'],
            ],
        ]);
    }

    private function pipeline(): array
    {
        return [
            $this->statusSumberLog('Siskaperbapo', 'building-store'),
            $this->statusOpenMeteo(),
            $this->statusDatabase(),
            $this->statusLayananMl(),
        ];
    }

    /** Status berdasarkan log terakhir script scraping. */
    private function statusSumberLog(string $sumber, string $icon): array
    {
        $log = DB::table('system_logs')
            ->where('source', $sumber)
            ->orderByDesc('logged_at')
            ->first();

        if (!$log) {
            return [
                'label' => strtoupper($sumber),
                'value' => 'Belum ada data',
                'icon'  => $icon,
                'color' => 'secondary',
                'note'  => 'Belum pernah sinkron',
            ];
        }

        $waktu = Carbon::parse($log->logged_at);

        [$value, $color] = match ($log->status) {
            'sukses'     => ['Normal', 'green'],
            'peringatan' => ['Perhatian', 'orange'],
            default      => ['Gagal', 'red'],
        };

        // Sukses tapi sudah lama tidak jalan = tertunda
        if ($log->status === 'sukses' && $waktu->lt(now()->subHours(36))) {
            [$value, $color] = ['Tertunda', 'orange'];
        }

        return [
            'label' => strtoupper($sumber),
            'value' => $value,
            'icon'  => $icon,
            'color' => $color,
            'note'  => 'Sinkron ' . $waktu->diffForHumans(),
        ];
    }

    /** Ping Open-Meteo, di-cache 5 menit supaya dashboard tidak lambat. */
    private function statusOpenMeteo(): array
    {
        $hasil = Cache::remember('pipeline.open_meteo', 300, function () {
            $mulai = microtime(true);

            try {
                $res = Http::timeout(8)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude'  => -7.25,
                    'longitude' => 112.75,
                    'current'   => 'temperature_2m',
                ]);
                $ok = $res->successful();
            } catch (\Throwable $e) {
                $ok = false;
            }

            return ['ok' => $ok, 'detik' => round(microtime(true) - $mulai, 1)];
        });

        if (!$hasil['ok']) {
            [$value, $color] = ['Gagal', 'red'];
            $note = 'API tidak merespons';
        } else {
            [$value, $color] = $hasil['detik'] > 2 ? ['Lambat', 'orange'] : ['Normal', 'green'];
            $note = 'Respons ' . number_format($hasil['detik'], 1, ',', '.') . ' dtk';
        }

        return [
            'label' => 'OPEN-METEO',
            'value' => $value,
            'icon'  => 'cloud',
            'color' => $color,
            'note'  => $note,
        ];
    }

    private function statusDatabase(): array
    {
        try {
            $mulai = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $mulai) * 1000);

            return [
                'label' => 'DATABASE',
                'value' => 'Terhubung',
                'icon'  => 'database',
                'color' => $ms > 200 ? 'orange' : 'green',
                'note'  => "Latensi {$ms} ms",
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'DATABASE',
                'value' => 'Terputus',
                'icon'  => 'database',
                'color' => 'red',
                'note'  => 'Tidak dapat terhubung',
            ];
        }
    }

    /** Cek layanan FastAPI lewat /health, di-cache 60 detik. */
    /** Cek layanan FastAPI lewat /health. Hanya keberhasilan yang di-cache. */
private function statusLayananMl(): array
{
    $base = rtrim((string) config('services.ml_service.url'), '/');

    if ($base === '') {
        return [
            'label' => 'LAYANAN ML',
            'value' => 'Belum diatur',
            'icon'  => 'server-off',
            'color' => 'orange',
            'note'  => 'ML_SERVICE_URL kosong di .env',
        ];
    }

    $data = Cache::get('pipeline.ml_service');
    $alasan = null;

    if ($data === null) {
        try {
            $res = Http::timeout(3)->get($base . '/health');

            if ($res->successful()) {
                $data = $res->json();
                Cache::put('pipeline.ml_service', $data, 60); // simpan hanya jika sukses
            } else {
                $alasan = 'HTTP ' . $res->status();
            }
        } catch (\Throwable $e) {
            $alasan = 'Tidak dapat terhubung';
            Log::warning('Cek layanan ML gagal: ' . $e->getMessage());
        }
    }

    if ($data === null) {
        return [
            'label' => 'LAYANAN ML',
            'value' => 'Mati',
            'icon'  => 'server-off',
            'color' => 'red',
            'note'  => $alasan,
        ];
    }

    $sibuk = ($data['scraping_harga'] ?? false) || ($data['scraping_cuaca'] ?? false);

    return [
        'label' => 'LAYANAN ML',
        'value' => $sibuk ? 'Sibuk' : 'Aktif',
        'icon'  => 'server',
        'color' => $sibuk ? 'blue' : 'green',
        'note'  => $sibuk ? 'Scraping sedang berjalan' : 'Siap menerima tugas',
    ];
}
}