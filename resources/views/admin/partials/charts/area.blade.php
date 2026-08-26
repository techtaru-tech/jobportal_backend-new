{{--
  The activity trend — a filled area over a horizontal grid, matching the
  Recharts `<AreaChart>` the React panel draws: 260px tall, a red stroke over a
  0.4→0 vertical gradient, `hairline.divider` grid rules, no axis lines, and an
  active dot with a surface-coloured halo on hover.

  The SVG is built as a string in `areaSvg()` and injected with `x-html`, not
  assembled from `<template x-for>` elements. A `<template>` written inside an
  `<svg>` is parsed into the SVG namespace, where it is an ordinary unknown
  element with no `.content` fragment — so Alpine's `x-for` silently renders
  nothing there. Everything interactive stays in HTML siblings layered over the
  plot, which also keeps circles round: the plot itself uses
  `preserveAspectRatio="none"`, which would squash them into ellipses.
--}}
<div class="relative h-[260px] pl-[34px]" x-data="{ hover: null }">
  <div class="absolute inset-0 left-[34px]" x-html="areaSvg()"></div>

  {{-- Y-axis tick labels, laid over the plot rather than inside a stretched SVG. --}}
  <template x-for="tick in areaChart().yTicks" :key="tick.value">
    <span class="pointer-events-none absolute left-0 -translate-y-1/2 text-[11px] tabular-nums text-ink-muted"
          :style="`top:${tick.y}%`" x-text="tick.value"></span>
  </template>

  {{-- The hovered point's crosshair and dot, drawn as HTML so they stay
       geometrically true regardless of how the SVG is stretched. --}}
  <template x-if="hover !== null">
    <span class="pointer-events-none absolute top-0 bottom-0 w-px bg-hairline"
          :style="`left:calc(34px + ${areaChart().points[hover].x}% - ${areaChart().points[hover].x * 0.34}px)`"></span>
  </template>
  <template x-if="hover !== null">
    <span class="pointer-events-none absolute h-[8px] w-[8px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary ring-2 ring-canvas"
          :style="`left:calc(34px + ${areaChart().points[hover].x}% - ${areaChart().points[hover].x * 0.34}px);top:${areaChart().points[hover].y}%`"></span>
  </template>

  {{-- One hover column per point, so the tooltip tracks the pointer without
       hit-testing maths on every mousemove. --}}
  <div class="absolute inset-0 left-[34px] flex">
    <template x-for="(point, i) in areaChart().points" :key="i">
      <div class="h-full flex-1" @mouseenter="hover = i" @mouseleave="hover = null"></div>
    </template>
  </div>

  <template x-if="hover !== null">
    <div class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-[12px] border border-hairline bg-canvas px-[10px] py-[8px] shadow-raised"
         :style="`left:${Math.min(88, Math.max(14, areaChart().points[hover].x))}%;top:8px`">
      <p class="mb-[2px] text-[11.5px] text-ink-secondary" x-text="fmtDate(areaChart().points[hover].date + 'T00:00:00')"></p>
      <p class="text-[13px] font-semibold tabular-nums text-ink" x-text="areaChart().points[hover].count"></p>
    </div>
  </template>
</div>

{{-- X-axis labels, thinned so they never collide (Recharts' `minTickGap`). --}}
<div class="mt-sm flex justify-between pl-[34px] text-[11px] text-ink-muted">
  <template x-for="label in areaChart().xLabels" :key="label">
    <span x-text="label"></span>
  </template>
</div>
