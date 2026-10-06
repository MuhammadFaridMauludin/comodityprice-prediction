@extends('layouts.admin.tabler')

@section('content')
    <x-admin.page-header pretitle="Overview" title="Model" />

    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">

                {{-- Model aktif --}}
                <div class="col-12">
                    <x-admin.card title="Model Aktif">
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <x-admin.table :columns="[
                                    'Komoditas',
                                    'Model',
                                    ['label' => 'RMSE', 'align' => 'right'],
                                    ['label' => 'MAPE', 'align' => 'right'],
                                    'Terakhir dilatih',
                                ]">
                                    @foreach ($active as $m)
                                        <tr>
                                            <td>{{ $m['commodity'] }}</td>
                                            <td><span class="badge bg-primary-lt">{{ $m['model'] }}</span></td>
                                            <td class="text-end">{{ number_format($m['rmse'], 1, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($m['mape'], 2, ',', '.') }}%</td>
                                            <td class="text-secondary">{{ $m['trained']->translatedFormat('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </x-admin.table>
                            </div>
                            <div class="col-lg-5">
                                <h4>Keterangan</h4>
                                <p class="text-secondary">
                                    Model aktif adalah model yang dipakai untuk menghasilkan prediksi harga 7 hari di
                                    halaman pengguna.
                                    Setiap komoditas memiliki model aktifnya sendiri, dipilih dari hasil training dengan
                                    RMSE terendah.
                                </p>
                                <p class="text-secondary mb-0">
                                    Jalankan retraining dari panel di bawah untuk memperbarui model dengan data terbaru.
                                </p>
                            </div>
                        </div>
                    </x-admin.card>
                </div>

                {{-- Retraining --}}
                <div class="col-12">
                    <x-admin.card title="Retraining Model">
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <form id="train-form" class="border rounded-3 p-3 h-100" novalidate>
                                    <div class="mb-3">
                                        <label class="form-label">Komoditas</label>
                                        <select class="form-select" name="komoditas">
                                            @foreach ($commodities as $c)
                                                <option value="{{ $c }}">{{ $c }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Periode data</label>
                                        <div class="row g-2">
                                            <div class="col-6"><input type="date" name="dari" class="form-control"
                                                    value="2025-01-01" aria-label="Dari tanggal"></div>
                                            <div class="col-6"><input type="date" name="sampai" class="form-control"
                                                    value="{{ now()->toDateString() }}" aria-label="Sampai tanggal"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Model</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            @foreach ($models as $m)
                                                <label class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" name="models[]"
                                                        value="{{ $m }}" {{ $m === 'LSTM' ? 'checked' : '' }}>
                                                    <span class="form-check-label">{{ $m }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <button type="submit" id="btn-train" class="btn btn-primary w-100">
                                        <i class="ti ti-player-play"></i> Training
                                    </button>
                                </form>
                            </div>

                            <div class="col-lg-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <h4 class="text-center mb-3">Tahapan Training</h4>
                                    <ol id="train-steps" class="list-unstyled m-0">
                                        @foreach ($steps as $step)
                                            <li class="d-flex align-items-center gap-2 py-2 text-secondary">
                                                <span class="step-icon"><i class="ti ti-circle"></i></span>
                                                <span>{{ $step }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <h4 class="text-center mb-3">Hasil Training</h4>
                                    <div id="train-result">
                                        <div class="text-center text-secondary py-4">
                                            <i class="ti ti-chart-dots fs-1 d-block mb-2"></i>
                                            Belum ada hasil. Pilih model lalu klik Training.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>
                </div>

                {{-- Riwayat training --}}
                <div class="col-12">
                    <x-admin.card title="Riwayat Training" :flush="true">
                        <x-admin.table :columns="[
                            'No',
                            'Nama Model',
                            'Komoditas',
                            'Tanggal Train',
                            'Dataset (periode)',
                            ['label' => 'RMSE', 'align' => 'right'],
                            ['label' => 'MAE', 'align' => 'right'],
                            ['label' => 'MAPE', 'align' => 'right'],
                            'Status',
                        ]">
                            @foreach ($history as $row)
                                <tr>
                                    <td>{{ $history->firstItem() + $loop->index }}</td>
                                    <td class="fw-bold">{{ $row['model'] }}</td>
                                    <td>{{ $row['commodity'] }}</td>
                                    <td>{{ $row['trained']->translatedFormat('d M Y') }}</td>
                                    <td class="text-secondary">{{ $row['period'] }}</td>
                                    <td class="text-end">
                                        {{ $row['rmse'] ? number_format($row['rmse'], 1, ',', '.') : '-' }}</td>
                                    <td class="text-end">{{ $row['mae'] ? number_format($row['mae'], 1, ',', '.') : '-' }}
                                    </td>
                                    <td class="text-end">
                                        {{ $row['mape'] ? number_format($row['mape'], 2, ',', '.') . '%' : '-' }}</td>
                                    <td><x-admin.badge :status="$row['status']" /></td>
                                </tr>
                            @endforeach
                        </x-admin.table>

                        <x-slot name="footer">
                            {{ $history->links('vendor.pagination.tabler') }}
                        </x-slot>
                    </x-admin.card>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('myscript')
    <script>
        (function() {
            var form = document.getElementById('train-form');
            if (!form) return;

            var steps = Array.prototype.slice.call(document.querySelectorAll('#train-steps li'));
            var result = document.getElementById('train-result');
            var button = document.getElementById('btn-train');

            var ICON = {
                wait: '<i class="ti ti-circle"></i>',
                run: '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>',
                done: '<i class="ti ti-circle-check text-green"></i>'
            };

            // Metrik contoh (simulasi). Ganti dengan respons server saat training sungguhan.
            var SAMPLE = {
                LSTM: {
                    rmse: 223.4,
                    mae: 165.5,
                    mape: 2.63
                },
                BiLSTM: {
                    rmse: 210.2,
                    mae: 155.7,
                    mape: 2.52
                },
                GRU: {
                    rmse: 195.6,
                    mae: 144.9,
                    mape: 2.33
                }
            };

            function fmt(n, d) {
                return n.toLocaleString('id-ID', {
                    minimumFractionDigits: d,
                    maximumFractionDigits: d
                });
            }

            function setStep(i, state) {
                steps[i].querySelector('.step-icon').innerHTML = ICON[state];
                steps[i].classList.toggle('text-secondary', state === 'wait');
                steps[i].classList.toggle('fw-bold', state === 'run');
            }

            function showResult(models) {
                var best = models.reduce(function(a, b) {
                    return SAMPLE[a].rmse <= SAMPLE[b].rmse ? a : b;
                });
                var html = '<table class="table table-sm table-vcenter mb-2"><thead><tr>' +
                    '<th>Model</th><th class="text-end">RMSE</th><th class="text-end">MAPE</th></tr></thead><tbody>';
                models.forEach(function(m) {
                    var s = SAMPLE[m];
                    html += '<tr class="' + (m === best ? 'table-active' : '') + '"><td class="fw-bold">' + m +
                        '</td>' +
                        '<td class="text-end">' + fmt(s.rmse, 1) + '</td>' +
                        '<td class="text-end">' + fmt(s.mape, 2) + '%</td></tr>';
                });
                html += '</tbody></table><div class="small text-secondary">Model terbaik: <b>' + best +
                    '</b> (RMSE terendah). Ini hasil simulasi dengan data contoh.</div>';
                result.innerHTML = html;
            }

            function run(models) {
                button.disabled = true;
                steps.forEach(function(_, i) {
                    setStep(i, 'wait');
                });
                result.innerHTML =
                    '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div>' +
                    '<div class="text-secondary small mt-3">Training berjalan...</div></div>';

                var i = 0;
                (function next() {
                    if (i > 0) setStep(i - 1, 'done');
                    if (i >= steps.length) {
                        showResult(models);
                        button.disabled = false;
                        Swal.fire({
                            icon: 'success',
                            title: 'Training selesai',
                            text: 'Ini hasil simulasi dengan data contoh.'
                        });
                        return;
                    }
                    setStep(i, 'run');
                    i++;
                    setTimeout(next, 1000);
                })();
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var models = Array.prototype.slice.call(form.querySelectorAll('input[name="models[]"]:checked'))
                    .map(function(c) {
                        return c.value;
                    });

                if (!models.length) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pilih minimal satu model'
                    });
                    return;
                }
                if (form.dari.value && form.sampai.value && form.dari.value > form.sampai.value) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Rentang tanggal tidak valid',
                        text: 'Tanggal awal harus sebelum tanggal akhir.'
                    });
                    return;
                }

                run(models);
            });
        })();
    </script>
@endpush
