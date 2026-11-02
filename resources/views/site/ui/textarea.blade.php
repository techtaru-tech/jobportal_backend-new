{{-- @include('site.ui.textarea', ['label' => 'About', 'model' => 'form.about', 'rows' => 4, 'max' => 300, 'placeholder' => '', 'error' => 'errors.about']) --}}
<label class="block">
  <span class="wa-label">{{ $label }}@include('site.ui.mark', ['required' => $required ?? false, 'optional' => $optional ?? true])</span>
  <textarea x-model="{{ $model }}" rows="{{ $rows ?? 4 }}" @isset($max) maxlength="{{ $max }}" @endisset placeholder="{{ $placeholder ?? '' }}" class="wa-textarea"></textarea>
  @isset($max)<p class="mt-xs text-right text-caption text-ink-muted"><span x-text="({{ $model }} || '').length"></span>/{{ $max }}</p>@endisset
  @isset($error)<p x-show="{{ $error }}" x-text="{{ $error }}" class="wa-error"></p>@endisset
</label>
