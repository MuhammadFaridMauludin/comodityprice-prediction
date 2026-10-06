@extends('layouts.app')

@section('title', 'Wawasan')

@section('content')
    <x-page-header title="Wawasan Pasar" meta="30 hari" />

    <div class="insight">
        <x-line-chart class="insight__chart" title="Perbandingan Komoditas" subtitle="Beras Premium vs Cabai Merah"
            :labels="$labels" :datasets="$datasets" :zero="true" :legend="true" />

        @include('wawasan.partials.ranking', ['title' => 'Kenaikan Tertinggi', 'items' => $gainers])
        @include('wawasan.partials.ranking', ['title' => 'Penurunan Tertinggi', 'items' => $losers])

        <section class="card notes">
            <h2>Catatan Analisis</h2>
            <p>{{ $analysis }}</p>
        </section>
    </div>
@endsection
