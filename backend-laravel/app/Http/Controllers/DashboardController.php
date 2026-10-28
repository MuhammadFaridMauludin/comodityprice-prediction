<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Harga;
use App\Models\Komoditas;


class DashboardController extends Controller
{
    private const HORIZON = 30;
    public function index(Request $request)
{
    
    $komoditas = Komoditas::orderBy('id_komoditas')->get();
    abort_if($komoditas->isEmpty(), 404);

    // Komoditas utama: ?komoditas=ID, default yang pertama
    $utama = $komoditas->firstWhere('id_komoditas', (int) $request->query('komoditas'))
        ?? $komoditas->first();

    // Tren: 30 data harga terakhir milik komoditas utama
    $tren = Harga::where('id_komoditas', $utama->id_komoditas)
        ->orderByDesc('tanggal')
        ->limit(30)
        ->get(['tanggal', 'harga'])
        ->reverse()
        ->values();

    $labels   = $tren->map(fn ($r) => Carbon::parse($r->tanggal)->translatedFormat('d M'))->all();
    $values   = $tren->map(fn ($r) => (int) $r->harga)->all();
    $terakhir = (int) ($tren->last()->harga ?? 0);
    $tglAkhir = $tren->last()->tanggal ?? null;

    // Prediksi: hasil run terbaru (id_evaluasi terbesar), 7 hari setelah data aktual terakhir
    $series = [];
    if ($tglAkhir) {
        $idEval = DB::table('hasil_prediksi')
            ->where('id_komoditas', $utama->id_komoditas)
            ->max('id_evaluasi');

        if ($idEval) {
            $series = DB::table('hasil_prediksi')
                ->where('id_komoditas', $utama->id_komoditas)
                ->where('id_evaluasi', $idEval)
                ->whereDate('tanggal_prediksi', '>', $tglAkhir)
                ->orderBy('tanggal_prediksi')
                ->limit(self::HORIZON)
                ->pluck('harga_prediksi')
                ->map(fn ($v) => (int) round($v))
                ->all();
        }
    }

    $hargaPrediksi = $series ? end($series) : $terakhir;

    $prediction = [
        'commodity' => $utama->nama_komoditas,
        'price'     => $hargaPrediksi,
        'change'    => $terakhir ? round(($hargaPrediksi - $terakhir) / $terakhir * 100, 1) : 0,
        'series'    => $series,
    ];

    // Daftar komoditas: harga terbaru + perubahan dari data sebelumnya
    $commodities = $komoditas->map(function ($k) {
        $dua  = Harga::where('id_komoditas', $k->id_komoditas)
            ->orderByDesc('tanggal')->limit(2)->pluck('harga');
        $now  = (int) ($dua[0] ?? 0);
        $prev = (int) ($dua[1] ?? 0);

        return [
            'name'   => $k->nama_komoditas,
            'price'  => $now,
            'change' => $prev ? round(($now - $prev) / $prev * 100, 1) : 0,
        ];
    })->all();

    // Model terbaik (MAPE terendah) untuk komoditas utama
    $terbaik = DB::table('evaluasi_model as e')
        ->join('model as m', 'm.id_model', '=', 'e.id_model')
        ->where('e.id_komoditas', $utama->id_komoditas)
        ->whereNotNull('e.mape')
        ->orderBy('e.mape')
        ->first(['m.nama_model', 'e.rmse', 'e.mape']);

    $links = $terbaik ? [[
        'href'     => route('evaluasi'),
        'icon'     => 'gauge',
        'title'    => 'Model terbaik: '.$terbaik->nama_model,
        'subtitle' => 'RMSE '.number_format($terbaik->rmse, 1, ',', '.')
                    .' · MAPE '.number_format($terbaik->mape, 2, ',', '.').'%',
    ]] : [];

    return view('dashboard.dashboard', [
        'prediction'  => $prediction,
        'labels'      => $labels,
        'datasets'    => [['label' => 'Harga', 'values' => $values, 'color' => '#1a5cff']],
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