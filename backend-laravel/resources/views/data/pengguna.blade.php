@extends('layouts.admin.tabler')

@section('content')
    <x-admin.page-header pretitle="Overview" title="Pengguna" />

    <div class="page-body">
        <div class="container-xl">

            @if (session('status'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    {{ session('status') }}
                    <a href="#" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></a>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible" role="alert">
                    {{ session('error') }}
                    <a href="#" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></a>
                </div>
            @endif

            <x-admin.card title="Daftar Pengguna" :flush="true">
                <x-slot name="actions">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#modal-user" data-mode="create">
                        <i class="ti ti-plus"></i> Tambah Pengguna
                    </button>
                </x-slot>

                <x-admin.filters :selects="$selects" :search="true" />

                <x-admin.table :columns="[
                    'No',
                    'Nama',
                    'Email',
                    'Peran',
                    'Status',
                    'Login terakhir',
                    ['label' => 'Aksi', 'align' => 'right'],
                ]">
                    @forelse ($users as $u)
                        <tr>
                            <td>{{ $users->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span
                                        class="avatar avatar-sm me-2">{{ mb_strtoupper(mb_substr($u['name'], 0, 1)) }}</span>
                                    <span>{{ $u['name'] }}</span>
                                </div>
                            </td>
                            <td class="text-secondary">{{ $u['email'] }}</td>
                            <td>{{ $u['role'] }}</td>
                            <td><x-admin.badge :status="$u['status']" /></td>
                            <td class="text-secondary">
                                {{ $u['last_login'] ? \Illuminate\Support\Carbon::parse($u['last_login'])->diffForHumans() : 'Belum pernah' }}
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary" aria-label="Ubah"
                                    data-bs-toggle="modal" data-bs-target="#modal-user" data-mode="edit"
                                    data-id="{{ $u['id'] }}" data-name="{{ $u['name'] }}"
                                    data-email="{{ $u['email'] }}" data-role="{{ $u['role'] }}"
                                    data-status="{{ $u['status'] }}">
                                    <i class="ti ti-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-icon btn-sm btn-ghost-danger btn-delete"
                                    aria-label="Hapus" data-name="{{ $u['name'] }}"
                                    data-url="{{ route('pengguna.destroy', $u['id']) }}">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">Tidak ada pengguna yang sesuai.</td>
                        </tr>
                    @endforelse
                </x-admin.table>

                <x-slot name="footer">
                    {{ $users->links('vendor.pagination.tabler') }}
                </x-slot>
            </x-admin.card>
        </div>
    </div>

    {{-- Modal tambah / ubah (satu modal untuk keduanya) --}}
    <div class="modal modal-blur fade" id="modal-user" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" id="user-form" action="{{ route('pengguna.store') }}"
                data-store-url="{{ route('pengguna.store') }}" data-update-url="{{ route('pengguna.update', '__ID__') }}">
                @csrf
                <input type="hidden" name="_method" id="user-method" value="POST">
                <input type="hidden" name="form_mode" id="user-mode" value="create">
                <input type="hidden" name="form_id" id="user-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="user-title">Tambah Pengguna</h5>
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

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="f-role">Peran</label>
                            <select id="f-role" name="role"
                                class="form-select {{ $errors->has('role') ? 'is-invalid' : '' }}">
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-sm-6">
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

    {{-- Form tersembunyi untuk hapus --}}
    <form id="delete-form" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    {{-- Jika validasi gagal, buka kembali modal dengan isian sebelumnya --}}
    @if ($errors->any())
        <button type="button" id="reopen-modal" class="d-none" data-bs-toggle="modal" data-bs-target="#modal-user"
            data-mode="{{ old('form_mode', 'create') }}" data-id="{{ old('form_id') }}"
            data-name="{{ old('name') }}" data-email="{{ old('email') }}" data-role="{{ old('role') }}"
            data-status="{{ old('status') }}"></button>
    @endif
@endsection

@push('myscript')
    <script>
        (function() {
                var modal = document.getElementById('modal-user');
                var form = document.getElementById('user-form');

                // Isi modal saat dibuka: mode tambah (kosong) atau ubah (data baris yang diklik)
                modal.addEventListener('show.bs.modal',
