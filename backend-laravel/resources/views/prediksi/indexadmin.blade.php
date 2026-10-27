@extends('layouts.admin.tabler')

@section('content')
    <style>
        .page-body .card {
            margin-bottom: 1rem;
        }
    </style>

    @php
        $rp = fn($v) => $v === null ? '-' : 'Rp ' . number_format($v, 0, ',', '.');
        $angka = fn($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
        $persen = fn($v) => $v === null ? '-' : number_format($v, 1, ',', '.') . '%';
        $kolomAkurasi = ['Model', 'RMSE', 'MAE', 'MAPE', 'R²', 'RMSE naive', 'Dibanding naive'];
    @endphp

    <x-admin.page-header pretitle="Overview" title="Prediksi" />

    <div class="page-body">
        <div class="container-xl">

            {{-- Filter: langsung terkirim saat diubah --}}
            <x-admin.card :title="$tab === 'backtest' ? 'Aktual vs prediksi · ' . $horizon . ' hari' : 'Prediksi ' . $horizon . ' hari ke depan'">
                <form id="form-filter" method="GET" action="{{ request()->url() }}">
                    <input type="hidden" name="f" value="1">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Komoditas</label>
                            <select name="komoditas" class="form-select">
                                @foreach ($daftarKomoditas as $id => $nama)
                                    <option value="{{ $id }}" {{ $komoditas === $id ? 'selected' : '' }}>
                                        {{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Model</label>
                            <div class="py-2">
                                @foreach ($semuaModel as $nama)
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" name="model[]"
                                            value="{{ $nama }}"
                                            {{ in_array($nama, $modelDipilih, true) ? 'checked' : '' }}>
                                        <span class="form-check-label">{{ $nama }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Horizon</label>
                            <select name="horizon" class="form-select">
                                @foreach ($pilihanHorizon as $h)
                                    <option value="{{ $h }}" {{ $horizon === $h ? 'selected' : '' }}>
                                        {{ $h }} hari</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($tab === 'backtest')
                            <div class="col-md-2">
                                <label class="form-label">Titik awal</label>
                                <select name="awal" class="form-select" {{ $opsiAwal ? '' : 'disabled' }}>
                                    @foreach ($opsiAwal as $t)
                                        <option value="{{ $t }}" {{ $t === $awal ? 'selected' : '' }}>
                                            {{ \Carbon\Carbon::parse($t)->translatedFormat('d M Y') }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-2">
                            <label class="form-check py-2 mb-0">
                                <input class="form-check-input" type="checkbox" name="naive" value="1"
                                    {{ $tampilNaive ? 'checked' : '' }}>
                                <span class="form-check-label">Tampilkan naive</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-2">
                        <a class="text-secondary small" data-bs-toggle="collapse" href="#lanjutan">Lanjutan</a>
                        <div class="collapse {{ $strategi ? 'show' : '' }}" id="lanjutan">
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <label class="form-label">Strategi training</label>
                                    <select name="strategi" class="form-select">
                                        <option value="">Otomatis (terbaik)</option>
                                        @foreach (['ST-1', 'ST-2', 'ST-3'] as $st)
                                            <option value="{{ $st }}" {{ $strategi === $st ? 'selected' : '' }}>
                                                {{ $st }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </x-admin.card>

            {{-- Tab --}}
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'backtest' ? 'active' : '' }}"
                        href="{{ request()->fullUrlWithQuery(['tab' => 'backtest', 'unduh' => null]) }}">Backtest · aktual
                        vs prediksi</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'forecast' ? 'active' : '' }}"
                        href="{{ request()->fullUrlWithQuery(['tab' => 'forecast', 'unduh' => null]) }}">Prediksi ke
                        depan</a>
                </li>
            </ul>

            @if (!$modelDipilih)
                <div class="alert alert-warning">Pilih minimal satu model.</div>
            @elseif (!$runs)
                <div class="alert alert-warning">
                    Belum ada hasil prediksi tersimpan untuk komoditas ini. Latih model dengan horizon 30
                    dari halaman Model terlebih dahulu.
                </div>
            @elseif (!$grafik)
                <div class="alert alert-warning">
                    Run ditemukan, tetapi belum ada baris
                    {{ $tab === 'backtest' ? 'backtest' : 'prediksi ke depan' }} di tabel hasil_prediksi.
                </div>
            @else
                {{-- Grafik --}}
                <x-admin.card :title="$tab === 'backtest' ? 'Aktual vs prediksi · 30 hari' : 'Prediksi 30 hari ke depan'">
                    <div class="text-secondary small mb-2">
                        Harga (Rp/kg), Kota Surabaya ·
                        {{ $tab === 'backtest' ? 'titik awal' : 'data terakhir' }}
                        {{ \Carbon\Carbon::parse($awal)->translatedFormat('d M Y') }}
                    </div>
                    <div id="grafik-prediksi"></div>
                </x-admin.card>

                {{-- Akurasi (backtest saja) --}}
                @if ($tab === 'backtest')
                    <x-admin.card title="Akurasi backtest" :flush="true">
                        <x-admin.table :columns="$kolomAkurasi">
                            @foreach ($akurasi as $a)
                                <tr>
                                    <td class="fw-bold">
                                        <span class="d-inline-block me-2 rounded-1"
                                            style="width:10px;height:10px;background: {{ $warna[$a['label']] ?? '#4263eb' }}"></span>{{ $a['label'] }}
                                    </td>
                                    <td>{{ $angka($a['rmse']) }}</td>
                                    <td>{{ $angka($a['mae']) }}</td>
                                    <td>{{ $persen($a['mape']) }}</td>
                                    <td>{{ $angka($a['r2'], 3) }}</td>
                                    <td>{{ $angka($a['rmse_naive']) }}</td>
                                    <td>
                                        @if ($a['selisih'] === null)
                                            -
                                        @elseif ($a['selisih'] > 0)
                                            <span class="badge bg-success-lt">{{ number_format($a['selisih'], 0) }}%
                                                lebih baik</span>
                                        @else
                                            <span class="badge bg-warning-lt">{{ number_format(abs($a['selisih']), 0) }}%
                                                lebih buruk</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-admin.table>

                        <x-slot name="footer">
                            <div class="text-secondary small">
                                Dihitung dari {{ count($opsiAwal) }} titik awal backtest (satu titik tiap 7 hari pada
                                data uji), hanya pada hari ke-1 sampai ke-{{ $horizon }} setelah titik awal.
                                Run yang dipakai:
                                @foreach ($runs as $nama => $run)
                                    {{ $nama }} {{ $run->kode_skenario }} (window
                                    {{ $run->window_size }}){{ $loop->last ? '.' : ',' }}
                                @endforeach
                            </div>
                        </x-slot>
                    </x-admin.card>
                @endif

                {{-- Detail harian --}}
                <x-admin.card title="Detail harian" :flush="true">
                    <x-slot name="actions">
                        <a href="{{ request()->fullUrlWithQuery(['unduh' => 1]) }}"
                            class="btn btn-sm btn-outline-primary">Unduh CSV</a>
                    </x-slot>

                    <x-admin.table :columns="$kolom">
                        @foreach ($baris as $b)
                            <tr class="{{ $loop->index >= 7 ? 'baris-lain d-none' : '' }}">
                                <td>{{ \Carbon\Carbon::parse($b[0])->translatedFormat('d M Y') }}</td>
                                @foreach (array_slice($b, 1) as $v)
                                    <td>{{ is_numeric($v) ? $rp($v) : $v ?? '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </x-admin.table>

                    @if (count($baris) > 7)
                        <x-slot name="footer">
                            <div class="text-center">
                                <a href="#" id="toggle-baris" class="text-secondary small"
                                    data-jumlah="{{ count($baris) - 7 }}">{{ count($baris) - 7 }} baris lainnya</a>
                            </div>
                        </x-slot>
                    @endif
                </x-admin.card>
            @endif

        </div>
    </div>
@endsection
@push('myscript')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('form-filter');
            if (!form) return;

            form.querySelectorAll('select, input[type=checkbox]').forEach(function(el) {
                el.addEventListener('change', function() {
                    // Titik awal milik komoditas lama tidak boleh ikut terkirim.
                    if (el.name === 'komoditas' && form.elements['awal']) {
                        form.elements['awal'].disabled = true;
                    }
                    form.submit();
                });
            });
        });
    </script>
@endpush
@push('myscript')
    @if (!empty($grafik))
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const g = @json($grafik);
                const angka = v => v == null ? '-' : Math.round(v).toLocaleString('id-ID');
                const rupiah = v => v == null ? '-' : 'Rp ' + angka(v);

                new ApexCharts(document.querySelector('#grafik-prediksi'), {
                    chart: {
                        type: 'line',
                        height: 340,
                        toolbar: {
                            show: false
                        },
                        animations: {
                            enabled: false
                        }
                    },
                    series: g.series,
                    colors: g.warna,
                    stroke: {
                        width: g.lebar,
                        dashArray: g.putus,
                        curve: 'straight'
                    },
                    markers: {
                        size: 0,
                        hover: {
                            size: 5
                        }
                    },
                    grid: {
                        borderColor: '#e9ecef',
                        strokeDashArray: 0
                    },
                    xaxis: {
                        categories: g.kategori,
                        tickAmount: 8,
                        tooltip: {
                            enabled: false
                        },
                        labels: {
                            rotate: 0,
                            hideOverlaps: true
                        }
                    },
                    yaxis: {
                        tickAmount: 5,
                        forceNiceScale: true,
                        labels: {
                            formatter: angka
                        }
                    },
                    tooltip: {
                        shared: true,
                        y: {
                            formatter: rupiah
                        }
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right'
                    },
                    annotations: {
                        xaxis: [{
                                x: g.awalLabel,
                                x2: g.akhirLabel,
                                fillColor: '#206bc4',
                                opacity: 0.05,
                                borderWidth: 0
                            },
                            {
                                x: g.awalLabel,
                                borderColor: '#6c757d',
                                strokeDashArray: 4,
                                label: {
                                    text: g.teks,
                                    borderWidth: 0,
                                    position: 'top',
                                    textAnchor: 'start',
                                    orientation: 'horizontal',
                                    offsetX: 8,
                                    style: {
                                        background: 'transparent',
                                        color: '#6c757d',
                                        fontSize: '11px'
                                    }
                                }
                            }
                        ]
                    }
                }).render();

                const toggle = document.getElementById('toggle-baris');
                if (toggle) {
                    let terbuka = false;
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        terbuka = !terbuka;
                        document.querySelectorAll('.baris-lain').forEach(tr => tr.classList.toggle('d-none', !
                            terbuka));
                        toggle.textContent = terbuka ? 'Sembunyikan' : toggle.dataset.jumlah + ' baris lainnya';
                    });
                }
            });
        </script>
    @endif
@endpush
