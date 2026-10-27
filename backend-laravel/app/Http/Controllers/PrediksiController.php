<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PrediksiController extends Controller
{
    public function index(Request $request)
    {
        $all  = $this->dummyData();
        $slug = $request->query('komoditas', 'gabah');

        // Jika slug tidak dikenal, pakai komoditas pertama.
        if (! array_key_exists($slug, $all)) {
            $slug = array_key_first($all);
        }
        $c = $all[$slug];

        // 8 hari terakhir (aktual) dan 7 hari ke depan (prediksi)
        $today         = now()->startOfDay();
        $actualDates   = collect(range(7, 0))->map(fn ($i) => $today->copy()->subDays($i));
        $forecastDates = collect(range(1, 7))->map(fn ($i) => $today->copy()->addDays($i));

        $labels = $actualDates->merge($forecastDates)
            ->map(fn ($d) => $d->translatedFormat('d M'))->values()->all();

        $lastActual = end($c['actual']);

        // Garis aktual: 8 nilai + 7 kosong. Garis prediksi: 7 kosong + titik sambung + 7 prediksi.
        $actual   = array_merge($c['actual'], array_fill(0, 7, null));
        $forecast = array_merge(array_fill(0, 7, null), [$lastActual], $c['forecast']);

        // Tabel: selisih terhadap hari sebelumnya (hari pertama dibandingkan dengan data aktual terakhir)
        $rows = [];
        $prev = $lastActual;
        foreach ($c['forecast'] as $i => $price) {
            $rows[] = [
                'date'  => $forecastDates[$i]->translatedFormat('d M'),
                'price' => $price,
                'diff'  => ($price - $prev) / $prev * 100,
            ];
            $prev = $price;
        }

        return view('prediksi.index', [
            'tabs'      => collect($all)->map(fn ($item) => $item['name'])->all(),
            'active'    => $slug,
            'name'      => $c['name'],
            'modelName' => 'GRU',
            'labels'    => $labels,
            'datasets'  => [
                ['label' => 'Aktual',   'values' => $actual,   'color' => '#1a5cff'],
                ['label' => 'Prediksi', 'values' => $forecast, 'color' => '#22d3e6', 'dashed' => true],
            ],
            'rows'      => $rows,
        ]);
    }

    /** Data contoh. Ganti dengan query database / hasil model prediksi. */
    private function dummyData(): array
    {
        return [
            'gabah' => ['name' => 'Gabah',
                'actual'   => [6000, 6020, 6060, 6050, 6080, 6100, 6090, 6100],
                'forecast' => [6150, 6200, 6200, 6250, 6200, 6200, 6200]],
            'beras-premium' => ['name' => 'Beras Premium',
                'actual'   => [12600, 12620, 12650, 12700, 12680, 12720, 12750, 12800],
                'forecast' => [12850, 12900, 12880, 12950, 13000, 13020, 13050]],
            'minyak-goreng' => ['name' => 'Minyak Goreng',
                'actual'   => [18800, 18750, 18700, 18650, 18600, 18580, 18550, 18500],
                'forecast' => [18450, 18420, 18400, 18380, 18350, 18350, 18300]],
            'gula-pasir' => ['name' => 'Gula Pasir',
                'actual'   => [17000, 17020, 17050, 17100, 17080, 17150, 17180, 17200],
                'forecast' => [17250, 17300, 17280, 17350, 17400, 17420, 17450]],
            'cabai-merah' => ['name' => 'Cabai Merah',
                'actual'   => [58000, 56500, 55200, 54800, 53500, 53000, 52400, 52000],
                'forecast' => [51000, 50200, 49500, 50000, 49200, 48800, 48500]],
        ];
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
