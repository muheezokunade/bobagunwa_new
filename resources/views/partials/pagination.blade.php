@if ($paginator->hasPages())
    <nav aria-label="Pagination">
        <ul class="pagination">
            @if (! $paginator->onFirstPage())<li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a></li>@endif
            @foreach ($elements as $element)
                @if (is_string($element))<li><span>{{ $element }}</span></li>@endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li aria-current="page"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())<li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a></li>@endif
        </ul>
        <p class="muted small">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
    </nav>
@endif
