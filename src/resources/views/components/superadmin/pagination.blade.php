{{-- Paginator view for superadmin lists: `$paginator->links('components.superadmin.pagination')`.
     Receives $paginator and $elements from Laravel's paginator; renders nothing on a single page. --}}
@if ($paginator->hasPages())
    @php
        $previousLabel = trim(html_entity_decode(strip_tags(__('pagination.previous'))), "«» \u{00A0}");
        $nextLabel = trim(html_entity_decode(strip_tags(__('pagination.next'))), "«» \u{00A0}");
    @endphp
    <nav class="sa-pagination" role="navigation" aria-label="{{ __('pagination.navigation') }}">
        <p class="sa-pagination-summary">
            {{ __('pagination.summary', ['first' => $paginator->firstItem() ?? 0, 'last' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}
        </p>
        <div class="sa-pagination-pages">
            @if ($paginator->onFirstPage())
                <span class="sa-page is-disabled" aria-disabled="true" aria-label="{{ $previousLabel }}"><span class="material-symbols-outlined" aria-hidden="true">chevron_left</span></span>
            @else
                <a class="sa-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ $previousLabel }}"><span class="material-symbols-outlined" aria-hidden="true">chevron_left</span></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="sa-page is-gap" aria-hidden="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="sa-page is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="sa-page" href="{{ $url }}" aria-label="{{ __('pagination.go_to_page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="sa-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ $nextLabel }}"><span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></a>
            @else
                <span class="sa-page is-disabled" aria-disabled="true" aria-label="{{ $nextLabel }}"><span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></span>
            @endif
        </div>
    </nav>
@endif
