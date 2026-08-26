{{--
  Pagination for the API's `meta` block (`page`, `total_pages`, `total`).

  Page numbers rather than infinite scroll: an operator working a queue needs
  to know where they are in it and be able to come back to the same place.

  `state` names the Alpine state object, `loader` the method that fetches a page.
--}}
<template x-if="{{ $state }}.meta && {{ $state }}.meta.total_pages <= 1">
  <p class="px-lg py-md text-caption text-ink-muted">
    <span x-text="fmtNumber({{ $state }}.meta.total)"></span>
    <span x-text="{{ $state }}.meta.total === 1 ? 'result' : 'results'"></span>
  </p>
</template>

<template x-if="{{ $state }}.meta && {{ $state }}.meta.total_pages > 1">
  <div class="flex flex-wrap items-center justify-between gap-md px-lg py-md">
    <p class="text-caption text-ink-muted">
      Page <span x-text="{{ $state }}.meta.page"></span> of <span x-text="{{ $state }}.meta.total_pages"></span>
      · <span x-text="fmtNumber({{ $state }}.meta.total)"></span> results
    </p>
    <div class="flex items-center gap-sm">
      <button :disabled="{{ $state }}.meta.page <= 1" @click="{{ $loader }}({{ $state }}.meta.page - 1)"
              class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                     bg-surface text-primary border-btn border-primary transition-[background-color,border-color,transform] duration-micro ease-out
                     hover:bg-primary-light active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50 disabled:border-hairline">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-[18px] w-[18px]'])
        <span>Previous</span>
      </button>
      <button :disabled="{{ $state }}.meta.page >= {{ $state }}.meta.total_pages" @click="{{ $loader }}({{ $state }}.meta.page + 1)"
              class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                     bg-surface text-primary border-btn border-primary transition-[background-color,border-color,transform] duration-micro ease-out
                     hover:bg-primary-light active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50 disabled:border-hairline">
        <span>Next</span>
        @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])
      </button>
    </div>
  </div>
</template>
