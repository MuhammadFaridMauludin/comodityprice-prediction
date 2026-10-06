@extends('layouts.admin.tabler')

@section('content')
    <x-admin.page-header pretitle="Overview" title="Dashboard" />

    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-12">
                    <x-admin.card title="Monitoring Pipeline">
                        <x-slot name="actions">
                            <span class="text-secondary small">Update terakhir: {{ $updatedAt->format('d-m-Y H:i') }}</span>
                        </x-slot>

                        <div class="row g-3">
                            @foreach ($pipeline as $p)
                                <div class="col-sm-6 col-lg-3">
                                    <x-admin.stat :flat="true" :label="$p['label']" :value="$p['value']" :icon="$p['icon']"
                                        :color="$p['color']" :note="$p['note']" />
                                </div>
                            @endforeach
                        </div>
                    </x-admin.card>
                </div>

                <div class="col-12">
                    <x-admin.card title="Log Sistem" :flush="true">
                        <x-admin.filters :selects="$selects" />

                        <x-admin.table :columns="[
                            'No',
                            'Waktu',
                            'Proses',
                            'Sumber',
                            'Status',
                            ['label' => 'Durasi', 'align' => 'right'],
                            'Pesan',
                        ]">
                            @forelse ($logs as $row)
                                <tr>
                                    <td>{{ $logs->firstItem() + $loop->index }}</td>
                                    <td>{{ $row['time']->translatedFormat('d M Y H:i') }}</td>
                                    <td>{{ $row['process'] }}</td>
                                    <td>{{ $row['source'] }}</td>
                                    <td><x-admin.badge :status="$row['status']" /></td>
                                    <td class="text-end">{{ number_format($row['duration'], 1, ',', '.') }} dtk</td>
                                    <td class="text-secondary">{{ $row['message'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">Tidak ada log yang sesuai
                                        filter.</td>
                                </tr>
                            @endforelse
                        </x-admin.table>

                        <x-slot name="footer">
                            {{ $logs->links('vendor.pagination.tabler') }}
                        </x-slot>
                    </x-admin.card>
                </div>
            </div>
        </div>
    </div>
@endsection
