{{--
  /account — the job seeker's profile on one page (Naukri / LinkedIn style).

  A summary card on top (photo, who, profile strength, what to do next, the
  resume), then every section of the app's profile as its own card, in the
  app's order. A card reads like a profile; its Edit turns that card into a
  form in place. Fields, options, rules and endpoints come from the app via
  js/site/pages/account-profile-kit.js; the field markup is shared with
  /account/setup (site/profile/*).
--}}
@extends('site.account.layout')

@section('title', 'My profile — Inthes')
@section('account_key', 'profile')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-profile-kit.js') }}?v={{ filemtime(public_path('js/site/pages/account-profile-kit.js')) }}"></script>
<script defer src="{{ asset('js/site/pages/account-profile.js') }}?v={{ filemtime(public_path('js/site/pages/account-profile.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountProfile()">
  <template x-if="!me">@include('site.ui.spinner')</template>

  <div x-show="me" x-cloak class="xl:grid xl:grid-cols-[minmax(0,1fr)_240px] xl:items-start xl:gap-xl">
    <div class="min-w-0 space-y-lg">

      {{-- ── Summary ──────────────────────────────────────────────────── --}}
      <section id="profile-top" class="wa-card scroll-mt-[92px] overflow-hidden !p-0">
        <div class="hero-wash dot-grid h-[72px] bg-primary-light/60"></div>
        <div class="-mt-[44px] flex flex-col gap-xl px-lg pb-lg sm:px-xl md:flex-row md:items-end">
          <div class="flex min-w-0 flex-1 flex-col gap-md sm:flex-row sm:items-end">
            {{-- Photo: click to add or replace. --}}
            <div class="relative shrink-0 self-start">
              <label class="group relative block cursor-pointer" :title="me?.photo ? 'Change photo' : 'Add a photo'">
                <span class="flex h-[96px] w-[96px] items-center justify-center overflow-hidden rounded-full bg-surface-muted text-h2 font-semibold text-ink-secondary ring-4 ring-surface">
                  <template x-if="me?.photo_url"><img :src="me.photo_url" alt="Your photo" class="h-full w-full object-cover"></template>
                  <template x-if="!me?.photo_url && profInitials(me?.name)"><span x-text="profInitials(me?.name)"></span></template>
                  <template x-if="!me?.photo_url && !profInitials(me?.name)"><span x-html="ICONS.user" class="text-ink-muted [&>svg]:h-10 [&>svg]:w-10"></span></template>
                </span>
                <span class="absolute inset-0 hidden items-center justify-center rounded-full bg-ink/45 text-caption font-semibold text-ink-onPrimary group-hover:flex" x-show="!profPhotoBusy" x-text="me?.photo ? 'Change' : 'Add photo'"></span>
                <span x-show="profPhotoBusy" class="absolute inset-0 flex items-center justify-center rounded-full bg-ink/45 text-ink-onPrimary">
                  <span x-html="ICONS.loader" class="animate-spin [&>svg]:h-6 [&>svg]:w-6"></span>
                </span>
                <span class="absolute bottom-[2px] right-[2px] flex h-7 w-7 items-center justify-center rounded-full bg-primary text-ink-onPrimary ring-2 ring-surface">
                  <span x-html="ICONS.camera" class="[&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
                </span>
                <input x-ref="photoInput" type="file" accept="image/jpeg,image/png" class="sr-only" @change="profPickPhoto($event.target)" aria-label="Upload a profile photo (JPG or PNG, up to 3 MB)">
              </label>
            </div>
            <div class="min-w-0 flex-1 sm:pb-[2px]">
              <h1 class="truncate text-h2 text-ink" x-text="me?.name || 'Your name'"></h1>
              <p class="text-bodysm text-ink-secondary" x-text="headline"></p>
              <div class="mt-sm flex flex-wrap gap-x-lg gap-y-xs text-caption text-ink-secondary">
                <span x-show="me?.phone" class="inline-flex items-center gap-xs">
                  <span x-html="ICONS.phone" class="text-ink-muted [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
                  <span x-text="'+91 ' + (me?.phone || '')"></span>
                  <span class="inline-flex items-center gap-[2px] font-semibold text-success"><span x-html="ICONS.badgeCheck" class="[&>svg]:h-[13px] [&>svg]:w-[13px]"></span>Verified</span>
                </span>
                <span x-show="me?.email" class="inline-flex min-w-0 items-center gap-xs">
                  <span x-html="ICONS.mail" class="text-ink-muted [&>svg]:h-[14px] [&>svg]:w-[14px]"></span><span class="truncate" x-text="me?.email"></span>
                </span>
                <span x-show="place" class="inline-flex items-center gap-xs">
                  <span x-html="ICONS.mapPin" class="text-ink-muted [&>svg]:h-[14px] [&>svg]:w-[14px]"></span><span x-text="place"></span>
                </span>
              </div>
              <button type="button" x-show="me?.photo" @click="profDeletePhoto()" :disabled="profPhotoBusy" class="mt-xs text-caption text-ink-muted underline-offset-2 hover:text-danger hover:underline">Remove photo</button>
            </div>
          </div>

          {{-- Strength: the server's number and the app's words for it. --}}
          <div class="rounded-card border-hair border-hairline bg-surface-muted/60 p-md md:w-[230px] md:shrink-0">
            <div class="flex items-baseline justify-between">
              <span class="text-kicker uppercase text-ink-secondary">Profile strength</span>
              <span class="text-[26px] font-bold leading-none text-ink" x-text="profStrength + '%'"></span>
            </div>
            <div class="mt-sm h-2 overflow-hidden rounded-full bg-hairline">
              <div class="h-full rounded-full bg-primary transition-[width] duration-700 ease-out" :style="'width:' + profStrength + '%'"></div>
            </div>
            <div class="mt-xs flex items-center justify-between gap-sm">
              <span class="text-bodysm font-semibold text-ink" x-text="profStrengthLabel"></span>
              <span class="text-caption text-ink-muted" x-text="profHiringLabel.replace('Hiring chance: ', 'Chance: ')"></span>
            </div>
          </div>
        </div>

        {{-- A near-empty profile gets the short guided route too. --}}
        <template x-if="profStrength < 40">
          <a href="{{ url('/account/setup') }}" class="group mx-lg mb-lg flex items-center gap-md rounded-field bg-primary px-lg py-md text-ink-onPrimary shadow-button hover:bg-primary-dark sm:mx-xl">
            <span x-html="ICONS.sparkles" class="shrink-0 [&>svg]:h-5 [&>svg]:w-5"></span>
            <span class="min-w-0 flex-1"><span class="block text-bodysm font-semibold">Complete your profile in 3 quick steps</span><span class="block text-caption text-white/80">About you, education &amp; experience, what you're looking for</span></span>
            <span x-html="ICONS.arrowRight" class="nudge shrink-0 [&>svg]:h-5 [&>svg]:w-5"></span>
          </a>
        </template>

        {{-- What to do next — each jumps to the card that fixes it. --}}
        <template x-if="boosts.length">
          <div class="border-t border-hairline-divider px-lg py-lg sm:px-xl" x-data="{ all: false }">
            <p class="wa-section-title mb-sm">Complete these to go further</p>
            <div class="grid gap-xs sm:grid-cols-2">
              <template x-for="b in (all ? boosts : boosts.slice(0, 4))" :key="b.label">
                <button type="button" @click="boostGo(b)"
                        class="group flex items-center gap-sm rounded-field border-hair border-dashed border-hairline-strong px-md py-sm text-left transition-colors hover:border-primary hover:bg-primary-light/40">
                  <span class="h-[14px] w-[14px] shrink-0 rounded-full border-[1.5px] border-ink-muted"></span>
                  <span class="min-w-0 flex-1 text-bodysm font-semibold text-ink" x-text="b.label"></span>
                  <span class="shrink-0 rounded-field bg-primary-light px-sm py-[2px] text-tag font-bold text-primary-dark" x-text="b.gain"></span>
                  <span x-html="ICONS.chevronRight" class="nudge shrink-0 text-ink-muted [&>svg]:h-4 [&>svg]:w-4"></span>
                </button>
              </template>
            </div>
            <button type="button" x-show="boosts.length > 4" @click="all = !all" class="mt-sm text-caption font-semibold text-primary hover:underline"
                    x-text="all ? 'Show fewer' : 'Show all ' + boosts.length"></button>
          </div>
        </template>

        {{-- The resume, one click away. --}}
        <div class="flex flex-wrap items-center gap-md border-t border-hairline-divider px-lg py-md sm:px-xl">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-field bg-primary-light text-primary"><span x-html="ICONS.fileText" class="[&>svg]:h-5 [&>svg]:w-5"></span></span>
          <div class="min-w-0 flex-1">
            <p class="text-h5 text-ink">Resume</p>
            <p class="truncate text-caption text-ink-secondary"
               x-text="me?.resume ? 'Built from this profile and kept up to date with it' : (profMissingForResume.length ? 'Add ' + profMissingForResume.map((m) => m.replace(/^your /, '').replace(/ \(.*\)$/, '')).join(', ') + ' and it writes itself' : 'Ready to create from your profile')"></p>
          </div>
          <div class="flex w-full flex-wrap gap-sm sm:w-auto">
            <template x-if="me?.resume">
              <div class="flex flex-1 gap-sm sm:flex-none">
                <a href="{{ url('/account/resume') }}" class="wa-btn-ghost-sm flex-1 sm:flex-none"><span x-html="ICONS.eye" class="[&>svg]:h-4 [&>svg]:w-4"></span> View</a>
                <a :href="me.resume_url" :download="me.resume" target="_blank" rel="noopener" class="wa-btn-sm flex-1 sm:flex-none"><span x-html="ICONS.download" class="[&>svg]:h-4 [&>svg]:w-4"></span> Download</a>
              </div>
            </template>
            <template x-if="!me?.resume && !profMissingForResume.length">
              <button type="button" @click="profGenerateResume()" :disabled="profResumeBusy" class="wa-btn-sm flex-1 sm:flex-none">
                <span x-html="profResumeBusy ? ICONS.loader : ICONS.sparkles" :class="profResumeBusy && 'animate-spin'" class="[&>svg]:h-4 [&>svg]:w-4"></span> Generate resume
              </button>
            </template>
            <template x-if="!me?.resume && profMissingForResume.length">
              <a href="{{ url('/account/resume') }}" class="wa-btn-ghost-sm flex-1 sm:flex-none">What's needed</a>
            </template>
          </div>
          <template x-if="me?.resume && cleanOffer">
            <button type="button" @click="buyClean()" :disabled="buying" class="flex w-full items-center gap-sm rounded-field bg-surface-muted px-md py-sm text-left hover:bg-hairline">
              <span x-html="ICONS.sparkles" class="text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
              <span class="flex-1 text-bodysm text-ink">Want it without the INTHES mark?</span>
              <span x-show="!buying" class="text-bodysm font-bold text-primary" x-text="cleanOffer.price_label"></span>
              <span x-show="buying" x-html="ICONS.loader" class="animate-spin text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
            </button>
          </template>
        </div>
      </section>

      {{-- ── Personal information ─────────────────────────────────────── --}}
      <section id="sec-personal" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'personal', 'title' => 'Personal information', 'icon' => 'user', 'edit' => 'personal'])
        <template x-if="editing !== 'personal'">
          <dl class="mt-lg grid grid-cols-2 gap-x-lg gap-y-md sm:gap-x-xl">
            @include('site.profile.field', ['label' => 'Full name', 'value' => 'me?.name'])
            <div class="min-w-0">
              <dt class="text-caption text-ink-muted">Mobile number</dt>
              <dd class="mt-[2px] flex items-center gap-xs text-bodysm text-ink"><span x-text="me?.phone ? '+91 ' + me.phone : 'Not added'"></span>
                <span x-show="me?.phone" class="inline-flex items-center gap-[2px] text-caption font-semibold text-success"><span x-html="ICONS.badgeCheck" class="[&>svg]:h-[13px] [&>svg]:w-[13px]"></span>Verified</span></dd>
            </div>
            @include('site.profile.field', ['label' => 'Email', 'value' => 'me?.email'])
            @include('site.profile.field', ['label' => 'Gender', 'value' => 'me?.gender'])
            @include('site.profile.field', ['label' => 'Date of birth', 'value' => "me?.dob ? fmtDate(me.dob) + (profAgeOf(me.dob) != null ? ' (' + profAgeOf(me.dob) + ' years)' : '') : ''"])
            <div class="min-w-0">
              <dt class="text-caption text-ink-muted">Where you live</dt>
              <dd class="mt-[2px] text-bodysm" :class="me?.address ? 'text-ink' : 'text-ink-muted'">
                <span x-text="me?.address || 'Not added'"></span>
                <span x-show="me?.home_latitude != null && me?.home_longitude != null" class="wa-pill ml-xs bg-primary-light text-primary-dark">Pinned</span>
              </dd>
            </div>
            @include('site.profile.field', ['label' => 'Current state', 'value' => 'me?.home_state'])
            @include('site.profile.field', ['label' => 'Current city', 'value' => 'me?.home_city'])
            @include('site.profile.field', ['label' => 'Marital status', 'value' => 'me?.marital_status'])
            @include('site.profile.field', ['label' => 'Nationality', 'value' => 'me?.nationality'])
            @include('site.profile.field', ['label' => "Father's name", 'value' => 'me?.father_name'])
            @include('site.profile.field', ['label' => "Mother's name", 'value' => 'me?.mother_name'])
            @include('site.profile.field', ['label' => 'Native place', 'value' => 'me?.native_place'])
            @include('site.profile.field', ['label' => 'Alternate mobile', 'value' => "me?.alternate_phone ? '+91 ' + me.alternate_phone : ''"])
            @include('site.profile.field', ['label' => 'WhatsApp', 'value' => "me?.whatsapp_number ? '+91 ' + me.whatsapp_number : ''"])
          </dl>
        </template>
        <template x-if="editing === 'personal'">
          <div class="mt-lg">
            @include('site.profile.personal')
            @include('site.profile.save-row', ['save' => 'profSavePersonal()'])
          </div>
        </template>
      </section>

      {{-- ── Education ────────────────────────────────────────────────── --}}
      <section id="sec-education" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'education', 'title' => 'Education', 'icon' => 'graduation', 'add' => 'Add education', 'addKey' => 'edu', 'addClick' => "startEdit('edu')"])
        <template x-if="!profEducations.length && editing !== 'edu'">
          <div class="mt-lg flex flex-col items-start gap-sm rounded-field border-hair border-dashed border-hairline-strong p-lg sm:flex-row sm:items-center">
            <p class="flex-1 text-bodysm text-ink-secondary">Most postings filter on qualification. Add what you studied and where.</p>
            <button type="button" @click="startEdit('edu')" class="wa-btn-ghost-sm">+ Add education</button>
          </div>
        </template>
        <ol x-show="profEducations.length" class="mt-lg">
          <template x-for="(e, i) in profEducations" :key="e.id">
            <li class="relative flex gap-md pb-lg last:pb-0">
              <span x-show="i < profEducations.length - 1" class="absolute left-[15px] top-9 bottom-0 w-px bg-hairline"></span>
              <span class="relative z-[1] mt-[2px] flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink-secondary"><span x-html="ICONS.graduation" class="[&>svg]:h-4 [&>svg]:w-4"></span></span>
              <div class="min-w-0 flex-1" :class="editing === 'edu' && pf.edu.id === e.id && 'opacity-50'">
                <p class="text-h5 text-ink" x-text="e.qualification"></p>
                <p x-show="e.specialization" class="text-bodysm text-ink-secondary" x-text="e.specialization"></p>
                <p x-show="e.institute" class="text-bodysm text-ink-secondary" x-text="e.institute"></p>
                <p x-show="eduLine(e)" class="mt-[2px] text-caption text-ink-muted" x-text="eduLine(e)"></p>
              </div>
              <div class="flex shrink-0 gap-[2px]">
                <button type="button" @click="editEdu(e)" aria-label="Edit education" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted"><span x-html="ICONS.pencil" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
                <button type="button" @click="profEduRemove(e)" :disabled="profBusy" aria-label="Remove education" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-danger hover:bg-danger-bg"><span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
              </div>
            </li>
          </template>
        </ol>
        <template x-if="editing === 'edu'">
          <div class="mt-lg rounded-card border-hair border-primary/30 bg-canvas p-lg">
            <h3 class="mb-lg text-h5 text-ink" x-text="pf.edu.id ? 'Edit education' : 'Add education'"></h3>
            @include('site.profile.education-form')
            @include('site.profile.save-row', ['save' => 'saveEdu()', 'labelExpr' => "pf.edu.id ? 'Save changes' : 'Add education'", 'disabled' => '!profEduCanSave'])
          </div>
        </template>
      </section>

      {{-- ── Experience ───────────────────────────────────────────────── --}}
      <section id="sec-experience" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'experience', 'title' => 'Experience', 'icon' => 'briefcase', 'add' => 'Add experience', 'addKey' => 'exp', 'addClick' => "startEdit('exp')"])

        <div x-show="me?.experience || profTotalExperience" class="mt-md flex flex-wrap items-center gap-sm">
          <span class="wa-tag !text-ink"><span class="text-ink-muted">Total experience</span><b class="font-semibold" x-text="me?.experience || '—'"></b></span>
          <span x-show="(me?.experiences || []).length && profTotalExperience" class="text-caption text-ink-muted" x-text="profTotalExperience + ' from the roles below'"></span>
        </div>

        <template x-if="!profExperiences.length && editing !== 'exp'">
          <div class="mt-lg rounded-field border-hair border-dashed border-hairline-strong p-lg">
            <label class="flex cursor-pointer items-center gap-sm">
              <input type="checkbox" :checked="profIsFresher" @click.prevent="profSetFresher(!profIsFresher)" :disabled="profBusy" class="h-5 w-5 rounded accent-[#EB0401]">
              <span class="text-body text-ink">I'm a fresher — no work experience yet</span>
            </label>
            <div class="mt-md flex flex-col items-start gap-sm sm:flex-row sm:items-center">
              <p class="flex-1 text-bodysm text-ink-secondary">Add your work history so hospitals can see where you've worked.</p>
              <button type="button" @click="startEdit('exp')" class="wa-btn-ghost-sm">+ Add experience</button>
            </div>
          </div>
        </template>

        <ol x-show="profExperiences.length" class="mt-lg">
          <template x-for="(x, i) in profExperiences" :key="x.id">
            <li class="relative flex gap-md pb-lg last:pb-0">
              <span x-show="i < profExperiences.length - 1" class="absolute left-[15px] top-9 bottom-0 w-px bg-hairline"></span>
              <span class="relative z-[1] mt-[2px] flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="x.currently_working ? 'bg-primary-light text-primary' : 'bg-surface-muted text-ink-secondary'"><span x-html="ICONS.building" class="[&>svg]:h-4 [&>svg]:w-4"></span></span>
              <div class="min-w-0 flex-1" :class="editing === 'exp' && pf.exp.id === x.id && 'opacity-50'">
                <p class="text-h5 text-ink"><span x-text="x.designation"></span>
                  <span x-show="x.currently_working" class="wa-pill ml-xs bg-success-bg align-middle text-success">Current</span></p>
                <p class="text-bodysm text-ink-secondary" x-text="x.organization"></p>
                <p class="text-caption text-ink-muted" x-text="expLine(x)"></p>
                <span x-show="x.department" class="wa-tag mt-xs" x-text="x.department"></span>
                <p x-show="x.description" class="mt-sm whitespace-pre-line text-bodysm text-ink-secondary" x-text="x.description"></p>
              </div>
              <div class="flex shrink-0 gap-[2px]">
                <button type="button" @click="editExp(x)" aria-label="Edit experience" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted"><span x-html="ICONS.pencil" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
                <button type="button" @click="profExpRemove(x)" :disabled="profBusy" aria-label="Remove experience" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-danger hover:bg-danger-bg"><span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
              </div>
            </li>
          </template>
        </ol>

        <template x-if="editing === 'exp'">
          <div class="mt-lg rounded-card border-hair border-primary/30 bg-canvas p-lg">
            <h3 class="mb-lg text-h5 text-ink" x-text="pf.exp.id ? 'Edit experience' : 'Add experience'"></h3>
            @include('site.profile.experience-form')
            @include('site.profile.save-row', ['save' => 'saveExp()', 'labelExpr' => "pf.exp.id ? 'Save changes' : 'Add experience'", 'disabled' => '!profExpCanSave'])
          </div>
        </template>

        {{-- Saved as it is answered, as in the app's editor. --}}
        <div class="mt-xl border-t border-hairline-divider pt-lg">
          @include('site.profile.team')
        </div>
      </section>

      {{-- ── Languages ────────────────────────────────────────────────── --}}
      <section id="sec-languages" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'languages', 'title' => 'Languages', 'icon' => 'languages', 'edit' => 'languages'])
        <template x-if="editing !== 'languages'">
          <div class="mt-lg">
            <div x-show="(me?.languages || []).length" class="grid gap-sm sm:grid-cols-2">
              <template x-for="l in (me?.languages || [])" :key="l">
                <div class="flex items-center justify-between gap-sm rounded-field bg-surface-muted px-md py-sm">
                  <span class="text-bodysm font-semibold text-ink" x-text="l"></span>
                  <span class="text-caption text-ink-secondary" x-text="langLine(l) || 'Level not set'"></span>
                </div>
              </template>
            </div>
            <div x-show="!(me?.languages || []).length" class="flex flex-col items-start gap-sm rounded-field border-hair border-dashed border-hairline-strong p-lg sm:flex-row sm:items-center">
              <p class="flex-1 text-bodysm text-ink-secondary">Useful wherever a role deals with patients or customers.</p>
              <button type="button" @click="startEdit('languages')" class="wa-btn-ghost-sm">+ Add languages</button>
            </div>
          </div>
        </template>
        <template x-if="editing === 'languages'">
          <div class="mt-lg">
            @include('site.profile.languages')
            @include('site.profile.save-row', ['save' => 'profSaveLanguages()'])
          </div>
        </template>
      </section>

      {{-- ── Job preferences ──────────────────────────────────────────── --}}
      <section id="sec-preferences" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'preferences', 'title' => 'Job preferences', 'icon' => 'target', 'edit' => 'preferences'])
        <template x-if="editing !== 'preferences'">
          <dl class="mt-lg grid grid-cols-2 gap-x-lg gap-y-md sm:gap-x-xl">
            @include('site.profile.chips-field', ['label' => 'Preferred cities', 'value' => 'me?.location'])
            @include('site.profile.chips-field', ['label' => 'Preferred roles', 'value' => 'me?.preferred_roles'])
            @include('site.profile.chips-field', ['label' => 'Job type', 'value' => 'me?.preferred_job_types'])
            @include('site.profile.chips-field', ['label' => 'Preferred shift', 'value' => 'me?.preferred_shifts'])
            @include('site.profile.chips-field', ['label' => 'Preferred workplace', 'value' => 'me?.preferred_organisation_types'])
            @include('site.profile.field', ['label' => 'Expected salary', 'value' => 'me?.expected_salary'])
          </dl>
        </template>
        <template x-if="editing === 'preferences'">
          <div class="mt-lg">
            <p class="mb-lg text-caption text-ink-secondary">We use this to pick the jobs we recommend to you.</p>
            @include('site.profile.preferences')
            @include('site.profile.save-row', ['save' => 'profSavePreferences()'])
          </div>
        </template>
      </section>

      {{-- ── Availability ─────────────────────────────────────────────── --}}
      <section id="sec-availability" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'availability', 'title' => 'Availability', 'icon' => 'calendar', 'edit' => 'availability'])
        <template x-if="editing !== 'availability'">
          <dl class="mt-lg grid grid-cols-2 gap-x-lg gap-y-md sm:gap-x-xl">
            @include('site.profile.field', ['label' => 'Currently employed?', 'value' => 'yn(me?.currently_employed)'])
            <template x-if="me?.currently_employed === true">@include('site.profile.field', ['label' => 'Notice period', 'value' => 'me?.notice_period'])</template>
            <template x-if="me?.currently_employed === true && me?.last_working_date">@include('site.profile.field', ['label' => 'Last working date', 'value' => 'fmtDate(me.last_working_date)'])</template>
            @include('site.profile.field', ['label' => 'Immediate joiner?', 'value' => 'yn(me?.immediate_joiner)'])
            <template x-if="me?.immediate_joiner === false">@include('site.profile.field', ['label' => 'Earliest joining date', 'value' => "me?.earliest_joining_date ? fmtDate(me.earliest_joining_date) : ''"])</template>
            @include('site.profile.field', ['label' => 'Willing to relocate?', 'value' => 'yn(me?.willing_to_relocate)'])
            @include('site.profile.field', ['label' => 'Own vehicle?', 'value' => 'yn(me?.has_vehicle)'])
            @include('site.profile.field', ['label' => 'Driving licence?', 'value' => 'yn(me?.has_driving_licence)'])
          </dl>
        </template>
        <template x-if="editing === 'availability'">
          <div class="mt-lg">
            <p class="mb-lg text-caption text-ink-secondary">When can you start? Tap an answer again to clear it.</p>
            @include('site.profile.availability')
            @include('site.profile.save-row', ['save' => 'profSaveAvailability()'])
          </div>
        </template>
      </section>

      {{-- ── About me + Skills & interests ────────────────────────────── --}}
      <section id="sec-about" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'about', 'title' => 'About me & skills', 'icon' => 'sparkles', 'edit' => 'about'])
        <template x-if="editing !== 'about'">
          <div class="mt-lg space-y-lg">
            <div>
              <p class="text-caption text-ink-muted">About me</p>
              <p x-show="me?.about" class="mt-[2px] whitespace-pre-line text-bodysm leading-relaxed text-ink" x-text="me?.about"></p>
              <p x-show="!me?.about" class="mt-[2px] text-bodysm text-ink-muted">Two or three sentences on what you do and what you're looking for. <button type="button" @click="startEdit('about')" class="font-semibold text-primary hover:underline">Write it</button></p>
            </div>
            <dl class="grid gap-x-xl gap-y-md sm:grid-cols-2">
              @include('site.profile.chips-field', ['label' => 'Professional skills', 'value' => 'me?.skills'])
              @include('site.profile.chips-field', ['label' => 'Hobbies & interests', 'value' => 'me?.hobbies'])
            </dl>
          </div>
        </template>
        <template x-if="editing === 'about'">
          <div class="mt-lg">
            @include('site.profile.about')
            <div class="mt-xl">@include('site.profile.skills')</div>
            @include('site.profile.save-row', ['save' => 'profSaveAbout()'])
          </div>
        </template>
      </section>

      {{-- ── Intro video ──────────────────────────────────────────────── --}}
      <section id="sec-video" class="wa-card scroll-mt-[92px] !p-lg sm:!p-xl">
        @include('site.profile.card-head', ['id' => 'video', 'title' => 'Intro video', 'icon' => 'video'])
        <div class="mt-lg">@include('site.profile.video', ['heading' => false])</div>
      </section>
    </div>

    {{-- ── On this page (desktop) ─────────────────────────────────────── --}}
    <aside class="hidden xl:sticky xl:top-[92px] xl:block">
      <nav class="wa-card !p-md" aria-label="Profile sections">
        <p class="wa-section-title px-sm pb-xs">On this page</p>
        <template x-for="s in sections" :key="s.id">
          <a :href="'#' + s.id" @click.prevent="jump(s.id)"
             :class="active === s.id ? 'bg-primary-light text-primary-dark' : 'text-ink hover:bg-surface-muted'"
             class="flex items-center gap-sm rounded-field px-sm py-[7px] text-bodysm">
            <span x-show="s.complete" x-html="ICONS.checkCircle" class="shrink-0 text-success [&>svg]:h-4 [&>svg]:w-4"></span>
            <span x-show="!s.complete" class="mx-[1px] h-[14px] w-[14px] shrink-0 rounded-full border-[1.5px]" :class="s.empty ? 'border-ink-muted' : 'border-warning'"></span>
            <span class="min-w-0 flex-1 truncate" :class="active === s.id && 'font-semibold'" x-text="s.label"></span>
          </a>
        </template>
        <div class="mt-sm border-t border-hairline-divider px-sm pt-sm">
          <p class="flex items-center justify-between text-caption"><span class="text-ink-secondary">Profile strength</span><span class="font-semibold text-primary-dark" x-text="profStrength + '%'"></span></p>
          <span class="mt-[3px] block h-[5px] overflow-hidden rounded-full bg-surface-muted"><span class="block h-full rounded-full bg-primary" :style="`width:${profStrength}%`"></span></span>
        </div>
      </nav>
      <a href="{{ url('/account/resume') }}" class="wa-card mt-md flex items-center gap-sm !p-md hover:border-primary">
        <span x-html="ICONS.fileText" class="text-primary [&>svg]:h-5 [&>svg]:w-5"></span>
        <span class="flex-1 text-bodysm font-semibold text-ink">Resume preview</span>
        <span x-html="ICONS.chevronRight" class="text-ink-muted [&>svg]:h-4 [&>svg]:w-4"></span>
      </a>
    </aside>
  </div>
</div>
@endsection
