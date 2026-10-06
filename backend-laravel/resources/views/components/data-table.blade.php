@props(['title' => null, 'columns' => []])

{{--
    Tabel serba guna. Baris ditulis di dalam slot; slot bernama "footer" bersifat opsional
    (misalnya untuk pagination):
    <x-data-table title="..." :columns="['Tanggal', ['label' => 'Selisih', 'align' => 'right']]">
        <tr><td>...</td></tr>
        <x-slot name="footer"> ... </x-slot>
    </x-data-table>
--}}
<section {{ $attributes->class(['card', 'table-card']) }}>
    @if ($title)
        <h2 class="table-card__title">{{ $title }}</h2>
    @endif

    <div class="table-card__scroll">
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        @php $col = is_array($col) ? $col : ['label' => $col]; @endphp
                        <th scope="col" @class(['num' => ($col['align'] ?? 'left') === 'right'])>{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>

    @isset($footer)
        <div class="table-card__footer">{{ $footer }}</div>
    @endisset
</section>
