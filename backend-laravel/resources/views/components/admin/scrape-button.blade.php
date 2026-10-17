@props(['route', 'label' => 'Scraping', 'terakhir' => null])

<div class="d-flex align-items-center gap-3">
    @if ($terakhir)
        <span class="text-secondary small">
            Data terakhir: {{ \Carbon\Carbon::parse($terakhir)->translatedFormat('d M Y') }}
        </span>
    @endif

    <form action="{{ $route }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">{{ $label }}</button>
    </form>
</div>
