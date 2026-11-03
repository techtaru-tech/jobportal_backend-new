{{-- "About you" — the same label, hint and 300-character cap wherever it is
     written, with "Write with AI" (AI Resume Builder) drafting from the saved
     profile — and the plain "Suggest for me" draft when AI cannot answer. --}}
<div>
  @include('site.ui.textarea', ['label' => 'About you', 'model' => 'pf.about', 'rows' => 5, 'max' => 300, 'placeholder' => 'What you do, what you are good at, what you are looking for…'])
  <div class="-mt-lg flex justify-start">
    <button type="button" @click="profWriteAbout()" :disabled="!!aiWriting" class="inline-flex items-center gap-xs rounded-button px-sm py-xs text-bodysm font-semibold text-primary hover:bg-primary-light disabled:opacity-60">
      <span x-html="ICONS.sparkles" class="[&>svg]:h-4 [&>svg]:w-4" :class="aiWriting === 'about' && 'animate-pulse'"></span>
      <span x-text="aiWriting === 'about' ? 'Writing…' : 'Write with AI'"></span>
    </button>
  </div>
</div>
