{{-- Free text with suggestions — the app's "picker + free text" (OptionPicker /
     MDropdown with a custom entry). Focus opens a scrollable list; typing
     narrows it. The "Add your own" row is the LAST row of that list, so the
     list is scrolled first — and with something typed that is not on it, the
     row reads `Add "…"`.
     @include('site.ui.combo', ['label' => 'Designation', 'model' => 'form.designation', 'items' => "opt('designations')", 'placeholder' => 'e.g. Staff Nurse', 'required' => true, 'error' => 'errors.designation', 'attrs' => 'maxlength=40'])
     'optional' => false drops the "(Optional)" marker (see site.ui.mark). --}}
@php $listExpr = "Inthes.comboList(({$items}), {$model}, typing)"; @endphp
<div class="relative"
     x-data="{ open: false, typing: false, hi: -1 }"
     @click.outside="open = false; typing = false"
     @keydown.escape="if (open) { $event.stopPropagation(); open = false; typing = false }">
  <label class="block">
    <span class="wa-label">{{ $label }}@include('site.ui.mark', ['required' => $required ?? false, 'optional' => $optional ?? true])</span>
    <span class="relative block">
      <input type="text" x-model="{{ $model }}" placeholder="{{ $placeholder ?? 'Type or pick' }}" autocomplete="off" {!! $attrs ?? '' !!}
             role="combobox" aria-autocomplete="list" :aria-expanded="open"
             @focus="open = true; typing = false; hi = -1"
             @click="open = true"
             @input="open = true; typing = true; hi = -1"
             @keydown.arrow-down.prevent="open = true; hi = Math.min(hi + 1, {{ $listExpr }}.length)"
             @keydown.arrow-up.prevent="hi = Math.max(hi - 1, -1)"
             @keydown.enter="if (open) { $event.preventDefault(); const l = {{ $listExpr }}; {{ $model }} = hi >= 0 && hi < l.length ? l[hi] : String({{ $model }} || '').trim(); open = false; typing = false; $dispatch('input') }"
             @keydown.tab="open = false; typing = false"
             class="wa-input pr-10">
      <button type="button" tabindex="-1" aria-label="Show options"
              @click.prevent="open = !open; typing = false; if (open) $el.parentElement.querySelector('input').focus()"
              class="absolute right-xs top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center text-ink-muted">
        @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])
      </button>
    </span>
  </label>
  <div x-show="open" x-cloak
       class="thin-scrollbar absolute left-0 right-0 z-40 mt-xs max-h-[264px] overflow-y-auto rounded-field border-hair border-hairline bg-surface py-xs shadow-raised"
       role="listbox">
    <template x-for="(item, i) in {{ $listExpr }}" :key="item">
      <button type="button" role="option" :aria-selected="item === {{ $model }}"
              @mousedown.prevent @click="{{ $model }} = item; open = false; typing = false; $dispatch('input')"
              @mouseenter="hi = i"
              :class="[hi === i ? 'bg-surface-muted' : '', item === {{ $model }} ? 'font-semibold text-primary-dark' : 'text-ink']"
              class="flex w-full items-center gap-sm px-md py-[9px] text-left text-bodysm">
        <span class="min-w-0 flex-1 truncate" x-text="item"></span>
        <span x-show="item === {{ $model }}" x-html="ICONS.check" class="shrink-0 text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
      </button>
    </template>
    <p x-show="!{{ $listExpr }}.length" class="px-md py-[9px] text-caption text-ink-muted">No matches</p>
    {{-- "Add your own" — the last row, after everything on the list. --}}
    <button type="button" @mousedown.prevent
            @click="if (Inthes.comboCanAdd(({{ $items }}), {{ $model }})) { {{ $model }} = String({{ $model }}).trim(); open = false; typing = false; $dispatch('input') } else { const inp = $root.querySelector('input'); inp.focus(); inp.select(); typing = true }"
            @mouseenter="hi = {{ $listExpr }}.length"
            :class="hi === {{ $listExpr }}.length && 'bg-surface-muted'"
            class="flex w-full items-center gap-sm border-t border-hairline-divider px-md py-[10px] text-left text-bodysm font-semibold text-primary">
      <span x-html="ICONS.plus" class="shrink-0 [&>svg]:h-4 [&>svg]:w-4"></span>
      <span class="min-w-0 flex-1 truncate" x-text="Inthes.comboCanAdd(({{ $items }}), {{ $model }}) ? 'Add &quot;' + String({{ $model }}).trim() + '&quot;' : 'Don\'t see yours? Add your own'"></span>
    </button>
  </div>
  @if (!empty($hint))<p class="mt-xs text-caption text-ink-muted">{{ $hint }}</p>@endif
  @isset($error)<p x-show="{{ $error }}" x-text="{{ $error }}" class="wa-error"></p>@endisset
</div>
