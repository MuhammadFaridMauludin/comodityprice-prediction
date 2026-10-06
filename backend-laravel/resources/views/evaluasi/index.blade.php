@extends('layouts.app')

@section('title', 'Evaluasi')

@section('content')
    <x-page-header title="Hasil Evaluasi Model" :meta="count($models).' model'" />

    <p class="lead">Pilih komoditas untuk melihat perbandingan evaluasi tiap model.</p>

    <x-tabs :items="$tabs" :active="$active" route="evaluasi" param="komoditas" />

    <div class="eval">
        <x-data-table title="Perbandingan Metrik" :columns="[
            'Model',
            ['label' => 'MAE',  'align' => 'right'],
            ['label' => 'RMSE', 'align' => 'right'],
            ['label' => 'MAPE', 'align' => 'right'],
            ['label' => 'R²',   'align' => 'right'],
        ]">
            @foreach ($models as $m)
                <tr @class(['is-best' => $m['name'] === $best['name']])>
                    <td class="model">{{ $m['name'] }}</td>
                    <td class="num">{{ number_format($m['mae'], 1, ',', '.') }}</td>
                    <td class="num">{{ number_format($m['rmse'], 1, ',', '.') }}</td>
                    <td class="num">{{ number_format($m['mape'], 2, ',', '.') }}%</td>
                    <td class="num">{{ number_format($m['r2'], 3, ',', '.') }}</td>
                </tr>
            @endforeach
        </x-data-table>

        @include('evaluasi.partials.best')
        @include('evaluasi.partials.notes')
    </div>
@endsection