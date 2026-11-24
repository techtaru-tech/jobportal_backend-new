{{-- A labelled text input.
     @include('site.ui.input', ['label' => 'Name', 'model' => 'form.name', 'type' => 'text', 'placeholder' => '', 'required' => true,
              'prefix' => '+91', 'attrs' => 'maxlength=10 inputmode=numeric', 'hint' => '', 'error' => 'errors.name'])
     `type => 'number'` binds with x-model.number. 'icon' => 'user' puts that icon inside the field. --}}
<label class="block">
  <span class="wa-label">{{ $label }}@include('site.ui.mark', ['required' => $required ?? false, 'optional' => $optional ?? true])</span>
  <div class="flex items-center gap-sm">
    @isset($prefix)
      <span class="inline-flex h-[50px] shrink-0 items-center rounded-field bg-surface-muted px-md text-input text-ink-secondary">{{ $prefix }}</span>
    @endisset
    @if (!empty($icon))
      {{-- A leading icon inside the field, as the app's inputs have. --}}
      <div class="relative min-w-0 flex-1">
        <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => $icon, 'class' => 'h-[18px] w-[18px]'])</span>
        <input type="{{ $type ?? 'text' }}" x-model{{ ($type ?? '') === 'number' ? '.number' : '' }}="{{ $model }}" placeholder="{{ $placeholder ?? '' }}" {!! $attrs ?? '' !!} class="wa-input pl-[44px]">
      </div>
    @else
    <input type="{{ $type ?? 'text' }}" x-model{{ ($type ?? '') === 'number' ? '.number' : '' }}="{{ $model }}" placeholder="{{ $placeholder ?? '' }}" {!! $attrs ?? '' !!} class="wa-input">
    @endif
  </div>
  @if (!empty($hint))<p class="mt-xs text-caption text-ink-muted">{{ $hint }}</p>@endif
  @isset($error)<p x-show="{{ $error }}" x-text="{{ $error }}" class="wa-error"></p>@endisset
</label>
