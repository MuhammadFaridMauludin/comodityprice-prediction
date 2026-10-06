@extends('layouts.app')

@section('title', 'Dasbor')

@section('content')
    <x-page-header title="Dasbor" />

    <div class="dash">
        @include('dashboard.partials.prediction')

        <x-line-chart class="dash__trend"
            title="Tren Harga"
            :subtitle="$prediction['commodity'].' · 30 hari'"
            :labels="$labels"
            :datasets="$datasets"
            :zero="true" />

        @include('dashboard.partials.commodities')
        @include('dashboard.partials.links')
    </div>
@endsection
