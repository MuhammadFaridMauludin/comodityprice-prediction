@props(['selects' => [], 'date' => false, 'search' => false])

<form method="GET" action="{{ url()->current() }}" class="px-3 pb-3 d-flex flex-wrap align-items-center gap-2">
    @if ($search)
        <div class="input-icon" style="min-width: 240px">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                placeholder="Cari..." aria-label="Cari">
        </div>
    @endif

    {{-- Dropdown Selects --}}
    @foreach ($selects as $name => $options)
        <select name="{{ $name }}" class="form-select form-select-sm w-auto" onchange="this.form.submit()"
            aria-label="Filter {{ $name }}">
            <option value="">{{ ucfirst(str_replace('_', ' ', $name)) }}: semua</option>
            @foreach ($options as $key => $value)
                <option value="{{ $key }}" {{ (string) request($name) === (string) $key ? 'selected' : '' }}>
                    {{ ucfirst($value) }}
                </option>
            @endforeach
        </select>
    @endforeach

    {{-- Input Rentang Tanggal (Mulai - Selesai) --}}
    @if ($date)
        <div class="d-flex align-items-center gap-1">
            <input type="date" name="tgl_mulai" value="{{ request('tgl_mulai') }}"
                class="form-control form-control-sm w-auto" onchange="this.form.submit()" title="Dari Tanggal"
                aria-label="Tanggal Mulai">

            <span class="text-secondary">-</span>

            <input type="date" name="tgl_selesai" value="{{ request('tgl_selesai') }}"
                class="form-control form-control-sm w-auto" onchange="this.form.submit()" title="Sampai Tanggal"
                aria-label="Tanggal Selesai">
        </div>
    @endif

    {{-- Tombol Reset --}}
    @if (request()->hasAny(array_merge(array_keys($selects), $date ? ['tgl_mulai', 'tgl_selesai'] : [], $search ? ['q'] : [])))
        <a href="{{ url()->current() }}" class="btn btn-sm btn-link text-danger">Reset Filter</a>
    @endif
</form>
