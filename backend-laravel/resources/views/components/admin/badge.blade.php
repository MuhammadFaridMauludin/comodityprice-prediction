@props(['status'])

@php
    $map = [
        'sukses' => ['green', 'Sukses'],
        'gagal' => ['red', 'Gagal'],
        'peringatan' => ['orange', 'Peringatan'],
        'berjalan' => ['blue', 'Berjalan'],
        'aktif' => ['green', 'Aktif'],
        'valid' => ['green', 'Valid'],
        'interpolasi' => ['azure', 'Interpolasi'],
        'anomali' => ['orange', 'Anomali'],
    ];
    [$color, $label] = $map[strtolower($status)] ?? ['secondary', ucfirst($status)];
@endphp

<span class="badge bg-{{ $color }}-lt">{{ $label }}</span>
