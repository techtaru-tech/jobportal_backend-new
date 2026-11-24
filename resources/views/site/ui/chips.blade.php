{{-- Chip picker over a list of strings.
     Single: @include('site.ui.chips', ['label' => 'Gender', 'model' => 'form.gender', 'items' => "opt('genders')"])
     Multi:  @include('site.ui.chips', ['label' => 'Shifts', 'model' => 'form.shifts', 'items' => "opt('shifts')", 'multi' => true, 'max' => 3])
     'labels' => JS expression for an object mapping value => display label.
     'required' => true keeps a single choice from being toggled off.
     'iconKind' => 'shift' gives every chip the app's icon for it (Inthes.chipIcon). --}}
@php
  $multi = !empty($multi);
  $lab = $labels ?? null;
  $maxArg = isset($max) ? ', '.$max : '';
  $offValue = !empty($required) ? 'item' : "''";
  $onExpr = $multi ? "({$model} || []).includes(item)" : "{$model} === item";
@endphp
<div>
  @if (!empty($label))
    <span class="wa-label">{{ $label }}@include('site.ui.mark', ['required' => $required ?? false, 'optional' => $optional ?? true])</span>
  @endif
  @if (!empty($hint))<p class="-mt-[2px] mb-sm text-caption text-ink-muted">{{ $hint }}</p>@endif
  <div class="flex flex-wrap gap-xs">
    <template x-for="item in ({{ $items }})" :key="item">
      <button type="button"
              @if ($multi)
                @click="toggleIn({{ $model }}, item{{ $maxArg }})"
              @else
                @click="{{ $model }} = ({{ $model }} === item ? {{ $offValue }} : item)"
              @endif
              :class="{{ $onExpr }} && 'wa-chip-on'"
              class="wa-chip">
        @if (!empty($iconKind))
          <span x-html="ICONS[Inthes.chipIcon('{{ $iconKind }}', item)]" :class="{{ $onExpr }} ? 'text-primary' : 'text-ink-muted'" class="[&>svg]:h-4 [&>svg]:w-4"></span>
        @else
          <span x-show="{{ $onExpr }}" x-html="ICONS.check" class="text-primary [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
        @endif
        <span x-text="{{ $lab ? "({$lab})[item] || item" : 'item' }}"></span>
      </button>
    </template>
  </div>
  @isset($error)<p x-show="{{ $error }}" x-text="{{ $error }}" class="wa-error"></p>@endisset
</div>
