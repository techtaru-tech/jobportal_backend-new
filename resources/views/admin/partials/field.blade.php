{{--
  A labelled key/value row — the workhorse of every detail page here.
  An empty value renders an em-dash rather than collapsing, so a missing field
  is visibly missing instead of silently absent.
--}}
<div class="min-w-0">
  <span class="block text-kicker text-ink-secondary">{{ strtoupper($label) }}</span>
  <div class="mt-[3px] text-bodysm text-ink break-words">
    <template x-if="{{ $value }} === null || {{ $value }} === undefined || {{ $value }} === ''">
      <span class="text-ink-muted">—</span>
    </template>
    <template x-if="!({{ $value }} === null || {{ $value }} === undefined || {{ $value }} === '')">
      <span x-text="{{ $value }}"></span>
    </template>
  </div>
</div>
