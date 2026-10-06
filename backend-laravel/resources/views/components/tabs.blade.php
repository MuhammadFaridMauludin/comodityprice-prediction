@props(['items' => [], 'active' => null, 'route', 'param' => 'tab', 'query' => []])

{{-- Tab pilihan berbentuk pil. Link: route($route, $query + [$param => $key]).
     $query dipakai untuk mempertahankan parameter lain (misalnya rentang tanggal). --}}
<nav class="tabs" aria-label="Pilihan">
    @foreach ($items as $key => $label)
        <a href="{{ route($route, array_merge($query, [$param => $key])) }}"
           class="tab {{ $active === $key ? 'is-active' : '' }}"
           @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
