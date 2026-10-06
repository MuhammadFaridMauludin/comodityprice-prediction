@props(['columns' => []])

{{-- Hanya tabelnya. Bungkus dengan <x-admin.card :flush="true"> supaya tampil sebagai kartu. --}}
<div class="table-responsive">
    <table {{ $attributes->class(['table', 'table-vcenter', 'table-hover', 'card-table']) }}>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    @php $col = is_array($col) ? $col : ['label' => $col]; @endphp
                    <th @class(['text-end' => ($col['align'] ?? 'left') === 'right'])>{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
