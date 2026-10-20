{{-- A Yes / No over a nullable answer — tapping the picked one clears it back
     to "not answered", the app's tri-state YesNoField.
     @include('webapp.screens.profile.yn', ['label' => 'Immediate joiner?', 'key' => 'immediate_joiner', 'icon' => 'zap']) --}}
<div class="flex flex-wrap items-center justify-between gap-sm rounded-field bg-surface-muted px-md py-sm">
  <span class="flex items-center gap-sm text-bodysm font-medium text-ink">
    @include('admin.partials.icon', ['name' => $icon ?? 'info', 'class' => 'h-[18px] w-[18px] text-ink-muted'])
    {{ $label }}
  </span>
  <div class="flex gap-xs">
    <button type="button" @click="{{ $click ?? "profTri('{$key}', true)" }}" :class="{{ $value ?? "pf.{$key}" }} === true && 'wa-chip-on'" class="wa-chip min-w-[72px] justify-center bg-surface">Yes</button>
    <button type="button" @click="{{ $clickNo ?? "profTri('{$key}', false)" }}" :class="{{ $value ?? "pf.{$key}" }} === false && 'wa-chip-on'" class="wa-chip min-w-[72px] justify-center bg-surface">No</button>
  </div>
</div>
