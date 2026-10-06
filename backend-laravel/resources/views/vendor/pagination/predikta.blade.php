{{-- Tampilan pagination Predikta. Dipanggil: $rows->links('vendor.pagination.predikta') --}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Navigasi halaman">
        <p class="pager__info">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>

        <ul class="pager__list">
            {{-- Sebelumnya --}}
            @if ($paginator->onFirstPage())
                <li><span class="pager__btn is-disabled" aria-disabled="true">‹</span></li>
            @else
                <li><a class="pager__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">‹</a></li>
            @endif

            {{-- Nomor halaman --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pager__gap">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><span class="pager__btn is-active" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a class="pager__btn" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Berikutnya --}}
            @if ($paginator->hasMorePages())
                <li><a class="pager__btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">›</a></li>
            @else
                <li><span class="pager__btn is-disabled" aria-disabled="true">›</span></li>
            @endif
        </ul>
    </nav>
@endif
