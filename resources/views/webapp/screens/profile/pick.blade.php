{{-- One value from a server list — the app's OptionPicker: chips for a short
     list (≤ 8, the app's kChipThreshold), a field past that. A list is a
     shortlist, not a whitelist, unless 'custom' => false.
     @include('webapp.screens.profile.pick', ['label' => 'Highest qualification', 'model' => 'pf.edu.qualification', 'items' => "opt('qualifications')", 'required' => true, 'custom' => true, 'placeholder' => 'Select a qualification']) --}}
@php $custom = $custom ?? true; @endphp
<div>
  <template x-if="({{ $items }}).length > 0 && ({{ $items }}).length <= 8">
    <div>
      @include('webapp.ui.chips', ['label' => $label, 'model' => $model, 'items' => $items, 'required' => $required ?? false])
    </div>
  </template>
  <template x-if="!(({{ $items }}).length > 0 && ({{ $items }}).length <= 8)">
    <div>
      @if ($custom)
        @include('webapp.ui.combo', ['label' => $label, 'model' => $model, 'items' => $items, 'required' => $required ?? false, 'placeholder' => $placeholder ?? 'Select'])
      @else
        @include('webapp.ui.select', ['label' => $label, 'model' => $model, 'items' => $items, 'required' => $required ?? false, 'placeholder' => $placeholder ?? 'Select'])
      @endif
    </div>
  </template>
</div>
