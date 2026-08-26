{{--
  A section label with a red rule running out of it. Groups the cards beneath
  into one idea, so a long dashboard reads as three or four sections rather
  than fourteen unrelated boxes.
--}}
<div class="mb-md flex items-baseline gap-md">
  <div class="shrink-0">
    <h2 class="text-h4 text-ink">{{ $title }}</h2>
    @isset($hint)<p class="mt-[2px] text-caption text-ink-muted">{{ $hint }}</p>@endisset
  </div>
  <span class="red-rule h-[1px] flex-1 opacity-40" aria-hidden="true"></span>
</div>
