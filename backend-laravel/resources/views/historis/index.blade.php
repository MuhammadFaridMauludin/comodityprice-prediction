@extends('layouts.app')

@section('title', 'Historis')

@section('content')
    <x-page-header title="Data Historis" :meta="number_format($total, 0, ',', '.').' baris'" />

    <p class="lead">Pilih komoditas dan rentang tanggal terlebih dahulu.</p>

    <x-tabs :items="$tabs" :active="$active" route="historis" param="komoditas"
            :query="['dari' => $dari, 'sampai' => $sampai]" />

    <div class="hist">
        @include('historis.partials.filter')

        <x-data-table :columns="['Tanggal', ['label' => 'Harga', 'align' => 'right'], ['label' => 'Δ', 'align' => 'right']]">
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td class="num price">Rp {{ number_format($row['price'], 0, ',', '.') }}</td>
                    <td class="num">
                        <x-change-badge :value="$row['diff']" :decimals="2" :arrow="false" :signed="true" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">Tidak ada data pada rentang tanggal ini.</td></tr>
            @endforelse

            <x-slot name="footer">
                {{ $rows->links('vendor.pagination.predikta') }}
            </x-slot>
        </x-data-table>
    </div>
@endsection
