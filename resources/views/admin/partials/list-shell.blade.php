{{--
  The shared shape of every list page: a search box, an optional filter row, a
  table, and pagination — wired to the API's `{data, meta}` envelope once
  instead of five times.

  Every branch renders inside the same bordered shell, so the frame stays put
  and only its contents swap: the table does not appear to pop into existence
  once the request lands. A refetch keeps the current rows on screen and dims
  them rather than dropping back to skeletons — losing your place in a queue on
  every filter change is worse than half a second of stale data.

  `state`, `loader` and `cols` name the Alpine pieces; `slot` is the <tbody>.
--}}
<div>
  <div class="mb-lg flex flex-col gap-md sm:flex-row sm:items-center sm:justify-between">
    @if (($showSearch ?? true))
      <div class="relative w-full sm:max-w-sm">
        <span x-html="ICONS.search" aria-hidden="true"
              class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 [&>svg]:h-[18px] [&>svg]:w-[18px] text-ink-muted"></span>
        <input type="search" x-model.debounce.400ms="{{ $state }}.q" @input="{{ $loader }}(1)"
               placeholder="{{ $placeholder ?? 'Search…' }}"
               class="w-full h-[46px] rounded-field bg-surface-muted pl-[42px] pr-md text-input text-ink placeholder:text-ink-muted
                      border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                      focus:border-focus focus:border-primary focus:shadow-glow
                      [&::-webkit-search-cancel-button]:appearance-none">
      </div>
    @else
      <div></div>
    @endif
    <div class="flex flex-wrap items-center gap-sm">{!! $filters ?? '' !!}</div>
  </div>

  <div :class="{{ $state }}.busy && {{ $state }}.data.length && 'pointer-events-none opacity-60 transition-opacity duration-component'"
       class="overflow-hidden rounded-card border-hair border-hairline bg-surface">

    <template x-if="{{ $state }}.busy && !{{ $state }}.data.length">
      @include('admin.partials.skeleton-table', ['cols' => $cols ?? 5])
    </template>

    <template x-if="{{ $state }}.error && !{{ $state }}.data.length">
      @include('admin.partials.empty-state', [
        'tone' => 'error', 'icon' => 'alert', 'title' => 'Something went wrong',
        'message' => 'That did not load. Please try again.',
      ])
    </template>

    <template x-if="!{{ $state }}.busy && !{{ $state }}.error && !{{ $state }}.data.length">
      @include('admin.partials.empty-state', [
        'icon' => 'search', 'title' => $emptyTitle ?? 'Nothing here yet',
        'message' => $emptyMessage ?? 'Try a different search or filter.',
      ])
    </template>

    <template x-if="{{ $state }}.data.length">
      <div>
        <div class="thin-scrollbar overflow-x-auto">
          <table class="w-full border-collapse text-left">
            {{-- Sticky header: a queue is often hundreds of rows, and losing the
                 column labels three screens down is what makes an operator
                 scroll back up to remember which number is which. --}}
            <thead class="sticky top-0 z-10 bg-surface">
              <tr>{!! $head !!}</tr>
            </thead>
            <tbody>{!! $body !!}</tbody>
          </table>
        </div>
        <div class="border-t border-hairline-divider">
          @include('admin.partials.pagination', ['state' => $state, 'loader' => $loader])
        </div>
      </div>
    </template>
  </div>
</div>
