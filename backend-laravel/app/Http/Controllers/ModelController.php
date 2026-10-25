<?php

namespace App\Http\Controllers;

use App\Models\EvaluasiModel;
use App\Models\Komoditas;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ModelController extends Controller
{
    // Horizon yang dipakai halaman prediksi pengguna. Sesuaikan.
    private const HORIZON_AKTIF = 30;

    // Samakan dengan STATUS_SELESAI di train.py.
    private const STATUS_OK = 'berhasil';

    public function model(Request $request)
    {
        return view('model.model', [
            'active' => $this->modelAktif(self::HORIZON_AKTIF),
            'horizonAktif' => self::HORIZON_AKTIF,

            'commodities' => Komoditas::orderBy('id_komoditas')
                ->pluck('nama_komoditas', 'id_komoditas'),

            'steps' => [
                'Agregasi data cuaca',
                'Menyiapkan dataset multivariat',
                'Pembagian data dan scaling',
                'Melatih model',
                'Evaluasi model',
                'Mengembalikan hasil training',
            ],

            'history' => $this->riwayat(),
        ]);
    }

    public function train(Request $request)
    {
        $validated = $request->validate([
            'komoditas' => ['required', 'integer', Rule::in([1, 2, 3])],
            'wilayah' => ['required', 'integer', Rule::in([37])],
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:dari',
            ],
            'model' => ['required', Rule::in(['LSTM', 'BiLSTM', 'GRU'])],
            'strategi' => ['required', Rule::in(['ST-1', 'ST-2', 'ST-3'])],
            'target_mode' => ['required', Rule::in(['price', 'difference'])],
            'horizon' => ['required', 'integer', Rule::in([1, 7, 30])],
        ]);

        try {
            $res = Http::withHeaders([
                'X-API-Key' => config('services.ml_service.key'),
            ])
                ->timeout(10)
                ->post(
                    rtrim(config('services.ml_service.url'), '/') . '/train',
                    $validated
                );
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'ML service tidak bisa dihubungi.',
            ], 503);
        }

        return response()->json(
            $res->json() ?? ['message' => 'Respons ML service tidak valid.'],
            $res->status()
        );
    }

    public function trainingStatus()
    {
        try {
            $res = Http::withHeaders([
                'X-API-Key' => config('services.ml_service.key'),
            ])
                ->timeout(10)
                ->get(
                    rtrim(config('services.ml_service.url'), '/') . '/train/status'
                );
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'ML service tidak bisa dihubungi.',
            ], 503);
        }

        return response()->json(
            $res->json() ?? ['message' => 'Respons ML service tidak valid.'],
            $res->status()
        );
    }

    /**
     * Satu model aktif per komoditas pada horizon tertentu.
     * Hanya run pada data terbaru yang saling dibandingkan,
     * dipilih dari RMSE validasi terendah.
     */
    private function modelAktif(int $horizon): Collection
    {
        return EvaluasiModel::with(['modelPrediksi', 'komoditas'])
            ->where('status', self::STATUS_OK)
            ->where('horizon', $horizon)
            ->whereNotNull('rmse_validasi')
            ->get()
            ->groupBy('id_komoditas')
            ->map(function ($grup) {
                $terbaru = (string) $grup->max(
                    fn ($r) => (string) $r->tanggal_berakhir
                );

                return $grup
                    ->filter(fn ($r) => (string) $r->tanggal_berakhir === $terbaru)
                    // RMSE validasi naik; jika sama, yang terbaru dulu.
                    ->sort(fn ($a, $b) => [$a->rmse_validasi, $b->tanggal_evaluasi]
                        <=> [$b->rmse_validasi, $a->tanggal_evaluasi])
                    ->first();
            })
            ->filter()
            ->map(fn ($r) => [
                'commodity' => $r->komoditas->nama_komoditas ?? '-',
                'model' => $r->modelPrediksi->nama_model ?? '-',
                'rmse' => $r->rmse,
                'mape' => $r->mape,
                'r2' => $r->r2,
                'trained' => $r->tanggal_evaluasi,
            ])
            ->values();
    }

    private function riwayat()
    {
        return EvaluasiModel::with(['modelPrediksi', 'komoditas'])
            ->orderByDesc('tanggal_evaluasi')
            ->paginate(10)
            ->through(fn ($r) => [
                'model' => $r->modelPrediksi->nama_model ?? '-',
                'commodity' => $r->komoditas->nama_komoditas ?? '-',
                'horizon' => $r->horizon,
                'trained' => $r->tanggal_evaluasi,
                'period' => ($r->tanggal_mulai && $r->tanggal_berakhir)
                    ? $r->tanggal_mulai->translatedFormat('d M Y')
                        . ' – ' . $r->tanggal_berakhir->translatedFormat('d M Y')
                    : '-',
                'rmse' => $r->rmse,
                'mae' => $r->mae,
                'mape' => $r->mape,
                'r2' => $r->r2,
                'status' => $r->status,
            ]);
    }
}