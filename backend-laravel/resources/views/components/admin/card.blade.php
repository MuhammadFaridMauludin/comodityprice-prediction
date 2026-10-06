@props(['title' => null, 'flush' => false])

{{-- Kartu serba guna. Slot bernama (opsional): actions (tombol di kanan judul), footer. --}}
<div {{ $attributes->class(['card']) }}>
    @if ($title || isset($actions))
        <div class="card-header">
            <h3 class="card-title">{{ $title }}</h3>
            @isset($actions)
                <div class="card-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['card-body' => !$flush])>{{ $slot }}</div>

    @isset($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endisset
</div>
