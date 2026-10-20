{{-- Which languages, and what the candidate can do in each (the app's
     LanguageFields). Read / Write / Speak are three ticks composed into one
     of the server's seven values; nothing ticked means none on record. --}}
<div>
  @include('site.ui.multi-add', ['label' => 'Languages you speak', 'model' => 'pf.languages', 'items' => "opt('languages')", 'placeholder' => '+ Add a language'])
  <div class="mt-md space-y-md">
    <template x-for="lang in pf.languages" :key="lang">
      <div class="rounded-card bg-surface-muted py-xs pl-md pr-xs">
        <div class="flex items-center">
          <span class="flex-1 text-bodysm font-semibold text-ink" x-text="lang"></span>
          <button type="button" @click="profLangRemove(lang)" :aria-label="'Remove ' + lang"
                  class="inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-muted hover:bg-hairline">
            <span x-html="ICONS.x" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
          </button>
        </div>
        <div class="flex flex-wrap gap-lg pb-sm">
          <template x-for="a in PROF_ABILITIES" :key="a">
            <label class="flex cursor-pointer items-center gap-xs text-bodysm text-ink">
              <input type="checkbox" :checked="profLangHas(lang, a)" @change="profLangToggle(lang, a)" class="h-[18px] w-[18px] rounded accent-[#EB0401]">
              <span x-text="a"></span>
            </label>
          </template>
        </div>
      </div>
    </template>
  </div>
  <p x-show="!pf.languages.length" class="mt-sm text-caption text-ink-muted">Employers filter on this wherever a role deals with patients or customers.</p>
</div>
