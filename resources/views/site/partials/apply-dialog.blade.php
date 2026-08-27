{{--
  What "Apply" does on the web.

  Applying is an app flow, not a web one: it needs the candidate's profile, a
  resume, and Smart Apply's per-posting field gating, none of which exist here.
  Building a second, thinner apply path on the web would produce applications an
  employer cannot read the same way — so the site does the honest thing and
  hands the person their phone instead.

  A QR, because the visitor reading this on a laptop is exactly who cannot tap a
  Play Store link. On a phone the same dialog leads with the button and keeps the
  QR as the secondary option, since scanning your own screen is not a thing.

  The QR is rendered server-side as inline SVG (see `StoreQr`) — no image
  request, no JS library, sharp at any size.
--}}
<div x-show="applyOpen" x-cloak
     @keydown.escape.window="applyOpen = false"
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
     role="dialog" aria-modal="true" aria-label="Apply in the Inthes app">

  <div x-show="applyOpen" @click="applyOpen = false"
       x-transition:enter="transition-opacity duration-component" x-transition:enter-start="opacity-0"
       x-transition:leave="transition-opacity duration-micro" x-transition:leave-end="opacity-0"
       class="absolute inset-0 bg-black/45"></div>

  <div x-show="applyOpen"
       x-transition:enter="transition duration-component ease-out"
       x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95 sm:translate-y-0"
       x-transition:leave="transition duration-micro"
       x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95 sm:translate-y-0"
       class="relative w-full max-w-[440px] rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog">

    <button @click="applyOpen = false" aria-label="Close"
            class="absolute right-lg top-lg inline-flex h-9 w-9 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors hover:bg-hairline">
      <span x-html="ICONS.x" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
    </button>

    <div class="text-center">
      @include('admin.partials.logo', ['size' => 44, 'class' => 'mx-auto'])
      <span class="mt-md block text-kicker text-ink-secondary">APPLY IN THE APP</span>
      <h2 class="mt-xs text-h2 text-ink">Scan to download</h2>

      {{-- The posting's own title, so the dialog is clearly about the job the
           person was just reading rather than a generic advert. --}}
      <p class="mt-sm text-bodysm text-ink-secondary">
        <template x-if="applyJob">
          <span>Applying for <span class="font-semibold text-ink" x-text="applyJob"></span> happens in the Inthes app, where your profile and resume already are.</span>
        </template>
        <template x-if="!applyJob">
          <span>Applying happens in the Inthes app, where your profile and resume already are.</span>
        </template>
      </p>
    </div>

    {{-- Order flips by viewport: the QR leads on a desktop, the button leads on
         a phone. Both are always present — a phone with a second device to
         scan from is not unusual, and a desktop user may want the link. --}}
    <div class="mt-xl flex flex-col items-center gap-lg">
      <div class="order-2 w-full sm:order-1">
        <div class="mx-auto w-[196px] rounded-card border-hair border-hairline bg-canvas p-md shadow-card [&>svg]:h-full [&>svg]:w-full">
          {!! App\Support\StoreQr::svg() !!}
        </div>
        <p class="mt-sm text-center text-caption text-ink-muted">Point your phone camera at the code</p>
      </div>

      <a href="{{ App\Support\StoreQr::storeUrl() }}" target="_blank" rel="noopener"
         class="order-1 flex h-[50px] w-full items-center justify-center gap-sm rounded-button bg-primary text-btn font-semibold text-ink-onPrimary shadow-button
                transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97] sm:order-2">
        Open Google Play
      </a>
    </div>

    <p class="mt-lg text-center text-caption text-ink-muted">
      Free to download. Sign in with your phone number — no CV upload needed to start.
    </p>
  </div>
</div>
