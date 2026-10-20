{{--
  /account/setup — "Complete your profile": three short steps on one page.
  Fields are the profile page's own (site/profile/*); see
  js/site/pages/account-setup.js for what each step saves.
--}}
@extends('site.account.layout')

@section('title', 'Complete your profile — Inthes')
@section('account_key', 'profile')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-profile-kit.js') }}?v={{ filemtime(public_path('js/site/pages/account-profile-kit.js')) }}"></script>
<script defer src="{{ asset('js/site/pages/account-setup.js') }}?v={{ filemtime(public_path('js/site/pages/account-setup.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountSetup()" class="mx-auto max-w-[760px]">
  <template x-if="!ready">@include('site.ui.spinner')</template>

  <div x-show="ready" x-cloak>
    {{-- Progress header --}}
    <div class="wa-card !p-lg sm:!p-xl">
      <div class="flex items-start gap-md">
        <div class="min-w-0 flex-1">
          <p class="wa-section-title">Complete your profile</p>
          <h1 class="mt-xs text-h2 text-ink" x-text="done ? 'You\'re all set' : STEPS[step].title"></h1>
          <p class="mt-xs text-bodysm text-ink-secondary" x-text="done ? 'Your profile is ready for employers. Add more any time.' : STEPS[step].subtitle"></p>
        </div>
        <button type="button" x-show="!done" @click="later()" :disabled="busy" class="wa-btn-quiet -mr-sm shrink-0 text-primary">Do this later</button>
      </div>
      <ol class="mt-lg grid grid-cols-3 gap-sm">
        <template x-for="(s, i) in STEPS" :key="i">
          <li>
            <button type="button" @click="goTo(i)" :disabled="i >= step || done" class="w-full text-left enabled:cursor-pointer disabled:cursor-default">
              <span class="block h-[5px] rounded-full" :class="done || i < step ? 'bg-primary' : (i === step ? 'bg-primary/40' : 'bg-hairline')"></span>
              <span class="mt-xs flex items-center gap-xs text-caption" :class="i === step && !done ? 'font-semibold text-ink' : 'text-ink-muted'">
                <span x-show="done || i < step" x-html="ICONS.checkCircle" class="text-success [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
                <span x-show="!(done || i < step)" x-text="(i + 1) + '.'"></span>
                <span class="truncate" x-text="s.title"></span>
              </span>
            </button>
          </li>
        </template>
      </ol>
    </div>

    {{-- ── Step 1: About you ──────────────────────────────────────────── --}}
    <template x-if="!done && step === 0">
      <div class="mt-lg space-y-lg">
        <div class="rounded-card border-hair border-primary/35 bg-primary-light p-lg">
          <p class="flex items-center gap-xs text-bodysm font-bold text-primary-dark">
            <span x-html="ICONS.sparkles" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span> Describe yourself in one line
          </p>
          <p class="mt-[2px] text-caption text-ink-secondary">e.g. Staff nurse, 4 years ICU experience in Jaipur — we'll fill in what we recognise across all three steps.</p>
          <div class="mt-sm flex flex-col gap-sm sm:flex-row">
            <input type="text" x-model="quick" @keydown.enter.prevent="quickFill()" placeholder="Type here…" class="wa-input bg-surface">
            <button type="button" @click="quickFill()" class="wa-btn-ghost shrink-0">
              <span x-html="ICONS.sparkles" class="[&>svg]:h-4 [&>svg]:w-4"></span> Fill my profile
            </button>
          </div>
        </div>
        <div class="wa-card !p-lg sm:!p-xl">
          <div class="space-y-xl">
            <div class="border-b border-hairline-divider pb-xl">@include('site.profile.photo', ['label' => true])</div>
            <div>@include('site.profile.personal', ['essentials' => true])</div>
          </div>
        </div>
      </div>
    </template>

    {{-- ── Step 2: Education & experience ─────────────────────────────── --}}
    <template x-if="!done && step === 1">
      <div class="mt-lg space-y-lg">
        <div class="wa-card !p-lg sm:!p-xl">
          <h2 class="flex items-center gap-sm text-h4 text-ink"><span x-html="ICONS.graduation" class="text-primary [&>svg]:h-5 [&>svg]:w-5"></span> Education</h2>
          <div x-show="profEducations.length" class="mt-md space-y-sm">
            <template x-for="e in profEducations" :key="e.id">
              <div class="flex items-center gap-md rounded-field bg-primary-light py-sm pl-md pr-xs" :class="pf.edu.id === e.id && 'ring-1 ring-primary'">
                <span x-html="ICONS.checkCircle" class="text-primary-dark [&>svg]:h-5 [&>svg]:w-5"></span>
                <button type="button" @click="profEduEdit(e)" class="min-w-0 flex-1 text-left">
                  <span class="block text-bodysm font-bold text-ink" x-text="e.qualification"></span>
                  <span class="block text-caption text-ink-secondary" x-text="[e.institute, e.year].filter(Boolean).join(' · ') || 'Tap to edit'"></span>
                </button>
                <button type="button" @click="profEduRemove(e, false)" :disabled="profBusy" aria-label="Remove" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-primary hover:bg-surface"><span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span></button>
              </div>
            </template>
          </div>
          <p x-show="profEducations.length" class="mb-md mt-xs text-caption text-ink-muted">Saved. Click one to edit it, or add another below.</p>
          <div class="mt-lg">@include('site.profile.education-form')</div>
          <button type="button" x-show="profEduStarted" @click="addDegree()" :disabled="profBusy" class="wa-btn-ghost-sm mt-lg w-full sm:w-auto"
                  x-text="pf.edu.id ? 'Save this degree' : 'Save & add another degree'"></button>
        </div>

        <div class="wa-card !p-lg sm:!p-xl">
          <h2 class="flex items-center gap-sm text-h4 text-ink"><span x-html="ICONS.briefcase" class="text-primary [&>svg]:h-5 [&>svg]:w-5"></span> Experience</h2>
          <div class="mt-md grid gap-sm sm:grid-cols-2">
            <button type="button" @click="setMode('experienced')" :class="expMode === 'experienced' ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted'"
                    class="flex items-center gap-md rounded-card border-chip p-md text-left">
              <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2" :class="expMode === 'experienced' ? 'border-primary' : 'border-ink-muted'"><span x-show="expMode === 'experienced'" class="h-2.5 w-2.5 rounded-full bg-primary"></span></span>
              <span><span class="block text-bodysm font-semibold text-ink">I have work experience</span><span class="block text-caption text-ink-secondary">Add your latest role</span></span>
            </button>
            <button type="button" @click="setMode('fresher')" :class="expMode === 'fresher' ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted'"
                    class="flex items-center gap-md rounded-card border-chip p-md text-left">
              <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2" :class="expMode === 'fresher' ? 'border-primary' : 'border-ink-muted'"><span x-show="expMode === 'fresher'" class="h-2.5 w-2.5 rounded-full bg-primary"></span></span>
              <span><span class="block text-bodysm font-semibold text-ink">I'm a fresher</span><span class="block text-caption text-ink-secondary">No work experience yet</span></span>
            </button>
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
                        <span x-html="ICONS.checkCircle" class="text-primary-dark [&>svg]:h-5 [&>svg]:w-5"></span>
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
                <p class="mb-lg text-h5 text-ink" x-text="pf.exp.id ? 'Edit this role' : (profExperiences.length ? 'Add another role (optional)' : 'Your latest role (optional)')"></p>
                @include('site.profile.experience-form')
                <button type="button" x-show="profExpStarted" @click="addRole()" :disabled="profBusy" class="wa-btn-ghost-sm mt-lg w-full sm:w-auto"
                        x-text="pf.exp.id ? 'Save this role' : 'Save & add another role'"></button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </template>

    {{-- ── Step 3: What you're looking for ────────────────────────────── --}}
    <template x-if="!done && step === 2">
      <div class="mt-lg space-y-lg">
        <div class="wa-card space-y-lg !p-lg sm:!p-xl">
          @include('site.ui.multi-add', ['label' => 'Preferred cities', 'model' => 'pf.location', 'items' => "opt('cities')", 'placeholder' => 'Where do you want to work?'])
          @include('site.ui.multi-add', ['label' => 'Preferred roles', 'model' => 'pf.roles', 'items' => "opt('categories')", 'placeholder' => 'Which roles interest you?'])
          <div class="grid gap-lg md:grid-cols-2">
            @include('site.ui.chips', ['label' => 'Job type', 'model' => 'pf.job_types', 'items' => "opt('job_types')", 'multi' => true])
            @include('site.ui.chips', ['label' => 'Preferred shift', 'model' => 'pf.shifts', 'items' => "opt('shifts')", 'multi' => true])
          </div>
          @include('site.ui.chips', ['label' => 'Expected salary', 'model' => 'pf.salary', 'items' => 'profSalaryOptions'])
        </div>
        <div class="wa-card !p-lg sm:!p-xl">
          @include('site.profile.languages')
        </div>
      </div>
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
            <button type="button" @click="leave('/jobs?sort=relevant')" class="wa-btn">
              <span x-show="finishing" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span> See jobs that match me
            </button>
            <button type="button" @click="leave('/account')" class="wa-btn-ghost">Go to my profile</button>
          </div>
        </div>

        <div class="wa-card !p-lg sm:!p-xl">
          <p class="wa-section-title">Add more later from your profile</p>
          <p class="mt-xs text-caption text-ink-muted">Optional — each one helps recruiters pick you.</p>
          <div class="mt-md grid gap-sm sm:grid-cols-2">
            @foreach ([
              ['availability', 'calendar', 'Availability', 'When you can join, notice period, relocation'],
              ['about', 'sparkles', 'About me & skills', 'A short summary, skills and hobbies'],
              ['video', 'video', '1-minute intro video', 'The fastest way to stand out'],
              ['personal', 'user', 'More personal details', 'Marital status, family, WhatsApp'],
            ] as [$anchor, $icon, $t, $d])
              <a href="{{ url('/account#'.$anchor) }}" class="group flex items-center gap-md rounded-field border-hair border-hairline p-md hover:border-primary">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-field bg-primary-light text-primary">@include('admin.partials.icon', ['name' => $icon, 'class' => 'h-[18px] w-[18px]'])</span>
                <span class="min-w-0 flex-1"><span class="block text-bodysm font-semibold text-ink">{{ $t }}</span><span class="block text-caption text-ink-muted">{{ $d }}</span></span>
                @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'nudge h-4 w-4 text-ink-muted'])
              </a>
            @endforeach
          </div>
        </div>
      </div>
    </template>

    {{-- Footer actions --}}
    <div x-show="!done" class="sticky bottom-0 z-20 -mx-page mt-lg border-t border-hairline-divider bg-canvas-alt/95 px-page py-md backdrop-blur lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0">
      <div class="flex items-center gap-sm">
        <button type="button" x-show="step > 0" @click="prev()" :disabled="busy" class="wa-btn-quiet h-[50px] border-hair border-hairline">
          <span x-html="ICONS.arrowLeft" class="[&>svg]:h-4 [&>svg]:w-4"></span> Back
        </button>
        <button type="button" x-show="step > 0" @click="skip()" :disabled="busy" class="wa-btn-quiet ml-auto h-[50px]">Skip</button>
        <button type="button" @click="next()" :disabled="busy" class="wa-btn flex-1 sm:flex-none sm:min-w-[200px]" :class="step === 0 && 'sm:ml-auto'">
          <span x-show="busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
          <span x-text="step === STEPS.length - 1 ? 'Save & finish' : 'Save & continue'"></span>
        </button>
      </div>
    </div>
  </div>
</div>
@endsection
