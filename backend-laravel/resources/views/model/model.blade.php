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
                                        <select class="form-select" name="komoditas" required>
                                            @foreach ($commodities as $id => $nama)
                                                <option value="{{ $id }}">{{ $nama }}</option>
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
                                        @csrf
                                        <label class="form-label">Model</label>
                                        <select class="form-select" name="model" required>
                                            <option value="LSTM">LSTM</option>
                                            <option value="BiLSTM">BiLSTM</option>
                                            <option value="GRU">GRU</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Arsitektur</label>
                                        <select class="form-select" name="arsitektur" required>
                                            <option value="A">A — 1 lapisan (128 unit)</option>
                                            <option value="B">B — 2 lapisan (64, 32 unit)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Strategi Training</label>
                                        <select class="form-select" name="strategi" required>
                                            <option value="ST-1">ST-1 — 50 epoch</option>
                                            <option value="ST-2">ST-2 — 100 epoch</option>
                                            <option value="ST-3">ST-3 — 100 epoch, patience 5</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Wilayah Target Harga</label>
                                        <select class="form-select" name="wilayah" required>
                                            <option value="37">Kota Surabaya</option>
                                        </select>
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
            const form = document.getElementById('train-form');
            if (!form) return;

            const result = document.getElementById('train-result');
            const button = document.getElementById('btn-train');
            const steps = Array.from(
                document.querySelectorAll('#train-steps li')
            );

            const startUrl = @json(route('model.train'));
            const statusUrl = @json(route('model.train.status'));
            const csrf = form.querySelector('[name="_token"]').value;

            const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

            function formatNumber(value, digits = 2) {
                return Number(value).toLocaleString('id-ID', {
                    minimumFractionDigits: digits,
                    maximumFractionDigits: digits
                });
            }

            function updateTrainingSteps(stageIndex, status) {
                const ICONS = {
                    wait: '<i class="ti ti-circle"></i>',
                    run: '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>',
                    done: '<i class="ti ti-circle-check text-green"></i>',
                    error: '<i class="ti ti-alert-circle text-danger"></i>'
                };

                steps.forEach((step, index) => {
                    let state = 'wait';

                    if (status === 'completed') {
                        state = 'done';
                    } else if (status === 'failed' && index === stageIndex) {
                        state = 'error';
                    } else if (index < stageIndex) {
                        state = 'done';
                    } else if (index === stageIndex) {
                        state = 'run';
                    }

                    step.querySelector('.step-icon').innerHTML = ICONS[state];

                    step.classList.toggle('text-secondary', state === 'wait');
                    step.classList.toggle('fw-bold', state === 'run');
                });
            }

            function showError(message) {
                result.replaceChildren();

                const box = document.createElement('div');
                box.className = 'alert alert-danger mb-0';
                box.textContent = message || 'Training gagal.';
                result.appendChild(box);
            }

            function showProgress(message) {
                result.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary"
                     role="status"></div>
                <div class="text-secondary small mt-3"></div>
            </div>`;

                result.querySelector('.text-secondary').textContent = message;
            }

            function showResult(data) {
                const metrics = data.metrik || {};
                const naive = data.metrik_naive || {};

                result.innerHTML = `
            <div class="alert alert-success">
                Training selesai. Hasil berasal dari ML service.
            </div>

            <div class="small text-secondary mb-3">
                Skenario: <strong class="scenario"></strong>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-vcenter">
                    <tbody>
                        <tr><th>Model</th><td class="model"></td></tr>
                        <tr><th>Arsitektur</th><td class="architecture"></td></tr>
                        <tr><th>Strategi</th><td class="strategy"></td></tr>
                        <tr><th>Epoch aktual</th><td class="epoch"></td></tr>
                        <tr><th>Parameter model</th><td class="parameters"></td></tr>
                        <tr><th>RMSE</th><td>${formatNumber(metrics.rmse)}</td></tr>
                        <tr><th>MAE</th><td>${formatNumber(metrics.mae)}</td></tr>
                        <tr><th>MAPE</th><td>${formatNumber(metrics.mape)}%</td></tr>
                        <tr><th>RMSE naive</th><td>${formatNumber(naive.rmse)}</td></tr>
                        <tr><th>MAE naive</th><td>${formatNumber(naive.mae)}</td></tr>
                        <tr><th>MAPE naive</th><td>${formatNumber(naive.mape)}%</td></tr>
                        <tr><th>Durasi</th><td>${formatNumber(data.durasi_detik, 1)} detik</td></tr>
                    </tbody>
                </table>
            </div>`;

                result.querySelector('.scenario').textContent =
                    data.kode_skenario ?? '-';
                result.querySelector('.model').textContent = data.model ?? '-';
                result.querySelector('.architecture').textContent =
                    data.arsitektur ?? '-';
                result.querySelector('.strategy').textContent =
                    data.strategi ?? '-';
                result.querySelector('.epoch').textContent =
                    data.epoch_aktual ?? '-';
                result.querySelector('.parameters').textContent =
                    Number(data.jumlah_parameter ?? 0).toLocaleString('id-ID');
            }

            async function fetchJson(url, options = {}) {
                const response = await fetch(url, options);
                const payload = await response.json();

                if (!response.ok) {
                    const validationErrors = payload.errors ?
                        Object.values(payload.errors).flat().join(' ') :
                        '';

                    throw new Error(
                        validationErrors ||
                        payload.detail ||
                        payload.message ||
                        `Request gagal (${response.status}).`
                    );
                }

                return payload;
            }

            form.addEventListener('submit', async function(event) {
                event.preventDefault();

                if (!form.reportValidity()) return;

                if (form.dari.value > form.sampai.value) {
                    showError('Tanggal awal tidak boleh melewati tanggal akhir.');
                    return;
                }

                button.disabled = true;

                // Reset seluruh indikator tahapan.
                updateTrainingSteps(-1, 'running');
                showProgress('Mengirim permintaan ke ML service...');

                try {
                    await fetchJson(startUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: new FormData(form)
                    });

                    while (true) {
                        await sleep(2000);

                        const status = await fetchJson(statusUrl, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        // Perbarui ikon berdasarkan progres dari FastAPI.
                        updateTrainingSteps(
                            status.stage_index,
                            status.status
                        );

                        if (status.status === 'completed') {
                            showResult(status.result);

                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Training selesai',
                                    text: `Skenario ${status.result.kode_skenario} berhasil dijalankan.`
                                });
                            }

                            break;
                        }

                        if (status.status === 'failed') {
                            throw new Error(
                                status.error || 'Training gagal.'
                            );
                        }

                        showProgress(
                            'Training berjalan di ML service. Menunggu hasil...'
                        );
                    }
                } catch (error) {
                    updateTrainingSteps(-1, 'failed');
                    showError(error.message);

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Training gagal',
                            text: error.message
                        });
                    }
                } finally {
                    button.disabled = false;
                }
            });

        })();
    </script>
@endpush
