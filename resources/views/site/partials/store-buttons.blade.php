{{--
  The store buttons.

  Android is always shown. iOS appears only once `IOS_STORE_URL` is set — the
  app is not on the App Store yet, and a dead "Download on the App Store"
  button is worse than none: it spends an iPhone user's tap to tell them
  nothing. While it is missing, one honest line says so instead.

  Both go through `/get-app/go`, which reads the User-Agent and forwards, so a
  visitor who taps the "wrong" one still lands in the right place.
--}}
@php $hasIos = App\Support\StoreQr::iosUrl() !== null; @endphp

<div class="mt-lg flex flex-wrap items-center gap-sm {{ $align ?? '' }}">
    <a href="{{ App\Support\StoreQr::storeUrl() }}"
       class="sheen inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-primary px-xl text-btn font-semibold text-ink-onPrimary shadow-button
              transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="currentColor" aria-hidden="true">
            <path d="M3.6 1.8a1 1 0 0 0-.5.9v18.6a1 1 0 0 0 .5.9l10.2-10.2L3.6 1.8Zm11.6 8.6 2.9-2.9-8.4-4.8 5.5 7.7Zm0 3.2-5.5 7.7 8.4-4.8-2.9-2.9Zm4.3-4.3-2.3 2.3 2.3 2.3 2.1-1.2a1.3 1.3 0 0 0 0-2.2l-2.1-1.2Z"/>
        </svg>
        <span>Get it on Google Play</span>
    </a>

    @if ($hasIos)
        <a href="{{ App\Support\StoreQr::storeUrl() }}"
           class="sheen inline-flex h-[50px] items-center justify-center gap-sm rounded-button border-btn border-hairline bg-surface px-xl text-btn font-semibold text-ink
                  transition-colors duration-micro hover:border-hairline-strong hover:bg-surface-muted">
            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="currentColor" aria-hidden="true">
                <path d="M16.4 12.7c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.7-1.8-3.3-1.8-1.5-.1-2.7.8-3.4.8-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 6.9 1.2 9.2.8 1.1 1.7 2.3 2.9 2.3 1.2 0 1.6-.7 3-.7 1.4 0 1.8.7 3 .7 1.2 0 2-1.1 2.8-2.2.6-.9.9-1.7 1-2.1-2.1-.8-2.4-3.9-2.4-4.2ZM14.3 5.4c.6-.8 1-1.8.9-2.9-.9.1-2 .6-2.6 1.4-.6.7-1.1 1.7-.9 2.7 1 .1 2-.5 2.6-1.2Z"/>
            </svg>
            <span>Download on the App Store</span>
        </a>
    @endif
</div>

{{-- Only while iOS is genuinely unavailable. Setting IOS_STORE_ID removes
     this line and adds the button above, together. --}}
@unless ($hasIos)
    <p class="mt-sm text-caption text-ink-muted {{ $align ?? '' }} flex">Android today · iOS coming soon</p>
@endunless

@if ($hasIos)
    <p class="mt-sm text-caption text-ink-muted {{ $align ?? '' }} flex">Free on Android and iOS</p>
@endif
