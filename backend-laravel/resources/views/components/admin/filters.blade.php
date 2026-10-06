@props(['selects' => [], 'date' => false, 'search' => false])

{{-- Filter lewat parameter URL (GET). $selects = ['nama' => [pilihan, ...]] --}}
<form method="GET" action="{{ url()->current() }}" class="px-3 pb-3 d-flex flex-wrap align-items-center gap-2">
    @if ($search)
        <div class="input-icon" style="min-width: 240px">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                placeholder="Cari nama atau email..." aria-label="Cari">
        </div>
    @endif

    @foreach ($selects as $name => $options)
        <select name="{{ $name }}" class="form-select form-select-sm w-auto" onchange="this.form.submit()"
            aria-label="Filter {{ $name }}">
            <option value="">{{ ucfirst($name) }}: semua</option>
            @foreach ($options as $option)
                <option value="{{ $option }}" {{ request($name) === $option ? 'selected' : '' }}>
                    {{ ucfirst($option) }}</option>
            @endforeach
        </select>
    @endforeach

    @if ($date)
        <input type="date" name="tanggal" value="{{ request('tanggal') }}"
            class="form-control form-control-sm w-auto" onchange="this.form.submit()" aria-label="Filter tanggal">
    @endif

    @if (request()->hasAny(array_merge(array_keys($selects), $date ? ['tanggal'] : [], $search ? ['q'] : [])))
        <a href="{{ url()->current() }}" class="btn btn-sm btn-link">Reset</a>
    @endif
</form>
