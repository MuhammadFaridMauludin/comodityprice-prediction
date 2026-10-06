@props(['value', 'suffix' => '', 'decimals' => 1, 'arrow' => true, 'signed' => false])

@php
    $up   = $value >= 0;
    $text = ($arrow ? ($up ? '▲' : '▼').' ' : '')
          . ($signed ? ($up ? '+' : '-') : '')
          . number_format(abs($value), $decimals, ',', '.').'%'
          . ($suffix ? ' '.$suffix : '');
@endphp

<span {{ $attributes->class(['change', 'change--up' => $up, 'change--down' => ! $up]) }}>{{ $text }}</span>
