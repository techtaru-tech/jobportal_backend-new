{{-- Free text with suggestions — the app's "picker + free text".
     @include('site.ui.combo', ['label' => 'Designation', 'model' => 'form.designation', 'items' => "opt('designations')", 'placeholder' => 'e.g. Staff Nurse', 'required' => true, 'error' => 'errors.designation']) --}}
@php $listId = 'dl-'.\Illuminate\Support\Str::random(8); @endphp
<label class="block">
  <span class="wa-label">{{ $label }}@if (!empty($required))<span class="text-primary"> *</span>@endif</span>
  <input type="text" x-model="{{ $model }}" list="{{ $listId }}" placeholder="{{ $placeholder ?? 'Type or pick' }}" autocomplete="off" class="wa-input">
  <datalist id="{{ $listId }}">
    <template x-for="item in ({{ $items }})" :key="item"><option :value="item"></option></template>
  </datalist>
  @isset($error)<p x-show="{{ $error }}" x-text="{{ $error }}" class="wa-error"></p>@endisset
</label>
