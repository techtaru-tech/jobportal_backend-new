{{--
  Paginator styled to match the admin panel's, and rendered as real links.

  Anchors rather than buttons on purpose: a crawler has to be able to walk from
  page one to the rest, and a candidate has to be able to open page three in a
  new tab. Laravel's default view is Tailwind-flavoured but not *this*
  Tailwind — none of the design tokens exist in it.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-md">
        <p class="text-caption text-ink-muted">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            of {{ number_format($paginator->total()) }}
        </p>

        <div class="flex items-center gap-sm">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-[42px] cursor-not-allowed items-center gap-sm rounded-button border-btn border-hairline bg-surface px-md text-btn font-semibold text-ink-muted opacity-50">
                    @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-[18px] w-[18px]'])
                    <span class="hidden sm:inline">Previous</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex h-[42px] items-center gap-sm rounded-button border-btn border-primary bg-surface px-md text-btn font-semibold text-primary
                          transition-colors duration-micro hover:bg-primary-light">
                    @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-[18px] w-[18px]'])
                    <span class="hidden sm:inline">Previous</span>
                </a>
            @endif

            <span class="px-sm text-bodysm tabular-nums text-ink-secondary">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex h-[42px] items-center gap-sm rounded-button border-btn border-primary bg-surface px-md text-btn font-semibold text-primary
                          transition-colors duration-micro hover:bg-primary-light">
                    <span class="hidden sm:inline">Next</span>
                    @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])
                </a>
            @else
                <span class="inline-flex h-[42px] cursor-not-allowed items-center gap-sm rounded-button border-btn border-hairline bg-surface px-md text-btn font-semibold text-ink-muted opacity-50">
                    <span class="hidden sm:inline">Next</span>
                    @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])
                </span>
            @endif
        </div>
    </nav>
@endif
