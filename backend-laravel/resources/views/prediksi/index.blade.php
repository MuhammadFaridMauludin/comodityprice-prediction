@extends('layouts.app')

@section('title', 'Prediksi')

@section('content')
    <x-page-header title="Prediksi Komoditas" :meta="'Model '.$modelName" />

    <p class="lead">Pilih komoditas untuk melihat hasil prediksi 7 hari ke depan.</p>

    <x-tabs :items="$tabs" :active="$active" route="prediksi" param="komoditas" />

    <div class="pred">
        <x-line-chart
            :title="$name"
            subtitle="aktual vs prediksi · /kg"
            :labels="$labels"
            :datasets="$datasets"
            :legend="true" />

        <x-data-table title="Tabel Hasil Prediksi"
            :columns="['Tanggal', 'Prediksi', ['label' => 'Selisih', 'align' => 'right']]">
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td class="price">Rp {{ number_format($row['price'], 0, ',', '.') }}</td>
                    <td class="num">
                        <x-change-badge :value="$row['diff']" :decimals="2" :arrow="false" :signed="true" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">Belum ada hasil prediksi.</td></tr>
            @endforelse
        </x-data-table>
    </div>
@endsection
