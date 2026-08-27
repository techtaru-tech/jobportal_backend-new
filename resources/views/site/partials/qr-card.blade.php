{{--
  The QR card.

  Server-rendered inline SVG (see `StoreQr`), so it is in the HTML on first
  paint: this is a thing people point a camera at, and one that pops in after a
  round trip is one they have already given up on.

  It encodes `/get-app/go`, not a store URL — that route reads the User-Agent
  and forwards, so a single code works for Android and iPhone alike, and every
  code already printed starts serving iOS the day it is switched on.
--}}
<div class="shrink-0 text-center">
    <div class="mx-auto rounded-card border-hair border-hairline bg-canvas p-md shadow-card [&>svg]:h-full [&>svg]:w-full {{ $size ?? 'w-[160px]' }}">
        {!! App\Support\StoreQr::svg() !!}
    </div>
    @if (($caption ?? false) !== null)
        <p class="mt-sm text-caption text-ink-muted">{{ $caption ?? 'Scan to install' }}</p>
    @endif
</div>
