{{-- A labelled text input.
     @include('webapp.ui.input', ['label' => 'Name', 'model' => 'form.name', 'type' => 'text', 'placeholder' => '', 'required' => true,
              'prefix' => '+91', 'attrs' => 'maxlength=10 inputmode=numeric', 'hint' => '', 'error' => 'errors.name'])
     `type => 'number'` binds with x-model.number. --}}
<label class="block">
  <span class="wa-label">{{ $label }}@if (!empty($required))<span class="text-primary"> *</span>@endif</span>
  <div class="flex items-center gap-sm">
    @isset($prefix)
      <span class="inline-flex h-[50px] shrink-0 items-center rounded-field bg-surface-muted px-md text-input text-ink-secondary">{{ $prefix }}</span>
    @endisset
    <input type="{{ $type ?? 'text' }}" x-model{{ ($type ?? '') === 'number' ? '.number' : '' }}="{{ $model }}" placeholder="{{ $placeholder ?? '' }}" {!! $attrs ?? '' !!} class="wa-input">
  </div>
  @if (!empty($hint))<p class="mt-xs text-caption text-ink-muted">{{ $hint }}</p>@endif
  @isset($error)<p x-show="{{ $error }}" x-text="{{ $error }}" class="wa-error"></p>@endisset
</label>
