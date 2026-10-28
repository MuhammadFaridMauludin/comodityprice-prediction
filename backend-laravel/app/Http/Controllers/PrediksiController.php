<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Harga;
use App\Models\Komoditas;


class PrediksiController extends Controller
{
  public function index(Request $request)
{

    $komoditas = Komoditas::orderBy('id_komoditas')->pluck('nama_komoditas', 'id_komoditas');
    abort_if($komoditas->isEmpty(), 404);

    // Komoditas aktif: ?komoditas=ID, kalau tidak valid pakai yang pertama
    $active = (int) $request->query('komoditas');
    if (! $komoditas->has($active)) {
        $active = $komoditas->keys()->first();
    }

    // Aktual: 8 data harga terakhir
    $aktual = Harga::where('id_komoditas', $active)
        ->orderByDesc('tanggal')
        ->limit(8)
        ->get(['tanggal', 'harga'])
        ->reverse()
        ->values();

    $actualDates  = $aktual->map(fn ($r) => Carbon::parse($r->tanggal)->startOfDay());
    $actualValues = $aktual->map(fn ($r) => (int) $r->harga)->all();
    $lastDate     = $actualDates->last();
    $lastActual   = end($actualValues) ?: 0;

    // Prediksi: run terbaru (id_evaluasi terbesar), 7 hari setelah data aktual terakhir
    $forecastRows = collect();
    $modelName    = '-';

    $idEval = DB::table('hasil_prediksi')
        ->where('id_komoditas', $active)
        ->max('id_evaluasi');

    if ($idEval && $lastDate) {
        $forecastRows = DB::table('hasil_prediksi as h')
            ->join('model as m', 'm.id_model', '=', 'h.id_model')
            ->where('h.id_komoditas', $active)
            ->where('h.id_evaluasi', $idEval)
            ->whereDate('h.tanggal_prediksi', '>', $lastDate->toDateString())
            ->orderBy('h.tanggal_prediksi')
            ->limit(self::HORIZON)
            ->get(['h.tanggal_prediksi', 'h.harga_prediksi', 'm.nama_model']);

        $modelName = $forecastRows->first()->nama_model ?? '-';
    }

    $forecastDates  = $forecastRows->map(fn ($r) => Carbon::parse($r->tanggal_prediksi));
    $forecastValues = $forecastRows->map(fn ($r) => (int) round($r->harga_prediksi))->all();

    // Label grafik: tanggal aktual + tanggal prediksi
    $labels = $actualDates->merge($forecastDates)
        ->map(fn ($d) => $d->translatedFormat('d M'))
        ->values()
        ->all();

    // Garis grafik: prediksi disambung dari titik aktual terakhir
    $nForecast = count($forecastValues);
    $actual    = array_merge($actualValues, array_fill(0, $nForecast, null));
    $forecast  = $nForecast
        ? array_merge(array_fill(0, max(0, count($actualValues) - 1), null), [$lastActual], $forecastValues)
        : array_fill(0, count($actualValues), null);

    // Tabel: persentase selisih harian
    $rows = [];
    $prev = $lastActual;
    foreach ($forecastValues as $i => $price) {
        $rows[] = [
            'date'  => $forecastDates[$i]->translatedFormat('d M'),
            'price' => $price,
            'diff'  => $prev ? (($price - $prev) / $prev) * 100 : 0,
        ];
        $prev = $price;
    }

    return view('prediksi.index', [
        'tabs'      => $komoditas->all(),   // [id_komoditas => nama]
        'active'    => $active,
        'name'      => $komoditas[$active],
        'modelName' => $modelName,
        'labels'    => $labels,
        'datasets'  => [
            ['label' => 'Aktual',   'values' => $actual,   'color' => '#1a5cff'],
            ['label' => 'Prediksi', 'values' => $forecast, 'color' => '#22d3e6', 'dashed' => true],
        ],
        'rows'      => $rows,
    ]);
}
    private const WILAYAH_ID = 37;
    private const HORIZON = 30;

    private const PILIHAN_HORIZON = [1, 7, 14, 30];

    private const KOMODITAS = [
        1 => 'Cabai Rawit Merah',
        2 => 'Cabai Merah Keriting',
        3 => 'Bawang Merah',
    ];

    private const WARNA = [
        'LSTM'   => '#206bc4',
        'BiLSTM' => '#f76707',
        'GRU'    => '#6741d9',
    ];

    private const GARIS = [
        'LSTM'   => 6,
        'BiLSTM' => 2,
        'GRU'    => 10,
    ];

    private const RIWAYAT = 14; // hari sebelum titik awal yang ikut digambar

    public function prediksi(Request $request)
    {
        $komoditas = (int) $request->query('komoditas', 0);

if (! isset(self::KOMODITAS[$komoditas])) {
    $komoditas = (int) DB::table('evaluasi_model as e')
        ->where('e.id_wilayah', self::WILAYAH_ID)
        ->where('e.horizon', self::HORIZON)
        ->where('e.status', 'berhasil')
        ->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('hasil_prediksi as p')
                ->whereColumn('p.id_evaluasi', 'e.id_evaluasi');
        })
        ->orderByDesc('e.id_evaluasi')
        ->value('e.id_komoditas');

    if (! isset(self::KOMODITAS[$komoditas])) {
        $komoditas = 1;
    }
}

        $tab        = $request->query('tab') === 'forecast' ? 'forecast' : 'backtest';
        $difilter   = $request->has('f');
        $semuaModel = DB::table('model')->orderBy('id_model')->pluck('nama_model')->all();

        // Sebelum form dikirim: semua model dan naive aktif.
        $modelDipilih = $difilter
            ? array_values(array_intersect($semuaModel, (array) $request->query('model', [])))
            : $semuaModel;
        $tampilNaive = $difilter ? $request->boolean('naive') : true;

        $strategi = in_array($request->query('strategi'), ['ST-1', 'ST-2', 'ST-3'], true)
            ? $request->query('strategi')
            : null;

        $horizon = (int) $request->query('horizon', self::HORIZON);
        if (! in_array($horizon, self::PILIHAN_HORIZON, true)) {
            $horizon = self::HORIZON;
        }

        $runs = $this->pilihRun($komoditas, $modelDipilih, $strategi);

        $data = [
            'grafik'   => null,
            'kolom'    => [],
            'baris'    => [],
            'akurasi'  => [],
            'opsiAwal' => [],
            'awal'     => null,
        ];

                if ($runs) {
            $data = $tab === 'backtest'
                ? $this->dataBacktest($request, $komoditas, $runs, $tampilNaive, $horizon)
                : $this->dataForecast($komoditas, $runs, $tampilNaive, $horizon);

            if ($request->boolean('unduh') && $data['baris']) {
                return $this->unduhCsv($komoditas, $tab, $data['kolom'], $data['baris']);
            }
        }

        return view('prediksi.indexadmin', array_merge($data, [
            'tab'             => $tab,
            'komoditas'       => $komoditas,
            'daftarKomoditas' => self::KOMODITAS,
            'semuaModel'      => $semuaModel,
            'modelDipilih'    => $modelDipilih,
            'tampilNaive'     => $tampilNaive,
            'strategi'        => $strategi,
            'horizon'         => $horizon,
            'pilihanHorizon'  => self::PILIHAN_HORIZON,
            'runs'            => $runs,
            'warna'           => self::WARNA,
        ]));
    }

    /**
     * Memilih satu run evaluasi per model: horizon 30, status berhasil,
     * punya baris di hasil_prediksi, pada data terbaru (tanggal_berakhir
     * terbaru), lalu RMSE validasi terendah.
     */
    private function pilihRun(int $komoditas, array $namaModel, ?string $strategi): array
    {
        if (! $namaModel) {
            return [];
        }

        $kandidat = DB::table('evaluasi_model as e')
            ->join('model as m', 'm.id_model', '=', 'e.id_model')
            ->where('e.id_komoditas', $komoditas)
            ->where('e.id_wilayah', self::WILAYAH_ID)
            ->where('e.horizon', self::HORIZON)
            ->where('e.status', 'berhasil')
            ->whereNotNull('e.rmse_validasi')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('hasil_prediksi as p')
                    ->whereColumn('p.id_evaluasi', 'e.id_evaluasi');
            })
            ->select('e.*', 'm.nama_model')
            ->get();

        if ($strategi) {
            $kandidat = $kandidat->filter(
                fn ($r) => $this->strategiDariKode((string) $r->kode_skenario) === $strategi
            );
        }

        $terbaruGlobal = $kandidat->max('tanggal_berakhir');
        $hasil = [];

        foreach ($namaModel as $nama) {
            $milikModel = $kandidat->where('nama_model', $nama);
            if ($milikModel->isEmpty()) {
                continue;
            }

            // Utamakan data terbaru yang sama antar model; kalau model ini
            // belum punya run di tanggal itu, pakai data terbarunya sendiri.
            $pakai = $milikModel->where('tanggal_berakhir', $terbaruGlobal);
            if ($pakai->isEmpty()) {
                $pakai = $milikModel->where('tanggal_berakhir', $milikModel->max('tanggal_berakhir'));
            }

            $hasil[$nama] = $pakai->sortBy('rmse_validasi')->first();
        }

        return $hasil;
    }

    /** K01-K09 diasumsikan berurutan: model dulu, baru ST-1..ST-3. */
    private function strategiDariKode(string $kode): ?string
    {
        if (! preg_match('/(\d+)/', $kode, $m)) {
            return null;
        }

        return 'ST-' . ((((int) $m[1]) - 1) % 3 + 1);
    }

    private function hargaMap(int $komoditas, string $dari, string $sampai): array
    {
        return DB::table('data_harga')
            ->where('id_komoditas', $komoditas)
            ->where('id_wilayah', self::WILAYAH_ID)
            ->whereBetween(DB::raw('DATE(tanggal)'), [$dari, $sampai])
            ->selectRaw('DATE(tanggal) as tgl, harga')
            ->pluck('harga', 'tgl')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function hitungMetrik(array $pasangan): array
    {
        $n = count($pasangan);
        if ($n === 0) {
            return ['rmse' => null, 'mae' => null, 'mape' => null, 'r2' => null];
        }

        $sumSq = $sumAbs = $sumPct = $sumA = 0.0;
        $nPct = 0;

        foreach ($pasangan as [$a, $p]) {
            $e = $a - $p;
            $sumSq  += $e * $e;
            $sumAbs += abs($e);
            $sumA   += $a;
            if ($a > 0) {
                $sumPct += abs($e / $a);
                $nPct++;
            }
        }

        $rata = $sumA / $n;
        $ssTot = 0.0;
        foreach ($pasangan as [$a, $p]) {
            $ssTot += ($a - $rata) ** 2;
        }

        return [
            'rmse' => sqrt($sumSq / $n),
            'mae'  => $sumAbs / $n,
            'mape' => $nPct ? $sumPct / $nPct * 100 : null,
            'r2'   => $ssTot > 0 ? 1 - $sumSq / $ssTot : null,
        ];
    }

    private function dataBacktest(Request $request, int $komoditas, array $runs, bool $tampilNaive, int $horizon): array
    {
        $kosong = ['grafik' => null, 'kolom' => [], 'baris' => [], 'akurasi' => [], 'opsiAwal' => [], 'awal' => null];

        $idRun = collect($runs)->pluck('id_evaluasi')->all();

        $opsiAwal = DB::table('hasil_prediksi')
            ->whereIn('id_evaluasi', $idRun)
            ->where('jenis', 'backtest')
            ->select('tanggal_awal')
            ->distinct()
            ->orderByDesc('tanggal_awal')
            ->pluck('tanggal_awal')
            ->all();

        if (! $opsiAwal) {
            return $kosong;
        }

        $awal = in_array($request->query('awal'), $opsiAwal, true) ? $request->query('awal') : $opsiAwal[0];

        $pasangan  = ['Naive' => ['semua' => []]];
        $barisAwal = [];
        $hargaAwal = null;
        $pertama   = array_key_first($runs);

        foreach ($runs as $nama => $run) {
            $pasangan[$nama]  = ['semua' => []];
            $barisAwal[$nama] = [];

            $rows = DB::table('hasil_prediksi as p')
                ->join('data_harga as h', function ($j) use ($komoditas) {
                    $j->on('h.tanggal', '=', 'p.tanggal_prediksi')
                        ->where('h.id_komoditas', '=', $komoditas)
                        ->where('h.id_wilayah', '=', self::WILAYAH_ID);
                })
                ->join('data_harga as h0', function ($j) use ($komoditas) {
                    $j->on('h0.tanggal', '=', 'p.tanggal_awal')
                        ->where('h0.id_komoditas', '=', $komoditas)
                        ->where('h0.id_wilayah', '=', self::WILAYAH_ID);
                })
                ->where('p.id_evaluasi', $run->id_evaluasi)
                ->where('p.jenis', 'backtest')
                ->get([
                    'p.tanggal_awal', 'p.tanggal_prediksi', 'p.harga_prediksi',
                    'h.harga as aktual', 'h0.harga as harga_awal',
                ]);

            foreach ($rows as $r) {
                // Hari ke-N dari titik awal. Hanya hari 1..horizon yang dipakai.
                $langkah = (int) round((strtotime($r->tanggal_prediksi) - strtotime($r->tanggal_awal)) / 86400);
                if ($langkah > $horizon) {
                    continue;
                }

                $a = (float) $r->aktual;
                $p = (float) $r->harga_prediksi;
                $n = (float) $r->harga_awal;

                $pasangan[$nama]['semua'][] = [$a, $p];

                // Naive dihitung sekali, dari run pertama.
                if ($nama === $pertama) {
                    $pasangan['Naive']['semua'][] = [$a, $n];
                }

                if ($r->tanggal_awal === $awal) {
                    $barisAwal[$nama][$r->tanggal_prediksi] = $p;
                    $hargaAwal = $n;
                }
            }
        }

        // Tabel akurasi: tiap model dibandingkan dengan RMSE naive.
        $rmseNaive = $this->hitungMetrik($pasangan['Naive']['semua'])['rmse'];
        $akurasi = [];

        foreach ($runs as $nama => $run) {
            $m = $this->hitungMetrik($pasangan[$nama]['semua']);

            $akurasi[] = $m + [
                'label'      => $nama,
                'run'        => $run,
                'rmse_naive' => $rmseNaive,
                'selisih'    => ($rmseNaive && $m['rmse'] !== null)
                    ? ($rmseNaive - $m['rmse']) / $rmseNaive * 100
                    : null,
            ];
        }

        // Tanggal grafik: 14 hari sebelum titik awal + $horizon hari prediksi.
        $carbon = Carbon::parse($awal);
        $riwayat = [];
        for ($k = self::RIWAYAT; $k >= 0; $k--) {
            $riwayat[] = $carbon->copy()->subDays($k)->toDateString();
        }
        $keDepan = [];
        for ($k = 1; $k <= $horizon; $k++) {
            $keDepan[] = $carbon->copy()->addDays($k)->toDateString();
        }
        $semua = array_merge($riwayat, $keDepan);

        $aktual = $this->hargaMap($komoditas, $semua[0], end($semua));
        $h0     = $aktual[$awal] ?? $hargaAwal;

        $series = [['name' => 'Aktual', 'data' => array_map(fn ($t) => $aktual[$t] ?? null, $semua)]];
        $warna  = ['#1d273b'];
        $lebar  = [3];
        $putus  = [0];

        if ($tampilNaive) {
            $series[] = ['name' => 'Naive', 'data' => array_map(fn ($t) => $t >= $awal ? $h0 : null, $semua)];
            $warna[]  = '#adb5bd';
            $lebar[]  = 2;
            $putus[]  = 3;
        }

        foreach ($runs as $nama => $run) {
            $series[] = ['name' => $nama, 'data' => array_map(
                fn ($t) => $t === $awal ? $h0 : ($barisAwal[$nama][$t] ?? null),
                $semua
            )];
            $warna[] = self::WARNA[$nama] ?? '#4263eb';
            $lebar[] = 2;
            $putus[] = self::GARIS[$nama] ?? 4;
        }

        // Tabel detail harian.
        $kolom = array_merge(['Tanggal', 'Aktual'], array_keys($runs), ['Paling dekat']);
        $baris = [];

        foreach ($keDepan as $t) {
            $a = $aktual[$t] ?? null;
            $b = [$t, $a];
            $terdekat = null;
            $jarak = null;

            foreach ($runs as $nama => $run) {
                $p = $barisAwal[$nama][$t] ?? null;
                $b[] = $p;

                if ($a !== null && $p !== null && ($jarak === null || abs($p - $a) < $jarak)) {
                    $jarak = abs($p - $a);
                    $terdekat = $nama;
                }
            }

            $b[] = $terdekat;
            $baris[] = $b;
        }

        return [
            'grafik' => [
                'kategori'   => array_map(fn ($t) => Carbon::parse($t)->format('d M'), $semua),
                'series'     => $series,
                'warna'      => $warna,
                'lebar'      => $lebar,
                'putus'      => $putus,
                'awalLabel'  => $carbon->format('d M'),
                'akhirLabel' => Carbon::parse(end($semua))->format('d M'),
                'teks'       => $horizon . ' hari prediksi',
            ],
            'kolom'    => $kolom,
            'baris'    => $baris,
            'akurasi'  => $akurasi,
            'opsiAwal' => $opsiAwal,
            'awal'     => $awal,
        ];
    }

    private function dataForecast(int $komoditas, array $runs, bool $tampilNaive, int $horizon): array
    {
        $kosong = ['grafik' => null, 'kolom' => [], 'baris' => [], 'akurasi' => [], 'opsiAwal' => [], 'awal' => null];

        $forecast = [];
        $awal = null;

        foreach ($runs as $nama => $run) {
            $rows = DB::table('hasil_prediksi')
                ->where('id_evaluasi', $run->id_evaluasi)
                ->where('jenis', 'forecast')
                ->orderBy('tanggal_prediksi')
                ->get(['tanggal_awal', 'tanggal_prediksi', 'harga_prediksi']);

            foreach ($rows as $r) {
                $forecast[$nama][$r->tanggal_prediksi] = (float) $r->harga_prediksi;
                if ($awal === null || $r->tanggal_awal > $awal) {
                    $awal = $r->tanggal_awal;
                }
            }
        }

        if ($awal === null) {
            return $kosong;
        }

        $carbon = Carbon::parse($awal);
        $riwayat = [];
        for ($k = self::RIWAYAT; $k >= 0; $k--) {
            $riwayat[] = $carbon->copy()->subDays($k)->toDateString();
        }

        $tanggalForecast = [];
        foreach ($forecast as $perModel) {
            $tanggalForecast = array_merge($tanggalForecast, array_keys($perModel));
        }
        $tanggalForecast = array_values(array_unique($tanggalForecast));
        sort($tanggalForecast);

        // Hanya $horizon hari pertama setelah data terakhir.
        $tanggalForecast = array_slice($tanggalForecast, 0, $horizon);

        $semua  = array_merge($riwayat, $tanggalForecast);
        $aktual = $this->hargaMap($komoditas, $riwayat[0], $awal);
        $h0     = $aktual[$awal] ?? null;

        $series = [['name' => 'Aktual', 'data' => array_map(fn ($t) => $aktual[$t] ?? null, $semua)]];
        $warna  = ['#1d273b'];
        $lebar  = [3];
        $putus  = [0];

        if ($tampilNaive && $h0 !== null) {
            $series[] = ['name' => 'Naive', 'data' => array_map(fn ($t) => $t >= $awal ? $h0 : null, $semua)];
            $warna[]  = '#adb5bd';
            $lebar[]  = 2;
            $putus[]  = 3;
        }

        foreach ($runs as $nama => $run) {
            $series[] = ['name' => $nama, 'data' => array_map(
                fn ($t) => $t === $awal ? $h0 : ($forecast[$nama][$t] ?? null),
                $semua
            )];
            $warna[] = self::WARNA[$nama] ?? '#4263eb';
            $lebar[] = 2;
            $putus[] = self::GARIS[$nama] ?? 4;
        }

        $kolom = array_merge(['Tanggal'], array_keys($runs));
        $baris = [];

        foreach ($tanggalForecast as $t) {
            $b = [$t];
            foreach ($runs as $nama => $run) {
                $b[] = $forecast[$nama][$t] ?? null;
            }
            $baris[] = $b;
        }

        return [
            'grafik' => [
                'kategori'   => array_map(fn ($t) => Carbon::parse($t)->format('d M'), $semua),
                'series'     => $series,
                'warna'      => $warna,
                'lebar'      => $lebar,
                'putus'      => $putus,
                'awalLabel'  => $carbon->format('d M'),
                'akhirLabel' => Carbon::parse(end($semua))->format('d M'),
                'teks'       => $horizon . ' hari ke depan',
            ],
            'kolom'    => $kolom,
            'baris'    => $baris,
            'akurasi'  => [],
            'opsiAwal' => [],
            'awal'     => $awal,
        ];
    }

    private function unduhCsv(int $komoditas, string $tab, array $kolom, array $baris)
    {
        $nama = sprintf('prediksi-%s-k%d-%s.csv', $tab, $komoditas, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($kolom, $baris) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $kolom);
            foreach ($baris as $b) {
                fputcsv($out, array_map(fn ($v) => $v === null ? '' : (is_float($v) ? round($v, 2) : $v), $b));
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
