<?php

namespace App\Http\Controllers;

use App\Support\DummyData;
use Illuminate\Http\Request;
use App\Models\Komoditas;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\Rule;

class ModelController extends Controller
{
    public function model(Request $request)
{
    return view('model.model', [
        'active' => DummyData::activeModels(),

        'commodities' => Komoditas::orderBy('id_komoditas')
            ->pluck('nama_komoditas', 'id_komoditas'),

        'models' => DummyData::MODELS,
        'steps' => [
    'Agregasi data cuaca',
    'Menyiapkan dataset multivariat',
    'Pembagian data dan scaling',
    'Melatih model',
    'Evaluasi model',
    'Mengembalikan hasil training',
],
        'history' => DummyData::paginate(
            DummyData::trainingHistory(),
            $request,
            10
        ),
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
        'arsitektur' => ['required', Rule::in(['A', 'B'])],
        'strategi' => ['required', Rule::in(['ST-1', 'ST-2', 'ST-3'])],
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
}