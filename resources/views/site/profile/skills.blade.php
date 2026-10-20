{{-- Professional skills and up to three hobbies (the app's
     SkillsInterestsFields) — pick from the list, or add your own. --}}
<div class="space-y-xl">
  <div x-data="{ draft: '' }">
    <span class="wa-label">Professional skills</span>
    <div class="flex flex-wrap gap-xs">
      <template x-for="s in profChipList(opt('professional_skills'), pf.skills)" :key="s">
        <button type="button" @click="profToggleSkill(s)" :class="pf.skills.some((x) => x.toLowerCase() === s.toLowerCase()) && 'wa-chip-on'" class="wa-chip">
          <span x-show="pf.skills.some((x) => x.toLowerCase() === s.toLowerCase())" x-html="ICONS.check" class="text-primary [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
          <span x-text="s"></span>
        </button>
      </template>
    </div>
    <div class="mt-sm flex gap-sm">
      <input type="text" x-model="draft" placeholder="Add a skill" maxlength="60" class="wa-input h-10" @keydown.enter.prevent="profAddOwn('skill', draft); draft = ''">
      <button type="button" class="wa-btn-ghost-sm shrink-0" @click="profAddOwn('skill', draft); draft = ''">Add your own</button>
    </div>
  </div>

  <div x-data="{ draft: '' }">
    <span class="wa-label">Hobbies &amp; interests <span class="ml-xs text-caption text-ink-muted" x-text="pf.hobbies.length + '/' + PROF_MAX_HOBBIES"></span></span>
    <div class="flex flex-wrap gap-xs">
      <template x-for="h in profChipList(opt('hobby_interests'), pf.hobbies)" :key="h">
        <button type="button" @click="profToggleHobby(h)" :class="pf.hobbies.includes(h) && 'wa-chip-on'" class="wa-chip">
          <span x-show="pf.hobbies.includes(h)" x-html="ICONS.check" class="text-primary [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
          <span x-text="h"></span>
        </button>
      </template>
    </div>
    <div class="mt-sm flex gap-sm">
      <input type="text" x-model="draft" placeholder="Add a hobby" maxlength="60" class="wa-input h-10" @keydown.enter.prevent="profAddOwn('hobby', draft); draft = ''">
      <button type="button" class="wa-btn-ghost-sm shrink-0" @click="profAddOwn('hobby', draft); draft = ''">Add your own</button>
    </div>
    <p class="mt-sm text-caption text-ink-muted">Pick 3 you genuinely enjoy — recruiters skim past generic lists.</p>
  </div>
</div>
