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
                                <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary" aria-label="Ubah"
                                    data-bs-toggle="modal" data-bs-target="#modal-user" data-mode="edit"
                                    data-id="{{ $u->id }}" data-name="{{ $u->name }}"
                                    data-email="{{ $u->email }}" data-status="{{ $u->status }}">
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
                            <td colspan="6" class="text-center text-secondary py-4">Tidak ada data admin yang sesuai.
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
    <div class="modal modal-blur fade" id="modal-user" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" id="user-form" action="{{ route('pengguna.store') }}"
                data-store-url="{{ route('pengguna.store') }}" data-update-url="{{ route('pengguna.update', '__ID__') }}">
                @csrf
                <input type="hidden" name="_method" id="user-method" value="POST">
                <input type="hidden" name="form_mode" id="user-mode" value="create">
                <input type="hidden" name="form_id" id="user-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="user-title">Tambah Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="f-name">Nama</label>
                        <input type="text" id="f-name" name="name"
                            class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="f-email">Email</label>
                        <input type="email" id="f-email" name="email"
                            class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="f-status">Status</label>
                        <select id="f-status" name="status"
                            class="form-select {{ $errors->has('status') ? 'is-invalid' : '' }}">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="f-password">Kata sandi</label>
                        <input type="password" id="f-password" name="password" autocomplete="new-password"
                            class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Batal</button>
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

    @if ($errors->any())
        <button type="button" id="reopen-modal" class="d-none" data-bs-toggle="modal" data-bs-target="#modal-user"
            data-mode="{{ old('form_mode', 'create') }}" data-id="{{ old('form_id') }}"
            data-name="{{ old('name') }}" data-email="{{ old('email') }}"
            data-status="{{ old('status') }}"></button>
    @endif
@endsection

@push('myscript')
    <script>
        // 1. Notifikasi SweetAlert Sukses
        @if (session('status'))
            Swal.fire({
                title: "Berhasil!",
                text: "{{ session('status') }}",
                icon: "success",
                draggable: true,
                confirmButtonText: "OK"
            });
        @endif

        // 2. Notifikasi SweetAlert Gagal / Error
        @if (session('error'))
            Swal.fire({
                title: "Gagal!",
                text: "{{ session('error') }}",
                icon: "error",
                draggable: true,
                confirmButtonText: "Tutup"
            });
        @endif

        // 3. Konfirmasi Hapus Data dengan SweetAlert
        document.querySelectorAll('.btn-delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Hapus data ini?',
                    text: 'Data "' + btn.dataset.name + '" akan dihapus permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                    draggable: true
                }).then(function(r) {
                    if (r.isConfirmed) {
                        var f = document.getElementById('delete-form');
                        f.action = btn.dataset.url;
                        f.submit();
                    }
                });
            });
        });
    </script>
@endpush
