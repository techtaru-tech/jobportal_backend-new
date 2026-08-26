{{--
  Supply against demand per city (or role).

  Two stacked rules rather than one split bar. The split version made a city
  with zero applications look like it had a short bar of *something*, when the
  honest reading is a full job bar above an empty one.
--}}
<template x-if="{{ $rows }}.length === 0">
  <p class="py-xxl text-center text-bodysm text-ink-muted">Not enough data yet.</p>
</template>

<template x-if="{{ $rows }}.length > 0">
  <ul class="flex-1 space-y-md">
    <template x-for="row in {{ $rows }}" :key="row.label">
      <li>
        <div class="mb-[6px] flex items-center justify-between gap-md text-bodysm">
          <span class="truncate font-semibold text-ink" x-text="row.label"></span>
          <span class="shrink-0 text-caption tabular-nums text-ink-muted"
                x-text="row.jobs + ' jobs · ' + row.applications + ' applications'"></span>
        </div>
        <div class="space-y-[3px]">
          <div class="h-[5px] overflow-hidden rounded-chip bg-surface-muted">
            <div class="h-full origin-left rounded-chip bg-primary animate-grow-x" :style="`width:${row.jobsPct}%`"></div>
          </div>
          <div class="h-[5px] overflow-hidden rounded-chip bg-surface-muted">
            <div class="h-full origin-left rounded-chip bg-info animate-grow-x" :style="`width:${row.applicationsPct}%`"></div>
          </div>
        </div>
      </li>
    </template>
  </ul>
</template>
