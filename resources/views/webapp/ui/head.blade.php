{{-- A screen title row with a back button.
     @include('webapp.ui.head', ['title' => 'Education', 'back' => '/profile', 'subtitle' => 'Optional line'])
     'titleExpr' => JS expression instead of a static title. --}}
<div class="mb-xl flex items-start gap-md">
  @isset($back)
    <button type="button" @click="back('{{ $back }}')" aria-label="Back"
            class="-ml-sm mt-[2px] inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
      @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
    </button>
  @endisset
  <div class="min-w-0 flex-1">
    @isset($titleExpr)
      <h1 class="text-h2 text-ink" x-text="{{ $titleExpr }}"></h1>
    @else
      <h1 class="text-h2 text-ink">{{ $title }}</h1>
    @endisset
    @if (!empty($subtitle))<p class="mt-xs text-bodysm text-ink-secondary">{{ $subtitle }}</p>@endif
  </div>
</div>
