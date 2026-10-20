{{-- Job seeker profile: the Profile tab, the section editors and Resume.
     The fields live in screens/profile/*.blade.php and are shared with
     Create profile (onboarding.blade.php). --}}

{{-- ── Profile tab ─────────────────────────────────────────────────────── --}}
<template x-if="is('profile')">
  <div class="wa-page">
    <div class="grid gap-xl lg:grid-cols-[380px_minmax(0,1fr)] lg:items-start">

      <div class="space-y-lg lg:sticky lg:top-[88px]">
        {{-- Header: who, and how strong the profile is (the server's number). --}}
        <section class="rounded-card bg-surface-dark p-xl text-ink-onPrimary shadow-card">
          <div class="flex items-center gap-lg">
            <label class="group relative shrink-0 cursor-pointer" title="Change photo">
              <span class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-white/10 text-h3 font-semibold ring-2 ring-white/20">
                <template x-if="me?.photo_url"><img :src="me.photo_url" alt="" class="h-full w-full object-cover"></template>
                <template x-if="!me?.photo_url && profInitials(me?.name)"><span x-text="profInitials(me?.name)"></span></template>
                <template x-if="!me?.photo_url && !profInitials(me?.name)"><span x-html="ICONS.user" class="[&>svg]:h-7 [&>svg]:w-7"></span></template>
              </span>
              <span class="absolute -bottom-[2px] -right-[2px] flex h-6 w-6 items-center justify-center rounded-full bg-primary text-ink-onPrimary ring-2 ring-surface-dark">
                <span x-html="profPhotoBusy ? ICONS.loader : ICONS.camera" :class="profPhotoBusy && 'animate-spin'" class="[&>svg]:h-3 [&>svg]:w-3"></span>
              </span>
              <input type="file" accept="image/jpeg,image/png" class="sr-only" @change="profPickPhoto($event.target)" aria-label="Upload a profile photo">
            </label>
            <div class="min-w-0 flex-1">
              <h1 class="truncate text-h2" x-text="me?.name || 'Your profile'"></h1>
              <p class="text-caption text-white/65" x-text="profSubLine"></p>
              <p x-show="me?.phone" class="mt-[2px] flex items-center gap-xs text-caption text-white/65">
                <span x-text="'+91 ' + (me?.phone || '')"></span>
                <span x-html="ICONS.badgeCheck" class="text-success [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
                <span class="text-success">Verified</span>
              </p>
            </div>
          </div>

          <div class="mt-xl flex items-baseline">
            <span class="flex-1 text-kicker uppercase text-white/60">Profile strength</span>
            <span class="text-[30px] font-bold leading-none" x-text="profStrength + '%'"></span>
          </div>
          <div class="mt-sm h-2 overflow-hidden rounded-full bg-white/20">
            <div class="h-full rounded-full bg-primary transition-[width] duration-700 ease-out" :style="'width:' + profStrength + '%'"></div>
          </div>
          <div class="mt-sm flex items-center justify-between gap-md">
            <span class="text-bodysm font-semibold" x-text="profStrengthLabel"></span>
            <span class="text-caption text-white/75" x-text="profHiringLabel"></span>
          </div>
          <template x-if="me?.photo">
            <button type="button" @click="profDeletePhoto()" :disabled="profPhotoBusy" class="mt-md text-caption text-white/60 underline-offset-2 hover:text-white hover:underline">Remove photo</button>
          </template>
        </section>

        {{-- One tap from here: look at it, or keep it. The paid copy sits under
             the free one, never in front of it. --}}
        <template x-if="me?.resume">
          <div>
            <div class="grid grid-cols-2 gap-sm">
              <a :href="me.resume_url" target="_blank" rel="noopener" class="wa-btn-ghost-sm">
                <span x-html="ICONS.eye" class="[&>svg]:h-4 [&>svg]:w-4"></span> View Resume
              </a>
              <a :href="me.resume_url" :download="me.resume" target="_blank" rel="noopener" class="wa-btn-sm">
                <span x-html="ICONS.download" class="[&>svg]:h-4 [&>svg]:w-4"></span> Download
              </a>
            </div>
            <template x-if="profCleanOffer">
              <button type="button" @click="profBuyClean()" :disabled="profBuying"
                      class="mt-sm flex w-full items-center gap-sm rounded-field bg-surface-muted px-md py-sm text-left hover:bg-hairline">
                <span x-html="ICONS.sparkles" class="text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
                <span class="flex-1 text-bodysm text-ink">Want it without the INTHES mark?</span>
                <span x-show="!profBuying" class="text-bodysm font-bold text-primary" x-text="profCleanOffer.price_label"></span>
                <span x-show="profBuying" x-html="ICONS.loader" class="animate-spin text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
              </button>
            </template>
          </div>
        </template>

        <template x-if="profBoosts.length">
          <div>
            <p class="wa-section-title mb-sm">Complete these to go further</p>
            <div class="space-y-sm">
              <template x-for="b in profBoosts" :key="b.label">
                <a :href="'#' + b.path" class="flex items-center justify-between gap-md rounded-card border-hair border-dashed border-hairline-strong bg-surface px-md py-md hover:border-primary">
                  <span class="text-bodysm font-semibold text-ink" x-text="b.label"></span>
                  <span class="rounded-field bg-primary-light px-sm py-[3px] text-tag font-bold text-primary-dark" x-text="b.gain"></span>
                </a>
              </template>
            </div>
          </div>
        </template>
      </div>

      <div>
        <p class="wa-section-title mb-xs">Your details</p>
        <div class="rounded-card border-hair border-hairline bg-surface px-lg">
          <a href="#/subscription" class="flex items-center gap-sm border-b border-hairline-divider py-[14px]">
            <span x-html="profPlanPaid ? ICONS.checkCircle : ICONS.crown" :class="profPlanPaid ? 'text-success' : 'text-ink-muted'" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            <span class="flex-1 text-bodysm font-semibold text-ink">My Plan</span>
            <span class="text-caption" :class="profPlanPaid ? 'text-success' : 'text-ink-muted'" x-text="profPlanName"></span>
            <span x-html="ICONS.chevronRight" class="text-ink-muted [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
          </a>
          <template x-for="s in profSections" :key="s.label">
            <a :href="'#' + s.path" class="flex items-center gap-sm border-b border-hairline-divider py-[14px] last:border-b-0">
              <span x-show="s.complete" x-html="ICONS.checkCircle" class="text-success [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
              <span x-show="!s.complete" class="mx-[1.5px] h-[15px] w-[15px] shrink-0 rounded-full border-[1.5px] border-ink-muted"></span>
              <span class="flex-1 text-bodysm font-semibold text-ink" x-text="s.label"></span>
              <span class="text-caption" :class="s.complete ? 'text-success' : 'text-ink-muted'" x-text="s.status"></span>
              <span x-html="ICONS.chevronRight" class="text-ink-muted [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            </a>
          </template>
        </div>

        <p class="wa-section-title mb-xs mt-xl">Support &amp; Legal</p>
        <div class="rounded-card border-hair border-hairline bg-surface px-lg">
          @foreach ([['About Us', 'about'], ['Help & Support', 'faq'], ['Contact Us', 'contact'], ['Terms & Conditions', 'terms'], ['Privacy Policy', 'privacy']] as [$label, $slug])
            <a href="#/page/{{ $slug }}" class="flex items-center gap-sm border-b border-hairline-divider py-[14px] last:border-b-0">
              <span class="flex-1 text-bodysm font-semibold text-ink">{{ $label }}</span>
              <span x-html="ICONS.chevronRight" class="text-ink-muted [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            </a>
          @endforeach
        </div>

        <div class="mt-xl grid gap-sm sm:grid-cols-2">
          <button type="button" @click="switchMode()" class="wa-btn-ghost">
            <span x-html="ICONS.building" class="[&>svg]:h-5 [&>svg]:w-5"></span> Switch to hiring
          </button>
          <a :href="playStoreUrl" target="_blank" rel="noopener" class="wa-btn-ghost">
            <span x-html="ICONS.smartphone" class="[&>svg]:h-5 [&>svg]:w-5"></span> Get the Android app
          </a>
        </div>
        <button type="button" @click="confirmSignOut()" class="wa-btn-quiet mt-md h-[50px] w-full border-hair border-hairline">
          <span x-html="ICONS.logOut" class="[&>svg]:h-5 [&>svg]:w-5"></span> Sign Out
        </button>
      </div>
    </div>
  </div>
</template>

{{-- ── Profile ▸ section editors ───────────────────────────────────────── --}}
<template x-if="is('profileEdit')">
  <div class="wa-narrow">
    @include('webapp.ui.head', ['titleExpr' => 'profEditorTitle(profSection)', 'back' => '/profile'])

    <template x-if="profSection === 'personal'">
      <div>
        @include('webapp.screens.profile.photo')
        <div class="mt-xl">@include('webapp.screens.profile.personal')</div>
        <button type="button" @click="profSavePersonal()" :disabled="profBusy" class="wa-btn mt-xl w-full">
          <span x-show="profBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> Save
        </button>
      </div>
    </template>

    <template x-if="profSection === 'education'">
      <div>
        <p class="-mt-md mb-lg text-caption text-ink-secondary">Most postings filter on qualification. Where you studied goes on your profile too.</p>
        <template x-if="!(me?.educations || []).length">
          @include('webapp.ui.empty', ['icon' => 'graduation', 'title' => 'No education added yet', 'text' => 'Add your qualifications so hospitals know what you studied.'])
        </template>
        <div class="space-y-md">
          <template x-for="e in profEducations" :key="e.id">
            <div class="wa-card flex items-start gap-sm !p-lg">
              <div class="min-w-0 flex-1">
                <p class="text-h5 text-ink" x-text="e.qualification"></p>
                <p x-show="e.specialization" class="text-bodysm text-ink-secondary" x-text="e.specialization"></p>
                <p x-show="e.institute" class="text-caption text-ink-muted" x-text="e.institute"></p>
                <p class="mt-[2px] text-caption text-ink-secondary" x-text="[e.year, e.course_type, e.percentage != null ? Number(e.percentage) + '%' : ''].filter(Boolean).join(' · ')"></p>
              </div>
              <button type="button" @click="profEduOpenForm(e)" aria-label="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted"><span x-html="ICONS.pencil" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span></button>
              <button type="button" @click="profEduRemove(e)" aria-label="Remove" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-danger hover:bg-danger-bg"><span x-html="ICONS.trash" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span></button>
            </div>
          </template>
        </div>
        <button type="button" @click="profEduOpenForm(null)" class="wa-btn-ghost mt-lg w-full">+ Add education</button>
      </div>
    </template>

    <template x-if="profSection === 'experience'">
      <div>
        <template x-if="!(me?.experiences || []).length">
          <div>
            <label class="flex cursor-pointer items-center gap-sm">
              <input type="checkbox" :checked="profIsFresher" @click.prevent="profSetFresher(!profIsFresher)" :disabled="profBusy" class="h-5 w-5 rounded accent-[#EB0401]">
              <span class="text-body text-ink">I'm a fresher — no work experience yet</span>
            </label>
            <div class="mt-xl">@include('webapp.ui.empty', ['icon' => 'briefcase', 'title' => 'No experience added yet', 'text' => "Add your work history so hospitals can see where you've worked."])</div>
          </div>
        </template>
        <p x-show="(me?.experiences || []).length && profTotalExperience" class="mb-md text-bodysm font-semibold text-ink" x-text="'Total experience: ' + profTotalExperience"></p>
        <div class="space-y-md">
          <template x-for="x in profExperiences" :key="x.id">
            <div class="wa-card flex items-start gap-sm !p-lg">
              <div class="min-w-0 flex-1">
                <p class="text-h5 text-ink" x-text="x.designation"></p>
                <p class="text-bodysm text-ink-secondary" x-text="x.organization"></p>
                <p class="text-caption text-ink-muted" x-text="[profPeriod(x), profDuration(x), (x.cities || []).join(', ')].filter(Boolean).join(' · ')"></p>
                <span x-show="x.department" class="wa-tag mt-xs" x-text="x.department"></span>
                <p x-show="x.description" class="mt-sm line-clamp-3 whitespace-pre-line text-bodysm text-ink-secondary" x-text="x.description"></p>
              </div>
              <button type="button" @click="profExpOpenForm(x)" aria-label="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted"><span x-html="ICONS.pencil" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span></button>
              <button type="button" @click="profExpRemove(x)" aria-label="Remove" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-danger hover:bg-danger-bg"><span x-html="ICONS.trash" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span></button>
            </div>
          </template>
        </div>
        <button type="button" @click="profExpOpenForm(null)" class="wa-btn-ghost mt-lg w-full">+ Add experience</button>
        <div class="mt-xl">@include('webapp.screens.profile.team')</div>
      </div>
    </template>

    <template x-if="profSection === 'languages'">
      <div>
        @include('webapp.screens.profile.languages')
        <button type="button" @click="profSaveLanguages()" :disabled="profBusy" class="wa-btn mt-xl w-full">
          <span x-show="profBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> Save languages
        </button>
      </div>
    </template>

    <template x-if="profSection === 'preferences'">
      <div>
        @include('webapp.screens.profile.preferences')
        <button type="button" @click="profSavePreferences()" :disabled="profBusy" class="wa-btn mt-xl w-full">
          <span x-show="profBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> Save preferences
        </button>
      </div>
    </template>

    <template x-if="profSection === 'availability'">
      <div>
        @include('webapp.screens.profile.availability')
        <button type="button" @click="profSaveAvailability()" :disabled="profBusy" class="wa-btn mt-xl w-full">
          <span x-show="profBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> Save availability
        </button>
      </div>
    </template>

    <template x-if="profSection === 'about'">
      <div>
        @include('webapp.screens.profile.about')
        <button type="button" @click="profSaveAbout()" :disabled="profBusy" class="wa-btn mt-lg w-full">
          <span x-show="profBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> Save
        </button>
        <div class="mt-xxl">@include('webapp.screens.profile.video')</div>
      </div>
    </template>

    <template x-if="profSection === 'skills'">
      <div>
        @include('webapp.screens.profile.skills')
        <button type="button" @click="profSaveAbout()" :disabled="profBusy" class="wa-btn mt-xl w-full">
          <span x-show="profBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> Save
        </button>
      </div>
    </template>

    {{-- Add / edit sheets — the app's bottom sheets. --}}
    <template x-if="profEduOpen || profExpOpen">
      <div class="prof-sheet fixed inset-0 z-[85] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" @keydown.escape.window="profEduOpen = profExpOpen = false">
        <div class="max-h-[92vh] w-full max-w-[560px] overflow-y-auto rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog animate-enter-up">
          <div class="mb-lg flex items-center justify-between gap-md">
            <h2 class="text-h3 text-ink" x-text="profEduOpen ? (pf.edu.id ? 'Edit education' : 'Add education') : (pf.exp.id ? 'Edit experience' : 'Add experience')"></h2>
            <button type="button" @click="profEduOpen = profExpOpen = false" aria-label="Close" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted"><span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span></button>
          </div>
          <template x-if="profEduOpen">
            <div>
              @include('webapp.screens.profile.education-form')
              <button type="button" @click="profEduSubmit()" :disabled="!profEduCanSave || profBusy" class="wa-btn mt-xl w-full" x-text="pf.edu.id ? 'Save changes' : 'Add education'"></button>
            </div>
          </template>
          <template x-if="profExpOpen">
            <div>
              @include('webapp.screens.profile.experience-form')
              <button type="button" @click="profExpSubmit()" :disabled="!profExpCanSave || profBusy" class="wa-btn mt-xl w-full" x-text="pf.exp.id ? 'Save changes' : 'Add experience'"></button>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>

{{-- ── Resume ──────────────────────────────────────────────────────────── --}}
<template x-if="is('resume')">
  <div class="wa-narrow">
    @include('webapp.ui.head', ['title' => 'Resume', 'back' => '/profile'])

    <span class="wa-label">Your resume</span>
    <div class="wa-card flex items-center gap-md !p-md">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-field bg-primary-light text-primary"><span x-html="ICONS.fileText" class="[&>svg]:h-5 [&>svg]:w-5"></span></span>
      <div class="min-w-0 flex-1">
        <template x-if="me?.resume">
          <a :href="me.resume_url" target="_blank" rel="noopener" class="block truncate text-h5 text-ink hover:text-primary" x-text="me.resume"></a>
        </template>
        <template x-if="!me?.resume"><p class="text-h5 text-ink">No resume yet</p></template>
        <p class="text-caption text-ink-secondary" x-text="me?.resume ? 'Built from your profile · tap to preview' : 'Written for you from your profile'"></p>
      </div>
    </div>

    <template x-if="me?.resume">
      <div class="mt-xl">
        <a :href="me.resume_url" :download="me.resume" target="_blank" rel="noopener" class="wa-btn w-full">
          <span x-html="ICONS.download" class="[&>svg]:h-5 [&>svg]:w-5"></span> Download
        </a>
        <div class="mt-sm grid grid-cols-2 gap-sm">
          <a :href="me.resume_url" target="_blank" rel="noopener" class="wa-btn-ghost-sm"><span x-html="ICONS.eye" class="[&>svg]:h-4 [&>svg]:w-4"></span> Open</a>
          <button type="button" @click="share(me.resume, me.resume_url)" class="wa-btn-ghost-sm"><span x-html="ICONS.share" class="[&>svg]:h-4 [&>svg]:w-4"></span> Share</button>
        </div>
      </div>
    </template>

    <template x-if="!me?.resume">
      <div class="mt-xl">
        {{-- Named one by one — "complete your profile" would send someone off to
             record a video and back to the same empty screen. --}}
        <template x-if="profMissingForResume.length">
          <div class="flex gap-sm rounded-card bg-primary-light p-md">
            <span x-html="ICONS.info" class="mt-[1px] text-primary [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            <div>
              <p class="text-h5 text-ink">Add these and your resume writes itself</p>
              <template x-for="m in profMissingForResume" :key="m"><p class="mt-[2px] text-caption text-ink-secondary" x-text="'• ' + m"></p></template>
              <div class="mt-sm flex flex-wrap gap-xs">
                <a x-show="profMissingForResume.includes('your name')" href="#/profile/personal" class="wa-btn-ghost-sm !h-8">Add name</a>
                <a x-show="profMissingForResume.includes('your qualification')" href="#/profile/education" class="wa-btn-ghost-sm !h-8">Add education</a>
                <a x-show="profMissingForResume.some((m) => m.startsWith('your experience'))" href="#/profile/experience" class="wa-btn-ghost-sm !h-8">Add experience</a>
              </div>
            </div>
          </div>
        </template>
        <template x-if="!profMissingForResume.length">
          <button type="button" @click="profGenerateResume()" :disabled="profResumeBusy" class="wa-btn w-full">
            <span x-html="profResumeBusy ? ICONS.loader : ICONS.fileText" :class="profResumeBusy && 'animate-spin'" class="[&>svg]:h-5 [&>svg]:w-5"></span> Create my resume
          </button>
        </template>
      </div>
    </template>

    <p class="mt-sm text-center text-caption text-ink-muted" x-text="me?.resume ? 'Built from your profile and kept up to date with it — change anything and this changes with it.' : 'It is written for you as soon as your profile has enough on it.'"></p>

    <template x-if="me?.resume">
      <div class="mt-xl">
        <p class="wa-section-title mb-sm">Watermark</p>
        <template x-if="me.resume_watermark_free">
          <div class="wa-card flex items-center gap-md !p-md">
            <span x-html="ICONS.checkCircle" class="text-success [&>svg]:h-5 [&>svg]:w-5"></span>
            <p class="text-bodysm text-ink">Your resume downloads without the INTHES mark.</p>
          </div>
        </template>
        <template x-if="!me.resume_watermark_free">
          <div class="wa-card !p-md">
            <p class="text-h5 text-ink">Your resume carries the INTHES logo</p>
            <p class="mt-[4px] text-caption text-ink-secondary" x-text="profCleanOffer?.description || 'Downloads are free, and the logo sits faintly behind the text.'"></p>
            <template x-if="profCleanOffer">
              <div>
                <button type="button" @click="profBuyClean()" :disabled="profBuying" class="wa-btn-ghost-sm mt-md w-full">
                  <span x-show="profBuying" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span>
                  <span x-text="'Remove it for ' + profCleanOffer.price_label"></span>
                </button>
                <p class="mt-xs text-caption text-ink-muted">One payment. Every future version is clean too.</p>
              </div>
            </template>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
