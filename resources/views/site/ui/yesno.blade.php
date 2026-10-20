{{-- A Yes / No question bound to a boolean (null = unanswered).
     @include('site.ui.yesno', ['label' => 'Are you currently employed?', 'model' => 'form.currently_employed']) --}}
<div>
  <span class="wa-label">{{ $label }}@if (!empty($required))<span class="text-primary"> *</span>@endif</span>
  <div class="flex gap-sm">
    <button type="button" @click="{{ $model }} = true" :class="{{ $model }} === true && 'wa-chip-on'" class="wa-chip min-w-[84px] justify-center">Yes</button>
    <button type="button" @click="{{ $model }} = false" :class="{{ $model }} === false && 'wa-chip-on'" class="wa-chip min-w-[84px] justify-center">No</button>
  </div>
</div>
