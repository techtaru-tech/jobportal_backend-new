{{-- @include('webapp.ui.empty', ['icon' => 'inbox', 'title' => 'Nothing here yet', 'text' => 'Optional line']) --}}
<div class="flex flex-col items-center justify-center rounded-card border-hair border-dashed border-hairline-strong bg-surface px-xl py-xxxl text-center">
  <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
    @include('admin.partials.icon', ['name' => $icon ?? 'inbox', 'class' => 'h-6 w-6'])
  </span>
  <p class="mt-lg text-h4 text-ink">{{ $title }}</p>
  @if (!empty($text))<p class="mt-xs max-w-[360px] text-bodysm text-ink-secondary">{{ $text }}</p>@endif
</div>
