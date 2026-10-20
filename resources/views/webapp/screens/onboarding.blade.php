{{-- Create profile — every profile section, one step at a time (the app's
     OnboardingView). No app chrome: its own top bar with the step bar, and a
     footer with Back / Skip / Save & continue.

     Reached from Apply (`?job=…&missing=…`) it is gated: the steps that hold
     what the job is waiting for cannot be skipped, "Do it later" becomes a
     close (abandoning the application), and the end goes back to Apply. --}}
<template x-if="is('onboarding')">
  <div class="flex min-h-screen flex-col bg-canvas">

    <header class="sticky top-0 z-30 border-b border-hairline-divider bg-canvas/95 backdrop-blur">
      <div class="mx-auto w-full max-w-[720px] px-page pb-md pt-md">
        <div class="flex items-center gap-md">
          <a :href="'#' + (onbGated ? '/job/' + onbJob : '/home')" class="shrink-0" title="Inthes">@include('admin.partials.logo', ['size' => 28])</a>
          <p class="flex-1 text-kicker uppercase text-ink-secondary" x-text="onbGated ? 'Finish to apply' : 'Create your profile'"></p>
          <template x-if="!onbGated">
            <button type="button" @click="onbLater()" :disabled="onbBusy" class="wa-btn-quiet !h-8 text-primary">Do it later</button>
          </template>
          <template x-if="onbGated">
            <button type="button" @click="onbAbandon()" title="Not now" aria-label="Not now" class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted">
              <span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
            </button>
          </template>
        </div>

        <template x-if="onbGated">
          <div class="mt-sm rounded-field bg-primary-light px-md py-sm">
            <p class="text-bodysm font-semibold text-primary-dark">
              Complete these details to apply<span x-show="onbJobTitle"> for <span x-text="onbJobTitle"></span></span>
            </p>
            <p class="text-caption font-semibold text-primary" x-text="onbGateSummary"></p>
          </div>
        </template>

        {{-- One segment per step: filled when answered, so the bar doubles as
             "what did I leave behind". Backwards only. --}}
        <div class="mt-sm flex gap-[3px]">
          <template x-for="(s, i) in ONB_STEPS" :key="s">
            <button type="button" @click="onbGoTo(i)" :disabled="i >= onbStep" :title="onbTitle(s)"
                    :class="onbHasAnswer(s) ? 'bg-primary' : (i === onbStep ? 'bg-primary/45' : 'bg-hairline')"
                    class="h-1 flex-1 rounded-[2px] enabled:cursor-pointer disabled:cursor-default"></button>
          </template>
        </div>
        <p class="mt-xs text-caption text-ink-secondary" x-text="'Step ' + (onbStep + 1) + ' of ' + ONB_STEPS.length + ' · ' + onbAnsweredCount + ' answered'"></p>
      </div>
    </header>

    <main class="mx-auto w-full max-w-[720px] flex-1 px-page pb-xxl pt-lg">
      <h1 class="text-h2 text-ink" x-text="onbTitle(onbCurrent)"></h1>
      <p class="mt-xs text-bodysm leading-relaxed text-ink-secondary" x-text="onbSubtitle(onbCurrent)"></p>
      <template x-if="onbIsRequired(onbCurrent)">
        <span class="mt-sm inline-block rounded-field bg-primary-light px-sm py-[4px] text-caption font-semibold text-primary-dark">Required for this job</span>
      </template>

      <div class="mt-xl">
        <template x-if="onbCurrent === 'personal'">
          <div>
            {{-- "Describe yourself in one line" --}}
            <div class="rounded-card border-hair border-primary/35 bg-primary-light p-md">
              <p class="flex items-center gap-xs text-bodysm font-bold text-primary-dark">
                <span x-html="ICONS.sparkles" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span> Describe yourself in one line
              </p>
              <p class="mt-[2px] text-caption text-ink-secondary">e.g. Staff nurse, 4 years ICU experience in Jaipur</p>
              <input type="text" x-model="onbQuick" @keydown.enter.prevent="onbQuickFill()" placeholder="Type here…" class="wa-input mt-sm bg-surface">
              <button type="button" @click="onbQuickFill()" class="wa-btn-ghost-sm mt-sm w-full">
                <span x-html="ICONS.sparkles" class="[&>svg]:h-4 [&>svg]:w-4"></span> Fill my profile
              </button>
            </div>
            <div class="mt-lg">@include('webapp.screens.profile.personal')</div>
          </div>
        </template>

        <template x-if="onbCurrent === 'education'">
          <div>
            <div class="space-y-sm">
              <template x-for="e in profEducations" :key="e.id">
                <div :class="pf.edu.id === e.id ? 'border-primary/90 border-[1.5px]' : 'border-primary/35 border-hair'"
                     class="flex cursor-pointer items-center gap-md rounded-card bg-primary-light py-md pl-md pr-xs" @click="profEduEdit(e)">
                  <span x-html="ICONS.checkCircle" class="text-primary-dark [&>svg]:h-5 [&>svg]:w-5"></span>
                  <div class="min-w-0 flex-1">
                    <p class="text-bodysm font-bold text-ink" x-text="e.qualification"></p>
                    <p class="text-caption text-ink-secondary" x-text="[e.institute, e.year].filter(Boolean).join(' · ')"></p>
                  </div>
                  <button type="button" @click.stop="profEduRemove(e, false)" title="Remove" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-primary hover:bg-surface"><span x-html="ICONS.trash" class="[&>svg]:h-5 [&>svg]:w-5"></span></button>
                </div>
              </template>
            </div>
            <p x-show="(me?.educations || []).length" class="mb-md mt-sm text-caption text-ink-secondary">Tap a saved degree to edit it.</p>
            @include('webapp.screens.profile.education-form')
            <button type="button" @click="onbAddDegree()" :disabled="profBusy" class="wa-btn-ghost-sm mt-lg w-full" x-text="pf.edu.id ? 'Save this degree' : '+ Add another degree'"></button>
          </div>
        </template>

        <template x-if="onbCurrent === 'experience'">
          <div>
            @include('webapp.ui.chips', ['label' => 'Total experience', 'model' => 'pf.experience', 'items' => "opt('experience_bands')"])
            <div class="mt-lg space-y-sm">
              <template x-for="x in profExperiences" :key="x.id">
                <div :class="pf.exp.id === x.id ? 'border-primary/90 border-[1.5px]' : 'border-primary/35 border-hair'"
                     class="flex cursor-pointer items-center gap-md rounded-card bg-primary-light py-md pl-md pr-xs" @click="profExpEdit(x)">
                  <span x-html="ICONS.checkCircle" class="text-primary-dark [&>svg]:h-5 [&>svg]:w-5"></span>
                  <div class="min-w-0 flex-1">
                    <p class="text-bodysm font-bold text-ink" x-text="x.designation"></p>
                    <p class="text-caption text-ink-secondary" x-text="[x.organization, profPeriod(x), profDuration(x)].filter(Boolean).join(' · ')"></p>
                  </div>
                  <button type="button" @click.stop="profExpRemove(x, false)" title="Remove" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-primary hover:bg-surface"><span x-html="ICONS.trash" class="[&>svg]:h-5 [&>svg]:w-5"></span></button>
                </div>
              </template>
            </div>
            <p x-show="(me?.experiences || []).length" class="mb-md mt-sm text-caption text-ink-secondary">Tap a saved role to edit it.</p>
            <div class="mt-lg">@include('webapp.screens.profile.experience-form')</div>
            <button type="button" @click="onbAddRole()" :disabled="profBusy" class="wa-btn-ghost-sm mt-lg w-full" x-text="pf.exp.id ? 'Save this role' : '+ Add another experience'"></button>
            <div class="mt-xl">@include('webapp.screens.profile.team')</div>
          </div>
        </template>

        <template x-if="onbCurrent === 'languages'"><div>@include('webapp.screens.profile.languages')</div></template>
        <template x-if="onbCurrent === 'preferences'"><div>@include('webapp.screens.profile.preferences')</div></template>
        <template x-if="onbCurrent === 'availability'"><div>@include('webapp.screens.profile.availability')</div></template>
        <template x-if="onbCurrent === 'skills'"><div>@include('webapp.screens.profile.skills')</div></template>
        <template x-if="onbCurrent === 'about'"><div>@include('webapp.screens.profile.about')</div></template>
        <template x-if="onbCurrent === 'photo'"><div>@include('webapp.screens.profile.photo', ['label' => false])</div></template>
        <template x-if="onbCurrent === 'introVideo'"><div>@include('webapp.screens.profile.video', ['heading' => false])</div></template>
      </div>
    </main>

    <footer class="sticky bottom-0 z-30 border-t border-hairline-divider bg-canvas/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
      <div class="mx-auto flex w-full max-w-[720px] items-end gap-sm px-page pb-lg pt-md">
        {{-- Back on every step but the first; in an apply run, the first goes back to the job. --}}
        <template x-if="onbStep > 0 || onbGated">
          <button type="button" @click="onbPrev()" :disabled="onbBusy" aria-label="Back"
                  class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-button border-hair border-hairline-strong text-ink hover:bg-surface-muted">
            <span x-html="ICONS.arrowLeft" class="[&>svg]:h-5 [&>svg]:w-5"></span>
          </button>
        </template>

        {{-- A step this job needs: no Skip, and Continue names what is missing. --}}
        <template x-if="onbIsRequired(onbCurrent)">
          <div class="flex-1">
            <p x-show="!onbSatisfied(onbCurrent)" class="mb-xs text-caption text-danger" x-text="'To continue, fill in: ' + onbMissingOn(onbCurrent).join(', ')"></p>
            <button type="button" @click="onbNext()" :disabled="onbBusy || !onbSatisfied(onbCurrent)" class="wa-btn w-full">
              <span x-show="onbBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
              <span x-text="onbIsLast ? 'Save & apply' : 'Save & continue'"></span>
            </button>
          </div>
        </template>

        <template x-if="!onbIsRequired(onbCurrent)">
          <div class="flex flex-1 gap-sm">
            {{-- Skip only while unanswered — once there is something to save,
                 "Skip" would mean "throw this away". --}}
            <template x-if="!onbHasAnswer(onbCurrent)">
              <button type="button" @click="onbSkip()" :disabled="onbBusy"
                      class="inline-flex h-[50px] flex-1 items-center justify-center rounded-button border-hair border-hairline-strong text-btn font-semibold text-ink-secondary hover:bg-surface-muted"
                      x-text="onbIsLast ? (onbGated ? 'Skip & apply' : 'Skip & finish') : 'Skip'"></button>
            </template>
            <button type="button" @click="onbNext()" :disabled="onbBusy" :class="onbHasAnswer(onbCurrent) ? 'flex-1' : 'flex-[2]'" class="wa-btn">
              <span x-show="onbBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
              <span x-text="onbIsLast ? (onbGated ? 'Save & apply' : 'Finish') : 'Save & continue'"></span>
            </button>
          </div>
        </template>
      </div>
    </footer>
  </div>
</template>
