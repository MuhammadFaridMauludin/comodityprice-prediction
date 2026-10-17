@extends('layouts.admin.tabler')

@section('content')
    <x-admin.page-header pretitle="Overview" title="Kelola Admin" />

    <div class="page-body">
        <div class="container-xl">
            <x-admin.card title="Daftar Admin" :flush="true">
                <x-slot name="actions">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-user"
                        data-mode="create">
                        <i class="ti ti-plus"></i> Tambah Admin
                    </button>
                </x-slot>

                <x-admin.filters :selects="$selects" :search="true" />

                <x-admin.table :columns="['No', 'Nama', 'Email', 'Status', 'Login terakhir', ['label' => 'Aksi', 'align' => 'right']]">
                    @forelse ($users as $u)
                        <tr>
                            <td>{{ $users->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span
                                        class="avatar avatar-sm me-2">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                                    <span>{{ $u->name }}</span>
                                </div>
                            </td>
                            <td class="text-secondary">{{ $u->email }}</td>
                            <td><x-admin.badge :status="$u->status" /></td>
                            <td class="text-secondary">
                                {{ $u->last_login_at ? \Illuminate\Support\Carbon::parse($u->last_login_at)->diffForHumans() : 'Belum pernah' }}
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary btn-edit"
                                    data-edit-url="{{ route('pengguna.edit', $u->id) }}"
                                    data-update-url="{{ route('pengguna.update', $u->id) }}" aria-label="Ubah">
                                    <i class="ti ti-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-icon btn-sm btn-ghost-danger btn-delete"
                                    aria-label="Hapus" data-name="{{ $u->name }}"
                                    data-url="{{ route('pengguna.delete', $u->id) }}">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">
                                Tidak ada data admin yang sesuai.
                            </td>
                        </tr>
                    @endforelse
                </x-admin.table>

                <x-slot name="footer">
                    {{ $users->links('vendor.pagination.tabler') }}
                </x-slot>
            </x-admin.card>
        </div>
    </div>

    {{-- Modal Tambah / Ubah --}}
    <div class="modal modal-blur fade" id="modal-user" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form id="form-user" method="POST" action="{{ route('pengguna.store') }}" class="modal-content">
                @csrf
                <input type="hidden" name="_method" value="PUT" id="form-method" disabled>
                <input type="hidden" name="form_mode" id="form-mode" value="create">
                <input type="hidden" name="form_id" id="form-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modal-user-title">Tambah Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" id="user-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="user-email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="user-status" class="form-select" required>
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kata sandi</label>
                        <input type="password" name="password" id="user-password" class="form-control"
                            autocomplete="new-password">
                        <small class="form-hint" id="password-hint"></small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Form Tersembunyi Hapus --}}
    <form id="delete-form" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@push('myscript')
    @php
        $reopen = [
            'mode' => old('form_mode', 'create'),
            'id' => old('form_id'),
            'name' => old('name'),
            'email' => old('email'),
            'status' => old('status'),
            'updateUrl' => old('form_id') ? route('pengguna.update', old('form_id')) : null,
        ];
        $flashStatus = session('status');
        $flashError = session('error');
        $hasErrors = $errors->any();
    @endphp

    <script>
        (function() {
            const modalEl = document.getElementById('modal-user');
            const form = document.getElementById('form-user');
            const methodInput = document.getElementById('form-method');
            const storeUrl = @json(route('pengguna.store'));

            const el = {
                mode: document.getElementById('form-mode'),
                id: document.getElementById('form-id'),
                name: document.getElementById('user-name'),
                email: document.getElementById('user-email'),
                status: document.getElementById('user-status'),
                password: document.getElementById('user-password'),
                title: document.getElementById('modal-user-title'),
                hint: document.getElementById('password-hint'),
            };

            function getModal() {
                return bootstrap.Modal.getOrCreateInstance(modalEl);
            }

            function setCreate(v) {
                v = v || {};
                form.action = storeUrl;
                methodInput.disabled = true; // POST biasa
                el.mode.value = 'create';
                el.id.value = '';
                el.title.textContent = 'Tambah Admin';
                el.name.value = v.name || '';
                el.email.value = v.email || '';
                el.status.selectedIndex = 0;
                if (v.status) el.status.value = v.status;
                el.password.value = '';
                el.password.required = true;
                el.hint.textContent = 'Minimal 8 karakter.';
            }

            function setEdit(updateUrl, v) {
                v = v || {};
                form.action = updateUrl;
                methodInput.disabled = false; // kirim _method=PUT
                el.mode.value = 'edit';
                el.id.value = v.id || '';
                el.title.textContent = 'Ubah Admin';
                el.name.value = v.name || '';
                el.email.value = v.email || '';
                if (v.status) el.status.value = v.status;
                el.password.value = '';
                el.password.required = false;
                el.hint.textContent = 'Kosongkan jika tidak ingin mengganti kata sandi.';
            }

            // TAMBAH: dibuka lewat tombol dengan data-mode="create"
            modalEl.addEventListener('show.bs.modal', function(e) {
                if (e.relatedTarget && e.relatedTarget.dataset.mode === 'create') {
                    setCreate();
                }
            });

            // EDIT
            document.querySelectorAll('.btn-edit').forEach(function(btn) {
                btn.addEventListener('click', async function() {
                    try {
                        const res = await fetch(btn.dataset.editUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (!res.ok) throw new Error('Gagal mengambil data');

                        const user = await res.json();
                        setEdit(btn.dataset.updateUrl, user);
                        getModal().show();
                    } catch (err) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: 'Data pengguna tidak dapat dimuat.'
                        });
                    }
                });
            });

            // HAPUS
            document.querySelectorAll('.btn-delete').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    Swal.fire({
                        title: 'Hapus admin ini?',
                        text: 'Admin "' + btn.dataset.name +
                            '" akan dihapus dan tidak dapat dikembalikan.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, hapus',
                        cancelButtonText: 'Batal',
                        reverseButtons: true
                    }).then(function(r) {
                        if (!r.isConfirmed) return;
                        const f = document.getElementById('delete-form');
                        f.action = btn.dataset.url;
                        f.submit();
                    });
                });
            });

            @if ($hasErrors)
                // Buka lagi modal dengan isian lama jika validasi gagal
                const old = @json($reopen);

                if (old.mode === 'edit' && old.updateUrl) {
                    setEdit(old.updateUrl, old);
                } else {
                    setCreate(old);
                }
                getModal().show();
            @endif

            @if ($flashStatus)
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: @json($flashStatus),
                    timer: 2500,
                    showConfirmButton: false
                });
            @endif

            @if ($flashError)
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: @json($flashError)
                });
            @endif
        })();
    </script>
@endpush
