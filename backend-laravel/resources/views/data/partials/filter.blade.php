{{-- Filter rentang tanggal + rata-rata. Butuh: $active, $dari, $sampai, $min, $max, $average --}}
<form method="GET" action="{{ route('historis') }}" class="card filter">
    <input type="hidden" name="komoditas" value="{{ $active }}">

    <div class="filter__dates">
        <div class="field">
            <label for="dari">Dari tanggal</label>
            <input type="date" id="dari" name="dari" value="{{ $dari }}"
                   min="{{ $min }}" max="{{ $max }}" onchange="this.form.submit()">
        </div>
        <div class="field">
            <label for="sampai">Sampai tanggal</label>
            <input type="date" id="sampai" name="sampai" value="{{ $sampai }}"
                   min="{{ $min }}" max="{{ $max }}" onchange="this.form.submit()">
        </div>
    </div>

    <noscript><button type="submit">Terapkan</button></noscript>

    <div class="filter__avg">
        <span>Rata-rata rentang ini</span>
        <strong>Rp {{ number_format($average, 0, ',', '.') }}</strong>
    </div>
</form>
