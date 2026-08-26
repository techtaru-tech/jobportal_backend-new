{{--
  Profile-strength bands as vertical bars, matching the Recharts `<BarChart>`:
  220px tall, 5px top corner radius, 52px max bar width, and a red that gets
  stronger the fuller the profile — the x-axis restated as colour, so the shape
  reads before the labels do.

  Laid out with flex rather than an SVG: the bars are plain rectangles, and
  flex gives the max-width and centring for free.
--}}
<div class="relative h-[220px]" x-data="{ hover: null }">
  {{-- Grid rules behind the bars, at the same four steps the axis labels use. --}}
  <template x-for="tick in histogram().yTicks" :key="tick.value">
    <div class="pointer-events-none absolute inset-x-0 border-t border-hairline-divider"
         :style="`top:${tick.y}%`">
      <span class="absolute -top-[7px] left-0 text-[11px] text-ink-muted tabular-nums" x-text="tick.value"></span>
    </div>
  </template>

  <div class="absolute inset-0 flex items-end gap-sm pl-[34px]">
    <template x-for="(bar, i) in histogram().bars" :key="bar.bucket">
      <div class="flex h-full flex-1 items-end justify-center" @mouseenter="hover = i" @mouseleave="hover = null">
        <div class="w-full max-w-[52px] rounded-t-[5px] bg-primary origin-bottom transition-[opacity] duration-micro"
             :style="`height:${bar.height}%;opacity:${bar.opacity}`"
             style="animation: grow-y 520ms cubic-bezier(0.22,1,0.36,1)"></div>
      </div>
    </template>
  </div>

  <template x-if="hover !== null">
    <div class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-[12px] border border-hairline bg-canvas px-[10px] py-[8px] shadow-raised"
         :style="`left:${histogram().bars[hover].center}%;top:4px`">
      <p class="text-[11.5px] text-ink-secondary mb-[2px]" x-text="histogram().bars[hover].bucket"></p>
      <p class="text-[13px] font-semibold text-ink tabular-nums" x-text="histogram().bars[hover].count"></p>
    </div>
  </template>
</div>

<div class="mt-sm flex gap-sm pl-[34px] text-[11px] text-ink-muted">
  <template x-for="bar in histogram().bars" :key="bar.bucket">
    <span class="flex-1 text-center" x-text="bar.bucket"></span>
  </template>
</div>
