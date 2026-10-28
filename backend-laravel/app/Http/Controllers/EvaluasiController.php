<?php

namespace App\Http\Controllers;

use App\Models\Komoditas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluasiController extends Controller
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

        // Semua run yang berhasil untuk komoditas ini
        $runs = DB::table('evaluasi_model as e')
        ->join('model as m', 'm.id_model', '=', 'e.id_model')
        ->where('e.id_komoditas', $active)
        ->where('e.status', 'berhasil')
        ->where('e.horizon', 30)
        ->whereNotNull('e.rmse')
        ->get(['m.nama_model', 'e.mae', 'e.rmse', 'e.mape', 'e.r2']);

        // Satu baris per model: ambil run dengan RMSE terendah
        $models = $runs
            ->groupBy('nama_model')
            ->map(fn ($g) => $g->sortBy('rmse')->first())
            ->map(fn ($r) => [
                'name' => $r->nama_model,
                'mae'  => (float) $r->mae,
                'rmse' => (float) $r->rmse,
                'mape' => (float) $r->mape,
                'r2'   => $r->r2 !== null ? (float) $r->r2 : null,
            ])
            ->sortBy('rmse')
            ->values();

        return view('evaluasi.index', [
            'tabs'    => $komoditas->all(),        // [id_komoditas => nama]
            'active'  => $active,
            'models'  => $models->all(),
            'best'    => $models->first(),         // model terbaik = RMSE terendah (null jika belum ada)
            'maxRmse' => $models->max('rmse') ?? 0, // dasar lebar batang
        ]);
    }
}