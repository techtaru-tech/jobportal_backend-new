{{-- "About you" — the same label, hint and 300-character cap wherever it is
     written, with "Suggest for me" drafting from the saved profile. --}}
<div>
  @include('site.ui.textarea', ['label' => 'About you', 'model' => 'pf.about', 'rows' => 5, 'max' => 300, 'placeholder' => 'What you do, what you are good at, what you are looking for…'])
  <div class="-mt-lg flex justify-start">
    <button type="button" @click="profSuggestAbout()" class="inline-flex items-center gap-xs rounded-button px-sm py-xs text-bodysm font-semibold text-primary hover:bg-primary-light">
      <span x-html="ICONS.sparkles" class="[&>svg]:h-4 [&>svg]:w-4"></span> Suggest for me
    </button>
  </div>
</div>
