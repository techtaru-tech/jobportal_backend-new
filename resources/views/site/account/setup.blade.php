{{--
  /account/setup — "Create your profile", the app's own 10-step wizard
  (OnboardingView): one section per step, in the app's order, with its
  titles, the 10-segment bar, "Step N of 10 · N answered", "Do it later",
  and Save & continue / Skip on every step.

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
    <div x-show="!done" class="sticky top-0 z-20 -mx-page bg-canvas-alt/95 px-page pb-md pt-sm backdrop-blur lg:static lg:mx-0 lg:bg-transparent lg:px-0">
      <div class="flex items-center justify-between">
        <p class="text-kicker uppercase tracking-[1.2px] text-ink-secondary" x-text="nextIsApply ? 'Finish to apply' : 'Create your profile'"></p>
        <button type="button" @click="later()" :disabled="busy" class="text-btn font-semibold text-primary hover:underline" data-setup-later>Do it later</button>
      </div>
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

    {{-- ── Step title ─────────────────────────────────────────────────── --}}
    <div x-show="!done" class="mt-lg flex items-start gap-md">
      <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-card bg-primary-light text-primary">
        <span x-html="ICONS[STEPS[step].icon]" class="[&>svg]:h-6 [&>svg]:w-6"></span>
      </span>
      <div class="min-w-0">
        <h1 class="text-h1 text-ink" x-text="STEPS[step].title"></h1>
        <p class="mt-xs text-bodysm text-ink-secondary" x-text="STEPS[step].subtitle"></p>
      </div>
    </div>

    {{-- 1 · About you --}}
    <template x-if="!done && STEPS[step].key === 'personal'">
      <div class="mt-lg space-y-lg">
        <div class="rounded-card border-hair border-primary/35 bg-primary-light p-lg">
          <p class="flex items-center gap-xs text-bodysm font-bold text-primary-dark">
            <span x-html="ICONS.sparkles" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span> Describe yourself in one line
          </p>
          <p class="mt-[2px] text-caption text-ink-secondary">e.g. Staff nurse with 4 years ICU experience in Jaipur — we'll fill in what we recognise across the steps.</p>
          <div class="mt-sm flex flex-col gap-sm sm:flex-row">
            <input type="text" x-model="quick" @keydown.enter.prevent="quickFill()" placeholder="Type here…" class="wa-input bg-surface">
            <button type="button" @click="quickFill()" class="wa-btn-ghost shrink-0">
              <span x-html="ICONS.sparkles" class="[&>svg]:h-4 [&>svg]:w-4"></span> Fill my profile
            </button>
          </div>
        </div>
        <div class="wa-card !p-lg sm:!p-xl">@include('site.profile.personal')</div>
      </div>
    </template>

    {{-- 2 · Your education --}}
    <template x-if="!done && STEPS[step].key === 'education'">
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
          <p class="mb-md text-caption text-ink-muted">Saved. Click one to edit it, or add another below.</p>
        </div>
        <div :class="profEducations.length && 'mt-lg'">@include('site.profile.education-form')</div>
        <button type="button" x-show="profEduStarted" @click="addDegree()" :disabled="profBusy" class="wa-btn-ghost-sm mt-lg w-full sm:w-auto"
                x-text="pf.edu.id ? 'Save this degree' : 'Save & add another degree'"></button>
      </div>
    </template>

    {{-- 3 · Your experience --}}
    <template x-if="!done && STEPS[step].key === 'experience'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">
        <div class="grid gap-sm sm:grid-cols-2">
          <template x-for="m in [['experienced', 'I have work experience', 'Add your latest role', 'briefcase'], ['fresher', 'I\'m a fresher', 'No work experience yet', 'graduation']]" :key="m[0]">
            <button type="button" @click="setMode(m[0])" :class="expMode === m[0] ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted'"
                    class="flex items-center gap-md rounded-card border-chip p-md text-left">
              <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="expMode === m[0] ? 'bg-primary text-white' : 'bg-surface-muted text-ink-secondary'">
                <span x-html="ICONS[m[3]]" class="[&>svg]:h-5 [&>svg]:w-5"></span>
              </span>
              <span><span class="block text-bodysm font-semibold text-ink" x-text="m[1]"></span><span class="block text-caption text-ink-secondary" x-text="m[2]"></span></span>
            </button>
          </template>
        </div>

        <template x-if="expMode === 'experienced'">
          <div class="mt-xl space-y-lg">
            <template x-if="!profExperiences.length">
              @include('site.ui.chips', ['label' => 'Total experience', 'model' => 'pf.experience', 'items' => 'experiencedBands'])
            </template>
            <template x-if="profExperiences.length">
              <div>
                <p class="text-bodysm text-ink"><span class="text-ink-secondary">Total experience:</span> <b class="font-semibold" x-text="me?.experience || profTotalExperience"></b> <span class="text-caption text-ink-muted">(from your roles)</span></p>
                <div class="mt-sm space-y-sm">
                  <template x-for="x in profExperiences" :key="x.id">
                    <div class="flex items-center gap-md rounded-field bg-primary-light py-sm pl-md pr-xs" :class="pf.exp.id === x.id && 'ring-1 ring-primary'">
                      <span x-html="ICONS.briefcase" class="text-primary-dark [&>svg]:h-5 [&>svg]:w-5"></span>
                      <button type="button" @click="profExpEdit(x)" class="min-w-0 flex-1 text-left">
                        <span class="block text-bodysm font-bold text-ink" x-text="x.designation"></span>
                        <span class="block text-caption text-ink-secondary" x-text="[x.organization, profPeriod(x), profDuration(x)].filter(Boolean).join(' · ')"></span>
                      </button>
                      <button type="button" @click="profExpRemove(x, false)" :disabled="profBusy" aria-label="Remove" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-primary hover:bg-surface"><span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
                    </div>
                  </template>
                </div>
              </div>
            </template>
            <div class="rounded-card border-hair border-hairline bg-canvas p-lg">
              <p class="mb-lg text-h5 text-ink"><span x-text="pf.exp.id ? 'Edit this role' : (profExperiences.length ? 'Add another role' : 'Your latest role')"></span><span x-show="!pf.exp.id" class="text-[12px] font-normal text-ink-muted"> (Optional)</span></p>
              @include('site.profile.experience-form')
              <button type="button" x-show="profExpStarted" @click="addRole()" :disabled="profBusy" class="wa-btn-ghost-sm mt-lg w-full sm:w-auto"
                      x-text="pf.exp.id ? 'Save this role' : 'Save & add another role'"></button>
            </div>
          </div>
        </template>
      </div>
    </template>

    {{-- 4 · Languages --}}
    <template x-if="!done && STEPS[step].key === 'languages'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.languages')</div>
    </template>

    {{-- 5 · What are you looking for? --}}
    <template x-if="!done && STEPS[step].key === 'preferences'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.preferences')</div>
    </template>

    {{-- 6 · Your availability --}}
    <template x-if="!done && STEPS[step].key === 'availability'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.availability')</div>
    </template>

    {{-- 7 · Skills & interests --}}
    <template x-if="!done && STEPS[step].key === 'skills'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.skills')</div>
    </template>

    {{-- 8 · A line about you --}}
    <template x-if="!done && STEPS[step].key === 'about'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.about')</div>
    </template>

    {{-- 9 · Your photo --}}
    <template x-if="!done && STEPS[step].key === 'photo'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.photo', ['label' => false])</div>
    </template>

    {{-- 10 · Your intro video --}}
    <template x-if="!done && STEPS[step].key === 'video'">
      <div class="wa-card mt-lg !p-lg sm:!p-xl">@include('site.profile.video', ['heading' => false])</div>
    </template>

    {{-- ── Done ───────────────────────────────────────────────────────── --}}
    <template x-if="done">
      <div class="mt-lg space-y-lg">
        <div class="wa-card !p-lg text-center sm:!p-xl">
          <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-success-bg text-success">
            @include('admin.partials.icon', ['name' => 'checkCircle', 'class' => 'h-7 w-7'])
          </span>
          <p class="mt-md text-h3 text-ink"><span x-text="'Profile ' + profStrength + '% complete'"></span></p>
          <p class="mt-xs text-bodysm text-ink-secondary" x-text="profStrengthLabel + ' · ' + profHiringLabel"></p>
          <p class="mt-sm text-caption text-ink-muted" x-show="finishing">Writing your resume from your profile…</p>
          <p class="mt-sm text-caption text-success" x-show="!finishing && me?.resume">Your resume is ready — built from what you entered.</p>
          <div class="mt-xl flex flex-col justify-center gap-sm sm:flex-row">
            <template x-if="nextUrl">
              <button type="button" @click="leave(nextUrl)" class="wa-btn" data-setup-next>
                <span x-show="finishing" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
                <span x-text="nextIsApply ? 'Continue applying' : 'Continue'"></span>
              </button>
            </template>
            <button type="button" x-show="!nextUrl" @click="leave('/jobs?sort=relevant')" class="wa-btn">
              <span x-show="finishing" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> See jobs that match me
            </button>
            <button type="button" @click="leave('/account')" class="wa-btn-ghost">Go to my profile</button>
          </div>
        </div>
      </div>
    </template>

    {{-- ── Footer: Back · Skip · Save & continue (the app's bottom bar) ─ --}}
    <div x-show="!done" class="sticky bottom-0 z-20 -mx-page mt-lg border-t border-hairline-divider bg-canvas-alt/95 px-page py-md backdrop-blur lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0">
      <div class="flex items-center gap-sm">
        <button type="button" x-show="step > 0" @click="prev()" :disabled="busy" aria-label="Back" class="wa-btn-quiet h-[54px] w-[54px] border-hair border-hairline !px-0">
          <span x-html="ICONS.arrowLeft" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
        <button type="button" @click="skip()" :disabled="busy" class="wa-btn-quiet h-[54px]" x-text="step === STEPS.length - 1 ? 'Skip & finish' : 'Skip'"></button>
        <button type="button" @click="next()" :disabled="busy" class="wa-btn ml-auto flex-1 sm:min-w-[220px] sm:flex-none" data-setup-next-step>
          <span x-show="busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
          <span x-text="step === STEPS.length - 1 ? 'Finish' : 'Save & continue'"></span>
        </button>
      </div>
    </div>
  </div>
</div>
@endsection
