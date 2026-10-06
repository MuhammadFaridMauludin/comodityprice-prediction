<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Data pengguna contoh yang disimpan di session, supaya tambah/ubah/hapus bisa dicoba. */
class DummyUsers
{
    private const KEY = 'dummy_users';

    public const ROLES    = ['Administrator', 'Analis Komoditas', 'Pengguna'];
    public const STATUSES = ['aktif', 'nonaktif'];

    public static function all(): Collection
    {
        if (! session()->has(self::KEY)) {
            session([self::KEY => self::seed()]);
        }

        return collect(session(self::KEY));
    }

    public static function find(int $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    public static function create(array $data): array
    {
        $users = self::all();
        $user  = $data + ['id' => ($users->max('id') ?? 0) + 1, 'last_login' => null];
        self::save($users->push($user));

        return $user;
    }

    public static function update(int $id, array $data): void
    {
        self::save(self::all()->map(fn ($u) => $u['id'] === $id ? array_merge($u, $data) : $u));
    }

    public static function delete(int $id): void
    {
        self::save(self::all()->reject(fn ($u) => $u['id'] === $id));
    }

    private static function save(Collection $users): void
    {
        session([self::KEY => $users->values()->all()]);
    }

    private static function seed(): array
    {
        $names = [
            'Muhammad Farid', 'Siti Nurhaliza', 'Budi Santoso', 'Dewi Lestari', 'Agus Prasetyo', 'Rina Wulandari',
            'Hendra Wijaya', 'Putri Ayu', 'Eko Saputra', 'Lina Marlina', 'Rizky Pratama', 'Maya Sari',
            'Dimas Anggara', 'Fitri Handayani', 'Yoga Permana', 'Nadia Rahma', 'Bayu Setiawan', 'Intan Permata',
            'Fajar Nugroho', 'Citra Kirana', 'Andi Wibowo', 'Mega Puspita', 'Rendi Hidayat', 'Tika Amalia',
            'Galih Kurniawan', 'Sekar Ayu', 'Taufik Hidayah', 'Wulan Dari',
        ];

        return collect($names)->map(fn ($name, $i) => [
            'id'         => $i + 1,
            'name'       => $name,
            'email'      => Str::slug($name, '.').'@example.com',
            'role'       => ($i === 0 || $i === 7) ? 'Administrator' : ($i % 3 === 0 ? 'Analis Komoditas' : 'Pengguna'),
            'status'     => $i % 7 === 5 ? 'nonaktif' : 'aktif',
            'last_login' => $i % 9 === 8 ? null : now()->subHours(($i + 1) * 5)->toIso8601String(),
        ])->all();
    }
}