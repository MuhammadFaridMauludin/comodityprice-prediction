<?php

namespace App\Http\Controllers;

use App\Support\DummyData;
use App\Support\DummyUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class DataController extends Controller
{
    public function harga(Request $request)
    {
        $rows = DummyData::filter(
            DummyData::prices(),
            $request,
            ['komoditas' => 'commodity', 'wilayah' => 'region', 'status' => 'status']
        );

        return view('data.index', [
            'type'    => 'harga',
            'rows'    => DummyData::paginate($rows, $request, 10),
            'selects' => [
                'komoditas' => array_keys(DummyData::COMMODITIES),
                'wilayah'   => array_slice(DummyData::REGIONS, 0, 4),
                'status'    => DummyData::DATA_STATUSES,
            ],
            'columns' => [
                'No', 'Tanggal', 'Komoditas', 'Wilayah',
                ['label' => 'Harga', 'align' => 'right'],
                'Status',
                ['label' => 'Aksi', 'align' => 'right'],
            ],
        ]);
    }

    public function cuaca(Request $request)
    {
        $rows = DummyData::filter(
            DummyData::weather(),
            $request,
            ['wilayah' => 'region', 'status' => 'status']
        );

        return view('data.index', [
            'type'    => 'cuaca',
            'rows'    => DummyData::paginate($rows, $request, 10),
            'selects' => [
                'wilayah' => DummyData::REGIONS,
                'status'  => DummyData::DATA_STATUSES,
            ],
            'columns' => [
                'No', 'Tanggal', 'Wilayah',
                ['label' => 'Suhu', 'align' => 'right'],
                ['label' => 'Curah Hujan', 'align' => 'right'],
                ['label' => 'Kelembapan', 'align' => 'right'],
                'Status',
                ['label' => 'Aksi', 'align' => 'right'],
            ],
        ]);
    }

    public function pengguna(Request $request)
    {
        $users = DummyUsers::all();

        if ($request->filled('q')) {
            $q     = mb_strtolower($request->query('q'));
            $users = $users->filter(fn ($u) => str_contains(mb_strtolower($u['name'].' '.$u['email']), $q));
        }

        $users = DummyData::filter($users, $request, ['peran' => 'role', 'status' => 'status']);

        return view('data.pengguna', [
            'users'    => DummyData::paginate($users, $request, 10),
            'selects'  => ['peran' => DummyUsers::ROLES, 'status' => DummyUsers::STATUSES],
            'roles'    => DummyUsers::ROLES,
            'statuses' => DummyUsers::STATUSES,
        ]);
    }

    public function penggunaStore(Request $request)
    {
        $data = $this->validatedUser($request);
        DummyUsers::create($data);

        return back()->with('status', 'Pengguna "'.$data['name'].'" berhasil ditambahkan.');
    }

    public function penggunaUpdate(Request $request, int $id)
    {
        abort_unless(DummyUsers::find($id), 404);

        $data = $this->validatedUser($request, $id);
        DummyUsers::update($id, $data);

        return back()->with('status', 'Data "'.$data['name'].'" berhasil diperbarui.');
    }

    public function penggunaDestroy(int $id)
    {
        $user = DummyUsers::find($id);
        abort_unless($user, 404);

        $admins = DummyUsers::all()->where('role', 'Administrator')->count();
        if ($user['role'] === 'Administrator' && $admins <= 1) {
            return back()->with('error', 'Administrator terakhir tidak dapat dihapus.');
        }

        DummyUsers::delete($id);

        return back()->with('status', 'Pengguna "'.$user['name'].'" berhasil dihapus.');
    }

    private function validatedUser(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', function ($attribute, $value, $fail) use ($id) {
                $taken = DummyUsers::all()->contains(
                    fn ($u) => strcasecmp($u['email'], $value) === 0 && $u['id'] !== $id
                );
                if ($taken) {
                    $fail('Email sudah dipakai pengguna lain.');
                }
            }],
            'role'     => ['required', Rule::in(DummyUsers::ROLES)],
            'status'   => ['required', Rule::in(DummyUsers::STATUSES)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
        ], [
            'name.required'     => 'Nama wajib diisi.',
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'role.in'           => 'Peran tidak valid.',
            'status.in'         => 'Status tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min'      => 'Kata sandi minimal 8 karakter.',
        ]);

        // Kata sandi sengaja TIDAK disimpan di data contoh.
        // Saat memakai database: $data['password'] = Hash::make($data['password']).
        return Arr::only($data, ['name', 'email', 'role', 'status']);
    }
}