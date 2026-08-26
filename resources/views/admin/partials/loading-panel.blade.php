{{-- Centred spinner for a whole panel — used only where there is no shape to mimic. --}}
<div class="flex flex-col items-center justify-center gap-md p-xxxl">
  @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-6 w-6 animate-spin text-primary'])
  <span class="text-bodysm text-ink-secondary">{{ $label ?? 'Loading…' }}</span>
</div>
