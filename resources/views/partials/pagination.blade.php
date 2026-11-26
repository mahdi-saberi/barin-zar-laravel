@if($paginator->hasPages())
    <nav class="pagination" aria-label="صفحه‌بندی">
        @if($paginator->previousPageUrl())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
        @endif
        @foreach($elements as $element)
            @if(is_string($element))
                <span class="pagination-gap" aria-hidden="true">{{ $element }}</span>
            @else
                @foreach($element as $page => $url)
                    @if($page === $paginator->currentPage())
                        <span aria-current="page" aria-label="صفحه {{ $page }}">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="صفحه {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if($paginator->nextPageUrl())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
        @endif
    </nav>
@endif
