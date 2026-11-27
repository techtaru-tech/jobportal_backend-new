{{--
  /account/setup — "Create your profile", the app's own 10-step wizard
  (OnboardingView): one section per step, in the app's order, with its
  titles, the 10-segment bar, "Step N of 10 · N answered", "Do it later",
  and Save & continue / Skip on every step. Reached from an Apply
  (?next=/jobs/{code}?apply=1), the steps that job needs cannot be skipped,
  their questions carry a *, and "Do it later" becomes a close — the app's
  gated onboarding.

  Fields are the profile page's own (site/profile/*), so the same answers
  and rules apply in both places; js/site/pages/account-setup.js saves each
  step.
--}}
@extends('site.account.layout')

@section('title', 'Create your profile — Inthes')
@section('account_key', 'profile')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-profile-kit.js') }}?v={{ filemtime(public_path('js/site/pages/account-profile-kit.js')) }}"></script>
<script defer src="{{ asset('js/site/pages/account-setup.js') }}?v={{ filemtime(public_path('js/site/pages/account-setup.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountSetup()" class="mx-auto max-w-[680px]">
  <template x-if="!ready">@include('site.ui.spinner')</template>

  <div x-show="ready" x-cloak>
    {{-- ── Header: the app's kicker, 10-segment bar and step count ───── --}}
    <div class="sticky top-0 z-20 -mx-page bg-canvas-alt/95 px-page pb-md pt-sm backdrop-blur lg:static lg:mx-0 lg:bg-transparent lg:px-0">
      <div class="flex items-center justify-between">
        <p class="text-kicker uppercase tracking-[1.2px] text-ink-secondary" x-text="nextIsApply ? 'Finish to apply' : 'Create your profile'"></p>
        {{-- "Do it later" only where later is a real option. In an apply run
             the way out is leaving the application, so it is a close. --}}
        <button type="button" x-show="!isGated" @click="later()" :disabled="busy" class="text-btn font-semibold text-primary hover:underline" data-setup-later>Do it later</button>
        <button type="button" x-show="isGated" x-cloak @click="abandonGate()" :disabled="busy" aria-label="Not now" title="Not now"
                class="-mr-xs inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-secondary hover:bg-surface">
          <span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
      </div>
      <p x-show="isGated" x-cloak class="mt-[2px] text-caption font-semibold text-primary" x-text="gateSummary"></p>
      <ol class="mt-md flex gap-[3px]" aria-label="Steps">
        <template x-for="(s, i) in STEPS" :key="s.key">
          <li class="flex-1">
            <button type="button" @click="goTo(i)" :disabled="i >= step || busy" :aria-label="s.title"
                    class="block h-[6px] w-full rounded-full transition-colors enabled:cursor-pointer"
                    :class="i === step ? 'bg-primary' : (answered(s.key) ? 'bg-primary' : (i < step ? 'bg-primary/35' : 'bg-hairline'))"></button>
          </li>
        </template>
      </ol>
      <p class="mt-sm text-caption text-ink-muted" x-text="'Step ' + (step + 1) + ' of ' + STEPS.length + ' · ' + answeredCount + ' answered'"></p>
    </div>

    {{-- ── Step title (OnboardingView: title + subtitle) ─────────────── --}}
    <div class="mt-lg">
      <h1 class="text-h1 text-ink" x-text="STEPS[step].title"></h1>
      <p class="mt-xs text-bodysm text-ink-secondary" x-text="STEPS[step].subtitle"></p>
      {{-- Says which steps are the gate, so a Save that will not go is explained. --}}
      <p x-show="stepRequired(STEPS[step].key)" x-cloak class="mt-sm inline-block rounded-field bg-primary-light px-sm py-[4px] text-caption font-semibold text-primary-dark">Required for this job</p>
    </div>

    {{-- 1 · About you --}}
    <template x-if="STEPS[step].key === 'personal'">
      <div class="mt-lg space-y-lg">
        <div class="rounded-card border-hair border-primary/35 bg-primary-light p-lg">
          <p class="flex items-center gap-xs text-bodysm font-bold text-primary-dark">
            <span x-html="ICONS.sparkles" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span> Describe yourself in one line
          </p>
          <p class="mt-[2px] text-caption text-ink-secondary">e.g. Staff nurse, 4 years ICU experience in Jaipur</p>
          <input type="text" x-model="quick" @keydown.enter.prevent="quickFill()" placeholder="Type here…" class="wa-input mt-sm bg-surface">
          <button type="button" @click="quickFill()" class="wa-btn-ghost mt-sm w-full">
            <span x-html="ICONS.sparkles" class="[&>svg]:h-4 [&>svg]:w-4"></span> Fill my profile
          </button>
        </div>
        <div class="wa-card !p-lg sm:!p-xl">@include('site.profile.personal')</div>
      </div>
    </template>

    {{-- 2 · Your education --}}
    <template x-if="STEPS[step].key === 'education'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">
        <div x-show="profEducations.length" class="space-y-sm">
          <template x-for="e in profEducations" :key="e.id">
            <div class="flex items-center gap-md rounded-field bg-primary-light py-sm pl-md pr-xs" :class="pf.edu.id === e.id && 'ring-1 ring-primary'">
              <span x-html="ICONS.graduation" class="text-primary-dark [&>svg]:h-5 [&>svg]:w-5"></span>
              <button type="button" @click="profEduEdit(e)" class="min-w-0 flex-1 text-left">
                <span class="block text-bodysm font-bold text-ink" x-text="e.qualification"></span>
                <span class="block text-caption text-ink-secondary" x-text="[e.institute, e.year].filter(Boolean).join(' · ') || 'Tap to edit'"></span>
              </button>
              <button type="button" @click="profEduRemove(e, false)" :disabled="profBusy" aria-label="Remove" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-primary hover:bg-surface"><span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
            </div>
          </template>
          <p class="mb-md text-caption text-ink-muted">Tap a saved degree to edit it.</p>
        </div>
        <div :class="profEducations.length && 'mt-lg'">@include('site.profile.education-form')</div>
        <button type="button" @click="addDegree()" :disabled="profBusy" class="wa-btn-ghost mt-lg w-full"
                x-text="pf.edu.id ? 'Save this degree' : '+ Add another degree'"></button>
      </div>
    </template>

    {{-- 3 · Your experience — Total experience, saved roles, the role form,
         "+ Add another experience", then team management (the app's order). --}}
    <template x-if="STEPS[step].key === 'experience'">
      <div class="wa-card mt-lg space-y-lg !p-lg sm:!p-xl">
        <div>
          <span class="wa-label">Total experience @include('site.profile.gate-mark', ['field' => 'experience'])</span>
          @include('site.ui.chips', ['model' => 'pf.experience', 'items' => "opt('experience_bands')"])
        </div>

        <template x-if="profExperiences.length">
          <div>
            <div class="space-y-sm">
              <template x-for="x in profExperiences" :key="x.id">
                <div class="flex items-center gap-md rounded-field bg-primary-light py-sm pl-md pr-xs" :class="pf.exp.id === x.id && 'ring-1 ring-primary'">
                  <span x-html="ICONS.checkCircle" class="text-primary-dark [&>svg]:h-5 [&>svg]:w-5"></span>
                  <button type="button" @click="profExpEdit(x)" class="min-w-0 flex-1 text-left">
                    <span class="block text-bodysm font-bold text-ink" x-text="x.designation"></span>
                    <span class="block text-caption text-ink-secondary" x-text="[x.organization, profPeriod(x), profDuration(x)].filter(Boolean).join(' · ')"></span>
                  </button>
                  <button type="button" @click="profExpRemove(x, false)" :disabled="profBusy" aria-label="Remove" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-primary hover:bg-surface"><span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
                </div>
              </template>
            </div>
            <p class="mt-sm text-caption text-ink-muted">Tap a saved role to edit it.</p>
          </div>
        </template>

        @include('site.profile.experience-form')

        <button type="button" @click="addRole()" :disabled="profBusy" class="wa-btn-ghost w-full"
                x-text="pf.exp.id ? 'Save this role' : '+ Add another experience'"></button>

        <div class="pt-sm">@include('site.profile.team')</div>
      </div>
    </template>

    {{-- 4 · Languages --}}
    <template x-if="STEPS[step].key === 'languages'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.languages')</div>
    </template>

    {{-- 5 · What are you looking for? --}}
    <template x-if="STEPS[step].key === 'preferences'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.preferences')</div>
    </template>

    {{-- 6 · Your availability --}}
    <template x-if="STEPS[step].key === 'availability'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.availability')</div>
    </template>

    {{-- 7 · Skills & interests --}}
    <template x-if="STEPS[step].key === 'skills'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.skills')</div>
    </template>

    {{-- 8 · A line about you --}}
    <template x-if="STEPS[step].key === 'about'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.about')</div>
    </template>

    {{-- 9 · Your photo --}}
    <template x-if="STEPS[step].key === 'photo'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.photo', ['label' => false])</div>
    </template>

    {{-- 10 · Your intro video --}}
    <template x-if="STEPS[step].key === 'video'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.video', ['heading' => false])</div>
    </template>

    {{-- ── Footer: Back · Skip · Save & continue (the app's bottom bar) ─ --}}
    <div class="sticky bottom-0 z-20 -mx-page mt-lg border-t border-hairline-divider bg-canvas-alt/95 px-page py-md backdrop-blur lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0">
      {{-- A grey button alone says "no" without saying why; this names the field. --}}
      <p x-show="gateMissingOn(STEPS[step].key).length" x-cloak class="mb-xs text-caption text-danger"
         x-text="'To continue, fill in: ' + gateMissingOn(STEPS[step].key).join(', ')"></p>
      <div class="flex items-center gap-sm">
        <button type="button" x-show="step > 0 || isGated" @click="prev()" :disabled="busy" aria-label="Back" class="wa-btn-quiet h-[54px] w-[54px] border-hair border-hairline !px-0">
          <span x-html="ICONS.arrowLeft" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
        <button type="button" x-show="!answered(STEPS[step].key) && !stepRequired(STEPS[step].key)" @click="skip()" :disabled="busy" class="wa-btn-quiet h-[54px]"
                x-text="step === STEPS.length - 1 ? (isGated ? 'Skip & apply' : 'Skip & finish') : 'Skip'"></button>
        <button type="button" @click="next()" :disabled="busy || !stepSatisfied(STEPS[step].key)" class="wa-btn flex-[2]" data-setup-next-step>
          <span x-show="busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
          <span x-text="step === STEPS.length - 1 ? (isGated ? 'Save & apply' : 'Finish') : 'Save & continue'"></span>
        </button>
      </div>
    </div>
  </div>
</div>
@endsection
