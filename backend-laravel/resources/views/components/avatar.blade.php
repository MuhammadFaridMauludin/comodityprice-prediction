@props(['name' => 'Pengguna', 'src' => null, 'size' => 44])

@php $initial = mb_strtoupper(mb_substr($name, 0, 1)); @endphp

<span class="avatar" style="--s: {{ $size }}px">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" onerror="this.remove()">
    @endif
    <span class="avatar__initial">{{ $initial }}</span>
</span>
