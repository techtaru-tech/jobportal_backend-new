{{-- The empty state. `tone === 'error'` swaps the wash and glyph to danger. --}}
@php $tone = $tone ?? 'neutral'; @endphp
<div class="flex flex-col items-center justify-center p-xxxl text-center">
  <div class="relative flex h-[76px] w-[76px] items-center justify-center rounded-card border-hair [&>svg]:h-[28px] [&>svg]:w-[28px] {{ $tone === 'error' ? 'border-danger/25 bg-danger-bg text-danger' : 'red-wash border-hairline text-ink-muted' }}">
    @include('admin.partials.icon', ['name' => $icon ?? 'inbox', 'class' => 'h-[28px] w-[28px]'])
  </div>
  @isset($title)
    <h3 class="mt-lg text-h4 text-ink">{{ $title }}</h3>
  @endisset
  <p class="max-w-sm text-bodysm text-ink-secondary {{ isset($title) ? 'mt-xs' : 'mt-lg' }}">{!! $message !!}</p>
</div>
