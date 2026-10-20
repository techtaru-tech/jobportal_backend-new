{{-- Multi-select chips over suggestions, plus "Add your own".
     @include('webapp.ui.multi-add', ['label' => 'Skills', 'model' => 'form.skills', 'items' => "opt('professional_skills')", 'max' => 3, 'placeholder' => 'Add your own', 'hint' => '']) --}}
@php
  $maxArg = !empty($max) ? ', '.$max : '';
  $addExpr = "if (draft.trim() && !({$model} || []).includes(draft.trim())) { toggleIn({$model}, draft.trim(){$maxArg}) } draft = ''";
@endphp
<div x-data="{ draft: '' }">
  <span class="wa-label">{{ $label }}@if (!empty($required))<span class="text-primary"> *</span>@endif</span>
  @if (!empty($hint))<p class="-mt-[2px] mb-sm text-caption text-ink-muted">{{ $hint }}</p>@endif
  <div class="flex flex-wrap gap-xs">
    <template x-for="item in [...new Set([...({{ $items }}), ...({{ $model }} || [])])]" :key="item">
      <button type="button" @click="toggleIn({{ $model }}, item{{ $maxArg }})"
              :class="({{ $model }} || []).includes(item) && 'wa-chip-on'" class="wa-chip">
        <span x-show="({{ $model }} || []).includes(item)" x-html="ICONS.check" class="text-primary [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
        <span x-text="item"></span>
      </button>
    </template>
  </div>
  <div class="mt-sm flex gap-sm">
    <input type="text" x-model="draft" placeholder="{{ $placeholder ?? 'Add your own' }}" class="wa-input h-10" @keydown.enter.prevent="{{ $addExpr }}">
    <button type="button" class="wa-btn-ghost-sm shrink-0" @click="{{ $addExpr }}">Add</button>
  </div>
</div>
