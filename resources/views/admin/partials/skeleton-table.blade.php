{{--
  A table-shaped skeleton for a list whose first page is still loading.

  Matches the table's own row rhythm so the real rows land where the
  placeholders were, instead of the whole page jumping when data arrives.
  Widths are uneven and deterministic per column — every bar the same length
  reads as a loading *graphic*, while a ragged right edge reads as text.
--}}
@php $widths = ['58%', '38%', '72%', '46%', '64%', '30%']; $cols = $cols ?? 5; @endphp
<div aria-busy="true" class="overflow-hidden">
  <div class="flex items-center gap-lg border-b border-hairline px-lg py-md">
    @for ($c = 0; $c < $cols; $c++)
      <div class="shimmer-fill animate-shimmer h-[9px] flex-1 rounded-[6px]" aria-hidden="true"></div>
    @endfor
  </div>
  @for ($r = 0; $r < 8; $r++)
    <div class="flex items-center gap-lg border-b border-hairline-divider px-lg py-lg last:border-0">
      @for ($c = 0; $c < $cols; $c++)
        <div class="flex-1">
          <div class="shimmer-fill animate-shimmer h-[13px] rounded-[6px]" aria-hidden="true"
               style="width:{{ $widths[($r + $c) % count($widths)] }}"></div>
        </div>
      @endfor
    </div>
  @endfor
</div>
