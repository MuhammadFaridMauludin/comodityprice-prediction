@props([
    'label',
    'value',
    'icon' => 'chart-bar',
    'color' => 'primary',
    'change' => null,
    'note' => null,
    'flat' => false,
])

<div {{ $attributes->class(['card card-stat' => !$flat, 'stat-tile' => $flat]) }}>
    <div @class([
        'card-body' => !$flat,
        'p-3' => $flat,
        'd-flex align-items-center gap-3',
    ])>
        <span class="avatar {{ $flat ? 'avatar-md' : 'avatar-lg' }} bg-{{ $color }}-lt">
            <i class="ti ti-{{ $icon }} fs-2"></i>
        </span>

        <div class="flex-fill">
            <div class="subheader">{{ $label }}</div>
            <div class="{{ $flat ? 'h2' : 'h1' }} mb-0">{{ $value }}</div>
            @if ($note)
                <div class="text-secondary small">{{ $note }}</div>
            @endif
        </div>

        @if (!is_null($change))
            <span class="badge {{ $change >= 0 ? 'bg-green-lt' : 'bg-red-lt' }}">
                <i class="ti ti-trending-{{ $change >= 0 ? 'up' : 'down' }}"></i>
                {{ number_format(abs($change), 1, ',', '.') }}%
            </span>
        @endif
    </div>
</div>
