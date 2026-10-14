@extends('layouts.admin.tabler')

@section('content')
    <x-admin.page-header pretitle="Overview" title="Data Management" />

    <div class="page-body">
        <div class="container-xl">
            {{-- Navigation Tabs --}}
            <ul class="nav nav-pills mb-3">
                <li class="nav-item">
                    <a class="nav-link {{ $type === 'harga' ? 'active' : '' }}" href="{{ route('harga') }}">Data Komoditas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $type === 'cuaca' ? 'active' : '' }}" href="{{ route('cuaca') }}">Data Cuaca</a>
                </li>
            </ul>

            <x-admin.card :title="$type === 'harga' ? 'Data Harga Komoditas' : 'Data Cuaca'" :flush="true">
                <x-slot name="actions">
                    <a href="#" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Tambah Data</a>
                </x-slot>

                {{-- Komponen Filter --}}
                <x-admin.filters :selects="$selects" :date="true" />

                {{-- Tabel Data --}}
                <x-admin.table :columns="$columns">
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>

                            {{-- Tanggal --}}
                            <td>{{ \Carbon\Carbon::parse($row->tanggal)->translatedFormat('d M Y') }}</td>

                            @if ($type === 'harga')
                                {{-- Kolom Khusus Data Harga --}}
                                <td>{{ $row->komoditas?->nama_komoditas ?? '-' }}</td>
                                <td>{{ $row->wilayah?->nama_wilayah ?? '-' }}</td>
                                <td class="text-end">Rp {{ number_format($row->harga, 0, ',', '.') }}</td>
                            @else
                                {{-- Kolom Khusus Data Cuaca --}}
                                <td>{{ $row->koordinat?->nama_koordinat ?? '-' }}</td>
                                <td class="text-end">{{ $row->suhu ?? 0 }} °C</td>
                                <td class="text-end">{{ $row->curah_hujan ?? 0 }} mm</td>
                                <td class="text-end">{{ $row->kelembapan ?? 0 }}%</td>
                            @endif

                            {{-- Badge Status --}}
                            <td><x-admin.badge :status="$row->status ?? 'normal'" /></td>

                            {{-- Tombol Aksi --}}
                            <td class="text-end">
                                <a href="#" class="btn btn-icon btn-sm btn-ghost-secondary" aria-label="Ubah"><i
                                        class="ti ti-pencil"></i></a>
                                <button type="button" class="btn btn-icon btn-sm btn-ghost-danger btn-delete"
                                    aria-label="Hapus"><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="text-center text-secondary py-4">Tidak ada data yang
                                sesuai filter.</td>
                        </tr>
                    @endforelse
                </x-admin.table>

                {{-- Pagination --}}
                <x-slot name="footer">
                    {{ $rows->links('vendor.pagination.tabler') }}
                </x-slot>
            </x-admin.card>
        </div>
    </div>
@endsection

@push('myscript')
    <script>
        document.querySelectorAll('.btn-delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Hapus data ini?',
                    text: 'Tindakan ini tidak dapat dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal'
                }).then(function(r) {
                    if (r.isConfirmed) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Hanya contoh',
                            text: 'Data dummy belum benar-benar dihapus.'
                        });
                    }
                });
            });
        });
    </script>
@endpush
