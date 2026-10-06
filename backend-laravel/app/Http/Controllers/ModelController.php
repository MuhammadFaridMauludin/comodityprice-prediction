<?php

namespace App\Http\Controllers;

use App\Support\DummyData;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function model(Request $request)
    {
        return view('model.model', [
            'active'      => DummyData::activeModels(),
            'commodities' => array_keys(DummyData::COMMODITIES),
            'models'      => DummyData::MODELS,
            'steps'       => DummyData::trainingSteps(),
            'history'     => DummyData::paginate(DummyData::trainingHistory(), $request, 10),
        ]);
    }
}