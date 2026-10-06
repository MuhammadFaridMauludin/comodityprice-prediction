<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EvaluasiController extends Controller
{
    public function index(Request $request)
    {
        $all  = $this->dummyData();
        $slug = $request->query('komoditas', 'gabah');
        if (! array_key_exists($slug, $all)) {
            $slug = array_key_first($all);
        }

        $models = collect($all[$slug]['models']);

        return view('evaluasi.index', [
            'tabs'    => collect($all)->map(fn ($c) => $c['name'])->all(),
            'active'  => $slug,
            'models'  => $models->values()->all(),
            'best'    => $models->sortBy('rmse')->first(),   // model terbaik = RMSE terendah
            'maxRmse' => $models->max('rmse'),                // dasar lebar batang
        ]);
    }

    /** Data contoh. Ganti dengan hasil evaluasi model kamu (database / file hasil training). */
    private function dummyData(): array
    {
        return [
            'gabah' => ['name' => 'Gabah', 'models' => [
                ['name' => 'LSTM',   'mae' => 165.5, 'rmse' => 223.4, 'mape' => 2.63, 'r2' => 0.933],
                ['name' => 'BiLSTM', 'mae' => 155.7, 'rmse' => 210.2, 'mape' => 2.52, 'r2' => 0.943],
                ['name' => 'GRU',    'mae' => 144.9, 'rmse' => 195.6, 'mape' => 2.33, 'r2' => 0.956],
            ]],
            'beras-premium' => ['name' => 'Beras Premium', 'models' => [
                ['name' => 'LSTM',   'mae' => 210.4, 'rmse' => 301.5, 'mape' => 1.72, 'r2' => 0.921],
                ['name' => 'BiLSTM', 'mae' => 198.9, 'rmse' => 287.3, 'mape' => 1.64, 'r2' => 0.934],
                ['name' => 'GRU',    'mae' => 185.2, 'rmse' => 270.8, 'mape' => 1.51, 'r2' => 0.947],
            ]],
            'minyak-goreng' => ['name' => 'Minyak Goreng', 'models' => [
                ['name' => 'LSTM',   'mae' => 280.6, 'rmse' => 395.2, 'mape' => 1.54, 'r2' => 0.912],
                ['name' => 'BiLSTM', 'mae' => 265.1, 'rmse' => 372.4, 'mape' => 1.47, 'r2' => 0.926],
                ['name' => 'GRU',    'mae' => 270.3, 'rmse' => 380.9, 'mape' => 1.49, 'r2' => 0.921],
            ]],
            'gula-pasir' => ['name' => 'Gula Pasir', 'models' => [
                ['name' => 'LSTM',   'mae' => 190.2, 'rmse' => 261.7, 'mape' => 1.12, 'r2' => 0.938],
                ['name' => 'BiLSTM', 'mae' => 176.5, 'rmse' => 244.9, 'mape' => 1.04, 'r2' => 0.949],
                ['name' => 'GRU',    'mae' => 181.3, 'rmse' => 252.6, 'mape' => 1.07, 'r2' => 0.944],
            ]],
            'cabai-merah' => ['name' => 'Cabai Merah', 'models' => [
                ['name' => 'LSTM',   'mae' => 1520.4, 'rmse' => 2105.8, 'mape' => 3.41, 'r2' => 0.874],
                ['name' => 'BiLSTM', 'mae' => 1384.7, 'rmse' => 1932.1, 'mape' => 3.12, 'r2' => 0.896],
                ['name' => 'GRU',    'mae' => 1296.9, 'rmse' => 1840.5, 'mape' => 2.95, 'r2' => 0.911],
            ]],
        ];
    }
}