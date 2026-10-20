{{-- A list value in a card's read view, as tags.
     @include('site.profile.chips-field', ['label' => 'Preferred cities', 'value' => 'me?.location']) --}}
<div class="min-w-0 {{ $class ?? '' }}">
  <dt class="text-caption text-ink-muted">{{ $label }}</dt>
  <dd class="mt-xs flex flex-wrap gap-xs">
    <template x-for="v in ({{ $value }} || [])" :key="v"><span class="wa-tag" x-text="v"></span></template>
    <span x-show="!({{ $value }} || []).length" class="text-bodysm text-ink-muted">Not added</span>
  </dd>
</div>
