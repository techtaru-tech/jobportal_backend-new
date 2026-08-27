{{--
  A section label, matching the admin panel's eyebrow-over-title idiom so the
  two surfaces read as one product: a Kicker, the heading, and a red rule
  running out of it rather than a full-width border that would divide the page
  instead of labelling it.
--}}
<div class="mb-lg flex flex-wrap items-end justify-between gap-md">
    <div class="min-w-0">
        @isset($kicker)
            <span class="block text-kicker text-ink-secondary">{{ strtoupper($kicker) }}</span>
        @endisset
        <h2 class="mt-[2px] text-h2 text-ink">{{ $title }}</h2>
        @isset($hint)
            <p class="mt-xs text-bodysm text-ink-secondary">{{ $hint }}</p>
        @endisset
    </div>

    @isset($action)
        <a href="{{ $action['href'] }}"
           class="inline-flex shrink-0 items-center gap-xs rounded-button px-md py-sm text-btnghost font-semibold text-primary-dark
                  transition-colors duration-micro hover:bg-primary-light">
            {{ $action['label'] }}
            @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-4 w-4'])
        </a>
    @endisset
</div>
