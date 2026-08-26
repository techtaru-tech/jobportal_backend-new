{{-- The standard page header: a Kicker eyebrow over an h1, room for actions. --}}
<div class="mb-lg flex flex-wrap items-end justify-between gap-md">
  <div class="min-w-0">
    <span class="block text-kicker text-ink-secondary">{{ strtoupper($kicker) }}</span>
    <h1 class="mt-[2px] text-h2 text-ink">{!! $title !!}</h1>
    @isset($description)
      <p class="mt-xs max-w-3xl text-bodysm text-ink-secondary">{{ $description }}</p>
    @endisset
  </div>
  @isset($actions)
    <div class="flex flex-wrap items-center gap-sm">{!! $actions !!}</div>
  @endisset
</div>
