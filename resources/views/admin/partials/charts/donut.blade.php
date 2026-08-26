{{--
  Application status as a donut with the total in its hole, plus a legend that
  restates each wedge as a StatusDot and a percentage.

  Matches the Recharts `<Pie innerRadius={52} outerRadius={80} paddingAngle={3}>`
  the React panel draws, at the same 168px box. Wedges are `stroke-dasharray`
  arcs on one circle rather than paths, which is what makes the padding gap
  between them trivial.

  Built as a string in `donutSvg()` for the same reason as the area chart: a
  `<template x-for>` inside an `<svg>` lands in the SVG namespace, has no
  `.content` fragment, and renders nothing.

  Colours are semantic and shared with `statusDot()` — the donut and the table
  beside it must not disagree about what "shortlisted" looks like.
--}}
<template x-if="statusPie().total === 0">
  <p class="py-xxl text-center text-bodysm text-ink-muted">No applications yet.</p>
</template>

<template x-if="statusPie().total > 0">
  <div class="flex flex-col items-center gap-lg sm:flex-row">
    <div class="relative h-[168px] w-[168px] shrink-0">
      <div class="h-full w-full" x-html="donutSvg()"></div>

      {{-- The donut's hole is otherwise dead space, and the total is the one
           number every wedge is a fraction of. --}}
      <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
        <span class="text-h2 tabular-nums text-ink" x-text="fmtNumber(statusPie().total)"></span>
        <span class="text-caption text-ink-muted">total</span>
      </div>
    </div>

    <ul class="w-full space-y-[2px]">
      <template x-for="slice in statusPie().slices" :key="slice.status">
        <li class="flex items-center justify-between gap-md rounded-field px-sm py-[6px] transition-colors hover:bg-canvas-alt">
          <span x-html="statusDot(slice.status, 'application')"></span>
          <span class="text-bodysm font-semibold tabular-nums text-ink">
            <span x-text="fmtNumber(slice.count)"></span>
            <span class="font-normal text-ink-muted" x-text="slice.percent + '%'"></span>
          </span>
        </li>
      </template>
    </ul>
  </div>
</template>
