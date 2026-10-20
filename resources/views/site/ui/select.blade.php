{{-- A labelled native select over an Alpine list of strings.
     @include('site.ui.select', ['label' => 'City', 'model' => 'form.city', 'items' => "opt('cities')", 'placeholder' => 'Select city', 'required' => true, 'error' => 'errors.city', 'disabled' => '!form.state']) --}}
<label class="block">
  <span class="wa-label">{{ $label }}@if (!empty($required))<span class="text-primary"> *</span>@endif</span>
  <div class="relative">
    <select x-model="{{ $model }}" @isset($disabled) :disabled="{{ $disabled }}" @endisset
            class="wa-input cursor-pointer appearance-none pr-10 disabled:cursor-not-allowed disabled:opacity-60">
      <option value="">{{ $placeholder ?? 'Select' }}</option>
      <template x-for="item in ({{ $items }})" :key="item">
        <option :value="item" x-text="item" :selected="item === {{ $model }}"></option>
      </template>
    </select>
    <span aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">
      @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])
    </span>
  </div>
  @isset($error)<p x-show="{{ $error }}" x-text="{{ $error }}" class="wa-error"></p>@endisset
</label>
