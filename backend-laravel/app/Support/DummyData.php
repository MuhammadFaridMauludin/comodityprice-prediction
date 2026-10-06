<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DummyData
{
    public const COMMODITIES = [
        'Gabah' => 6100, 'Beras Premium' => 12700, 'Minyak Goreng' => 18800,
        'Gula Pasir' => 17000, 'Cabai Merah' => 52000,
    ];
    public const REGIONS       = ['Surabaya', 'Malang', 'Kediri', 'Jember', 'Banyuwangi', 'Madiun', 'Probolinggo', 'Sidoarjo'];
    public const DATA_STATUSES = ['valid', 'interpolasi', 'anomali'];
    public const MODELS        = ['LSTM', 'BiLSTM', 'GRU'];
    public const PROCESSES     = [
        'Scraping Harga' => 'Siskaperbapo', 'Ambil Cuaca' => 'Open-Meteo',
        'Pra-pemrosesan' => 'Database', 'Prediksi Harian' => 'Scheduler', 'Backup Database' => 'Database',
    ];

    /** Angka "acak" yang selalu sama untuk kunci yang sama (0 .. $max-1). */
    private static function n(string $key, int $max): int
    {
        return abs(crc32($key)) % $max;
    }

    private static function dataStatus(string $key): string
    {
        $r = self::n($key, 20);

        return $r === 0 ? 'anomali' : ($r < 3 ? 'interpolasi' : 'valid');
    }

    public static function prices(): Collection
    {
        $rows = collect();
        foreach (range(0, 29) as $d) {
            $date = now()->startOfDay()->subDays($d);
            foreach (self::COMMODITIES as $name => $base) {
                foreach (array_slice(self::REGIONS, 0, 4) as $region) {
                    $key   = $name.$region.$d;
                    $price = (int) (round($base * (1 + (self::n($key, 21) - 10) / 200) / 50) * 50);
                    $rows->push(['date' => $date, 'commodity' => $name, 'region' => $region,
                                 'price' => $price, 'status' => self::dataStatus($key)]);
                }
            }
        }

        return $rows;
    }

    public static function weather(): Collection
    {
        $rows = collect();
        foreach (range(0, 29) as $d) {
            $date = now()->startOfDay()->subDays($d);
            foreach (self::REGIONS as $region) {
                $key = $region.$d;
                $rows->push([
                    'date'     => $date,
                    'region'   => $region,
                    'temp'     => 24 + self::n($key.'t', 11),
                    'rain'     => self::n($key.'r', 5) === 0 ? self::n($key.'m', 45) : 0,
                    'humidity' => 60 + self::n($key.'h', 36),
                    'status'   => self::dataStatus($key),
                ]);
            }
        }

        return $rows;
    }

    public static function logs(): Collection
    {
        $names = array_keys(self::PROCESSES);
        $ok = [
            'Scraping Harga' => '600 data harga tersimpan', 'Ambil Cuaca' => '240 data cuaca tersimpan',
            'Pra-pemrosesan' => 'Data bersih dan siap dipakai', 'Prediksi Harian' => 'Prediksi 7 hari diperbarui',
            'Backup Database' => 'Cadangan berhasil dibuat',
        ];

        return collect(range(0, 119))->map(function ($i) use ($names, $ok) {
            $proc   = $names[$i % 5];
            $r      = self::n("log$i", 20);
            $status = $r === 0 ? 'gagal' : ($r < 3 ? 'peringatan' : 'sukses');

            return [
                'time'     => now()->subMinutes($i * 37),
                'process'  => $proc,
                'source'   => self::PROCESSES[$proc],
                'status'   => $status,
                'duration' => (self::n("d$i", 90) + 5) / 10,
                'message'  => match ($status) {
                    'sukses'     => $ok[$proc],
                    'peringatan' => 'Sebagian data kosong, diisi interpolasi',
                    default      => 'Koneksi ke sumber melebihi batas waktu',
                },
            ];
        });
    }

    public static function processes(): array
    {
        return array_keys(self::PROCESSES);
    }

    public static function pipeline(): array
    {
        return [
            ['label' => 'Siskaperbapo',    'value' => 'Normal',    'icon' => 'building-store', 'color' => 'green',   'note' => 'Sinkron 12 menit lalu'],
            ['label' => 'Open-Meteo',      'value' => 'Lambat',    'icon' => 'cloud',          'color' => 'orange',  'note' => 'Respons 3,2 dtk'],
            ['label' => 'Database',        'value' => 'Terhubung', 'icon' => 'database',       'color' => 'green',   'note' => 'Latensi 12 ms'],
            ['label' => 'Pengguna online', 'value' => 42,          'icon' => 'users',          'color' => 'primary', 'note' => 'Total saat ini'],
        ];
    }

    public static function activeModels(): array
    {
        return [
            ['commodity' => 'Gabah',         'model' => 'GRU',    'rmse' => 195.6,  'mape' => 2.33, 'trained' => now()->subDays(4)],
            ['commodity' => 'Beras Premium', 'model' => 'GRU',    'rmse' => 270.8,  'mape' => 1.51, 'trained' => now()->subDays(4)],
            ['commodity' => 'Minyak Goreng', 'model' => 'BiLSTM', 'rmse' => 372.4,  'mape' => 1.47, 'trained' => now()->subDays(9)],
            ['commodity' => 'Gula Pasir',    'model' => 'BiLSTM', 'rmse' => 244.9,  'mape' => 1.04, 'trained' => now()->subDays(9)],
            ['commodity' => 'Cabai Merah',   'model' => 'GRU',    'rmse' => 1840.5, 'mape' => 2.95, 'trained' => now()->subDays(4)],
        ];
    }

    public static function trainingSteps(): array
    {
        return ['Mengambil data (Siskaperbapo dan Open-Meteo)', 'Pra-pemrosesan dan normalisasi',
                'Membagi data latih dan data uji', 'Melatih model', 'Evaluasi model', 'Menyimpan model'];
    }

    public static function trainingHistory(): Collection
    {
        $names = array_keys(self::COMMODITIES);

        return collect(range(0, 44))->map(function ($i) use ($names) {
            $commodity = $names[$i % 5];
            $failed    = self::n("train$i", 15) === 0;
            $rmse      = self::COMMODITIES[$commodity] * (0.028 + self::n("r$i", 10) / 1000);
            $trained   = now()->subDays($i * 3);

            return [
                'model'     => self::MODELS[$i % 3],
                'commodity' => $commodity,
                'trained'   => $trained,
                'period'    => '01 Jan 2025 – '.$trained->translatedFormat('d M Y'),
                'rmse'      => $failed ? null : $rmse,
                'mae'       => $failed ? null : $rmse * 0.74,
                'mape'      => $failed ? null : (2 + self::n("m$i", 15) / 10),
                'status'    => $failed ? 'gagal' : 'sukses',
            ];
        });
    }

    /** Saring koleksi dengan parameter URL. $fields = ['parameter' => 'nama_kolom']. */
    public static function filter(Collection $items, Request $request, array $fields): Collection
    {
        foreach ($fields as $param => $field) {
            if ($request->filled($param)) {
                $items = $items->where($field, $request->query($param));
            }
        }

        if ($request->filled('tanggal')) {
            $items = $items->filter(fn ($r) => isset($r['date']) && $r['date']->toDateString() === $request->query('tanggal'));
        }

        return $items->values();
    }

    public static function paginate(Collection $items, Request $request, int $perPage = 10): LengthAwarePaginator
    {
        $total = $items->count();
        $last  = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, LengthAwarePaginator::resolveCurrentPage()), $last);

        return (new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $total, $perPage, $page, ['path' => $request->url()]))
            ->onEachSide(1)
            ->withQueryString();
    }
}