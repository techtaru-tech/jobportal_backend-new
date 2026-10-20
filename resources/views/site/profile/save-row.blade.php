{{-- Save / Cancel under an inline card form.
     @include('site.profile.save-row', ['save' => 'profSaveLanguages()', 'label' => 'Save', 'disabled' => '']) --}}
<div class="mt-xl flex flex-col-reverse gap-sm border-t border-hairline-divider pt-lg sm:flex-row sm:justify-end">
  <button type="button" @click="cancelEdit()" :disabled="profBusy" class="wa-btn-quiet h-[46px] border-hair border-hairline sm:h-10">Cancel</button>
  <button type="button" @click="{{ $save }}" :disabled="profBusy{{ !empty($disabled) ? ' || '.$disabled : '' }}" class="wa-btn-sm h-[46px] min-w-[120px] sm:h-10">
    <span x-show="profBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span>
    @isset($labelExpr)<span x-text="{{ $labelExpr }}"></span>@else{{ $label ?? 'Save' }}@endisset
  </button>
</div>
