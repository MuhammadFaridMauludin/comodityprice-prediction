@if ($paginator->hasPages())
    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
        <p class="m-0 text-secondary small">
            Menampilkan <b>{{ $paginator->firstItem() }}</b>–<b>{{ $paginator->lastItem() }}</b>
            dari <b>{{ $paginator->total() }}</b> data
        </p>

        <ul class="pagination m-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="ti ti-chevron-left"></i> Sebelumnya</span>
                </li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i
                            class="ti ti-chevron-left"></i> Sebelumnya</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="page-item {{ $page == $paginator->currentPage() ? 'active' : '' }}">
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        </li>
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}"
                        rel="next">Berikutnya <i class="ti ti-chevron-right"></i></a></li>
            @else
                <li class="page-item disabled"><span class="page-link">Berikutnya <i
                            class="ti ti-chevron-right"></i></span></li>
            @endif
        </ul>
    </div>
@endif
