<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const STATUSES = ['aktif', 'nonaktif'];

    public function index(Request $request)
    {
        $query = User::query();

        // 1. Filter Pencarian Nama / Email
        $query->when($request->filled('q'), function ($q) use ($request) {
            $search = $request->q;
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });

        // 2. Filter Status
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        $users = $query->latest()->paginate(10)->withQueryString();

        return view('data.pengguna', [
            'users'    => $users,
            'selects'  => [
                'status' => array_combine(self::STATUSES, array_map('ucfirst', self::STATUSES)),
            ],
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedUser($request);
        $data['role'] = 'Admin'; // Otomatis diset sebagai Admin
        $data['password'] = Hash::make($request->password);

        User::create($data);

        return back()->with('status', 'Admin "' . $data['name'] . '" berhasil ditambahkan.');
    }

    public function edit(int $id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $this->validatedUser($request, $id);
        $data['role'] = 'Admin';

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('status', 'Data Admin "' . $data['name'] . '" berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $user = User::findOrFail($id);

        // Mencegah menghapus Admin terakhir di database
        if (User::count() <= 1) {
            return back()->with('error', 'Admin terakhir tidak dapat dihapus.');
        }

        // Mencegah menghapus akun sendiri yang sedang login
        if (Auth::id() === $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return back()->with('status', 'Admin "' . $user->name . '" berhasil dihapus.');
    }

    private function validatedUser(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'status'   => ['required', Rule::in(self::STATUSES)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
        ], [
            'name.required'     => 'Nama wajib diisi.',
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'email.unique'      => 'Email sudah dipakai pengguna lain.',
            'status.in'         => 'Status tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min'      => 'Kata sandi minimal 8 karakter.',
        ]);
    }
}