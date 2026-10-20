{{-- One label / value pair in a card's read view. 'value' is a JS expression;
     blank shows as "Not added".
     @include('site.profile.field', ['label' => 'Email', 'value' => 'me?.email']) --}}
<div class="min-w-0 {{ $class ?? '' }}">
  <dt class="text-caption text-ink-muted">{{ $label }}</dt>
  <dd class="mt-[2px] break-words text-bodysm" :class="({{ $value }}) ? 'text-ink' : 'text-ink-muted'" x-text="({{ $value }}) || 'Not added'"></dd>
</div>
