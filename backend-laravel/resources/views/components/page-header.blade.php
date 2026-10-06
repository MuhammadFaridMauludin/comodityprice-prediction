@props(['title', 'meta' => null])

{{-- Kanan atas: teks $meta jika diisi, jika tidak tanggal hari ini. --}}
<div class="page-header">
    <h1>{{ $title }}</h1>
    @if ($meta)
        <span class="page-header__meta">{{ $meta }}</span>
    @else
        <time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('d M Y') }}</time>
    @endif
</div>
