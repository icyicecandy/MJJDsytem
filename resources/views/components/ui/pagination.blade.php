@props(['paginator'])
@if($paginator->hasPages())
    <nav class="ui-pagination" aria-label="Pagination">
        @if($paginator->onFirstPage())
            <span class="btn secondary is-disabled" aria-disabled="true">Previous</span>
        @else
            <a class="btn secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        <span class="ui-pagination__summary">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())
            <a class="btn secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="btn secondary is-disabled" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
