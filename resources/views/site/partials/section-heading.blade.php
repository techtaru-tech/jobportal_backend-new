{{--
  A section label.

  Two layouts, because the page needs both:

  - **Centred** (default) — an eyebrow pill in solid brand red over a centred
    heading, the shape the marketing pages lead with. The pill is a filled
    block rather than the admin panel's quiet text eyebrow: on a public page
    the brand should be the first thing that registers, not a grey caption.

  - **Left** — used when the section carries an `action` link, because a
    centred title with a right-aligned button reads as two unrelated things
    rather than a heading and its overflow.

  Pass `centered => false` to force the left layout without an action.
--}}
@php
    $hasAction = isset($action);
    // An action forces the left layout: nothing else can hold the link.
    $isCentered = $hasAction ? false : ($centered ?? true);
@endphp

@if ($isCentered)
    <div class="mb-xl text-center">
        @isset($kicker)
            <span class="inline-block rounded-chip bg-primary px-lg py-xs text-kicker font-semibold uppercase tracking-[0.10em] text-ink-onPrimary shadow-button">
                {{ strtoupper($kicker) }}
            </span>
        @endisset
        <h2 class="mt-md text-h2 text-ink">{{ $title }}</h2>
        @isset($hint)
            {{-- Held well short of the column width: a centred paragraph that
                 runs the full 1200px is a line nobody's eye can track back. --}}
            <p class="mx-auto mt-sm max-w-[560px] text-bodysm text-ink-secondary">{{ $hint }}</p>
        @endisset
    </div>
@else
    <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
        <div class="min-w-0">
            @isset($kicker)
                <span class="inline-block rounded-chip bg-primary px-md py-[3px] text-kicker font-semibold uppercase tracking-[0.10em] text-ink-onPrimary">
                    {{ strtoupper($kicker) }}
                </span>
            @endisset
            <h2 class="mt-sm text-h2 text-ink">{{ $title }}</h2>
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
@endif
