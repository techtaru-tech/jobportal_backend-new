{{-- Applicant Profile (recruiter/applicant_profile/views/applicant_profile_view.dart). --}}
@php
  $kv = 'flex gap-md border-b border-hairline-divider py-[10px] last:border-0';
  $kvLabel = 'flex-1 text-bodysm text-ink-secondary';
  $kvValue = 'flex-1 break-words text-right text-bodysm font-semibold text-ink';
  $sheetItem = 'flex w-full items-center gap-md rounded-field px-md py-md text-left text-bodysm font-semibold text-ink hover:bg-surface-muted';
  $sheetIcon = 'text-ink-secondary [&>svg]:h-5 [&>svg]:w-5';
@endphp
<template x-if="is('rApplicant')">
  <div class="wa-narrow pb-[96px]">
    <div class="mb-lg flex items-center gap-md">
      <button type="button" @click="back(rApplicantData ? '/r/applicants/' + encodeURIComponent(rApplicantData.job_id) : '/r/jobs')" aria-label="Back"
              class="-ml-sm inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
      </button>
      <h1 class="text-h3 text-ink">Applicant Profile</h1>
    </div>

    <template x-if="rApplicantLoading && !rAp">
      <div>@include('webapp.ui.spinner')</div>
    </template>
    <template x-if="!rApplicantLoading && !rAp">
      <div>@include('webapp.ui.empty', ['icon' => 'user', 'title' => 'Applicant not available', 'text' => 'We could not load this application. Pull back and try again.'])</div>
    </template>

    <template x-if="rAp">
      <div class="space-y-xl">
        {{-- header --}}
        <div class="flex items-start gap-lg">
          <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-[16px] bg-primary-light text-h2 text-primary-dark">
            <template x-if="rApP.photo_url"><img :src="rApP.photo_url" alt="" class="h-full w-full object-cover"></template>
            <template x-if="!rApP.photo_url"><span x-text="initials(rApName(rAp))"></span></template>
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-h2 text-ink" x-text="rApName(rAp)"></p>
            <p class="text-bodysm text-ink-secondary" x-text="rApDesignation(rAp)"></p>
            <p class="mt-xs flex items-center gap-[3px] text-caption text-ink-secondary">
              <span x-html="ICONS.mapPin" class="text-ink-muted [&>svg]:h-[14px] [&>svg]:w-[14px]"></span><span x-text="rApLocation(rAp)"></span>
            </p>
            <p class="mt-sm flex flex-wrap items-center gap-x-sm text-bodysm">
              <span class="inline-flex items-center gap-[6px] font-bold text-ink">
                <span class="h-[7px] w-[7px] rounded-full" :class="rAppDot(rAp.status)"></span><span x-text="rAppLabel(rAp.status)"></span>
              </span>
              <span class="text-caption text-ink-muted" x-text="'· Applied ' + rDay(rAp.applied_at)"></span>
            </p>
          </div>
        </div>

        <section>
          <p class="wa-section-title mb-sm">Candidate summary</p>
          <div class="wa-card">
            <ul class="list-disc space-y-xs pl-lg text-bodysm text-ink marker:text-ink-muted">
              <li x-text="(rApP.experience || '—') + ' experience'"></li>
              <li x-text="rApP.qualification || '—'"></li>
              <li x-text="rApLocation(rAp) || '—'"></li>
              <li x-text="(rApP.preferred_shifts || []).length ? 'Available for ' + rApP.preferred_shifts.join(', ') + ' shift' : 'Shift preference not set'"></li>
            </ul>
          </div>
        </section>

        <section>
          <p class="wa-section-title mb-sm">Profile strength</p>
          <div class="wa-card">
            <p class="flex items-baseline gap-sm">
              <span class="text-[28px] font-bold text-primary" x-text="rApStrength(rAp) + '%'"></span>
              <span class="text-bodysm text-ink" x-text="rStrengthLabel(rApStrength(rAp))"></span>
            </p>
            <span class="mt-sm block h-[7px] overflow-hidden rounded-[4px] bg-hairline"><span class="block h-full bg-primary" :style="'width:' + rApStrength(rAp) + '%'"></span></span>
          </div>
        </section>

        <section>
          <p class="wa-section-title mb-sm">Professional information</p>
          <div class="wa-card !py-xs">
            <template x-for="row in [['Current Role', rApDesignation(rAp)], ['Total Experience', rApP.experience],
                ['Current Company', rApCurrentRole(rAp)?.organization], ['Department', rApCurrentRole(rAp)?.department],
                ['Preferred Location', (rApP.location || []).join(', ')], ['Preferred Shift', (rApP.preferred_shifts || []).join(', ')]]" :key="row[0]">
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}" x-text="row[0]"></span><span class="{{ $kvValue }}" x-text="row[1] || '—'"></span></div>
            </template>
          </div>
        </section>

        <template x-if="(rApP.experiences || []).length">
          <section>
            <p class="wa-section-title mb-sm">Work experience</p>
            <div class="space-y-sm">
              <template x-for="e in rApP.experiences" :key="e.id || e.designation + e.organization">
                <div class="wa-card !p-md">
                  <p class="text-h5 text-ink" x-text="e.designation"></p>
                  <p class="text-bodysm text-ink-secondary" x-text="e.organization"></p>
                  <p class="text-caption text-ink-muted" x-text="[e.period, e.department, (e.cities || []).join(', ')].filter(Boolean).join(' · ')"></p>
                  <p x-show="e.description" class="mt-sm text-bodysm text-ink" x-text="e.description"></p>
                </div>
              </template>
            </div>
          </section>
        </template>

        <template x-if="(rApP.educations || []).length">
          <section>
            <p class="wa-section-title mb-sm">Education</p>
            <div class="space-y-sm">
              <template x-for="e in rApP.educations" :key="e.id || e.qualification + e.year">
                <div class="wa-card !p-md">
                  <p class="text-h5 text-ink" x-text="e.specialization ? e.qualification + ' · ' + e.specialization : e.qualification"></p>
                  <p class="text-caption text-ink-muted" x-text="[e.institute, e.year].filter(Boolean).join(' · ')"></p>
                </div>
              </template>
            </div>
          </section>
        </template>

        <section>
          <p class="wa-section-title mb-sm">Languages</p>
          <div class="wa-card space-y-sm">
            <template x-for="l in (rApP.languages || [])" :key="l">
              <div class="flex justify-between gap-md text-bodysm">
                <span class="font-semibold text-ink" x-text="l"></span>
                <span class="text-caption text-ink-secondary" x-text="(rApP.language_levels || {})[l] || ''"></span>
              </div>
            </template>
            <p x-show="!(rApP.languages || []).length" class="text-bodysm text-ink-muted">—</p>
          </div>
        </section>

        <section>
          <p class="wa-section-title mb-sm">Job preferences</p>
          <div class="wa-card">
            <p class="wa-label">Preferred Roles</p>
            <div class="flex flex-wrap gap-xs"><template x-for="r in (rApP.preferred_roles || [])" :key="r"><span class="wa-tag" x-text="r"></span></template></div>
            <p class="wa-label mt-md">Preferred Locations</p>
            <div class="flex flex-wrap gap-xs"><template x-for="r in (rApP.location || [])" :key="r"><span class="wa-tag" x-text="r"></span></template></div>
            <template x-if="(rApP.preferred_job_types || []).length">
              <div>
                <p class="wa-label mt-md">Preferred Job Types</p>
                <div class="flex flex-wrap gap-xs"><template x-for="r in rApP.preferred_job_types" :key="r"><span class="wa-tag" x-text="r"></span></template></div>
              </div>
            </template>
            <div class="mt-md">
              <div class="flex gap-md py-[5px]"><span class="{{ $kvLabel }}">Expected Salary</span><span class="{{ $kvValue }}" x-text="rApP.expected_salary || '—'"></span></div>
              <div class="flex gap-md py-[5px]"><span class="{{ $kvLabel }}">Preferred Shift</span><span class="{{ $kvValue }}" x-text="(rApP.preferred_shifts || []).join(', ') || '—'"></span></div>
            </div>
          </div>
        </section>

        <template x-if="rApAbout">
          <section>
            <p class="wa-section-title mb-sm" x-text="rApLaterAbout ? 'About candidate · added after applying' : 'About candidate'"></p>
            <p class="whitespace-pre-line text-body text-ink" :class="!rAboutOpen && 'line-clamp-3'" x-text="rApAbout"></p>
            <button type="button" x-show="rApAbout.length > 140" class="wa-btn-quiet -ml-md text-primary" @click="rAboutOpen = !rAboutOpen" x-text="rAboutOpen ? 'Read less' : 'Read more'"></button>
          </section>
        </template>

        <section>
          <p class="wa-section-title mb-sm">Resume</p>
          <div class="wa-card flex items-center gap-md">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-primary-light text-primary">@include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-5 w-5'])</span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-h5 text-ink" x-text="rApP.resume || 'Resume'"></p>
              <p class="text-caption text-ink-secondary" x-text="'Uploaded ' + rDay(rAp.applied_at)"></p>
            </div>
            <button type="button" @click="rViewResume()" :disabled="rResumeBusy" aria-label="View resume" title="View resume" class="inline-flex h-10 w-10 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted">
              @include('admin.partials.icon', ['name' => 'eye', 'class' => 'h-5 w-5'])
            </button>
            <button type="button" @click="rDownloadResume()" :disabled="rResumeBusy" aria-label="Download resume" title="Download resume" class="inline-flex h-10 w-10 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted">
              <span x-html="rResumeBusy ? ICONS.loader : ICONS.download" :class="rResumeBusy && 'animate-spin'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
            </button>
          </div>
        </section>

        <template x-if="rApHasVideo || rApLaterVideo">
          <section>
            <p class="wa-section-title">Intro video</p>
            <p class="mb-sm mt-[2px] text-caption text-ink-secondary" x-text="rApLaterVideo ? 'A 1-minute self-introduction, recorded after this application.' : 'A 1-minute self-introduction recorded by the candidate.'"></p>
            <template x-if="rIntroUrl">
              <video :src="rIntroUrl" controls autoplay playsinline class="aspect-[9/16] max-h-[480px] w-full rounded-card bg-surface-dark object-contain"></video>
            </template>
            <template x-if="!rIntroUrl">
              <button type="button" @click="rPlayIntro()" class="wa-card flex w-full items-center gap-md !p-md text-left hover:shadow-raised">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-primary-light text-primary" x-html="rIntroBusy ? ICONS.loader : ICONS.play" :class="rIntroBusy && '[&>svg]:animate-spin'"></span>
                <span><span class="block text-h5 text-ink">Watch intro video</span><span class="block text-caption text-ink-secondary">Tap to play</span></span>
              </button>
            </template>
          </section>
        </template>

        <section>
          <p class="wa-section-title mb-sm">Application details</p>
          <div class="wa-card !py-xs">
            <template x-for="row in [['Applied For', rApplicantJob?.title || '—'], ['Application Date', rDay(rAp.applied_at)],
                ['Application Status', rAppLabel(rAp.status)], ['Submitted Resume', rApP.resume]]" :key="row[0]">
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}" x-text="row[0]"></span><span class="{{ $kvValue }}" x-text="row[1] || '—'"></span></div>
            </template>
          </div>
          <p class="mt-xl text-caption text-ink-secondary">Everything above is frozen at the time of application — later profile changes do not affect it.</p>
        </section>

        <template x-if="rAp.interview">
          <section>
            <p class="wa-section-title mb-sm">Interview</p>
            <div class="wa-card !py-xs">
              <template x-for="row in [['Date', /^[0-9]{4}-[0-9]{2}-[0-9]{2}/.test(rAp.interview.date || '') ? rDay(rAp.interview.date) : rAp.interview.date], ['Time', rAp.interview.time], ['Type', rAp.interview.type === 'online' ? 'Online' : 'In person'],
                  ['Location / Link', rAp.interview.location_or_link], ...(rAp.interview.notes ? [['Notes', rAp.interview.notes]] : [])]" :key="row[0]">
                <div class="{{ $kv }}"><span class="{{ $kvLabel }}" x-text="row[0]"></span><span class="{{ $kvValue }}" x-text="row[1] || '—'"></span></div>
              </template>
            </div>
          </section>
        </template>
      </div>
    </template>

    {{-- Sticky action bar: the next real decision, plus More. --}}
    <template x-if="rAp">
      <div class="fixed inset-x-0 bottom-[calc(57px+env(safe-area-inset-bottom))] z-30 border-t border-hairline-divider bg-canvas/95 backdrop-blur-md lg:bottom-0">
        <div class="mx-auto flex max-w-[720px] gap-sm px-page py-sm">
          <div class="flex-[2]">
            <template x-if="rAp.status === 'applied'">
              <button type="button" class="wa-btn w-full" :disabled="rStatusBusy" @click="rShortlist()">Shortlist</button>
            </template>
            <template x-if="rAp.status === 'shortlisted'">
              <button type="button" class="wa-btn w-full" :disabled="rStatusBusy" @click="rAp.interview ? rMarkSelected() : rOpenInterview()" x-text="rAp.interview ? 'Mark as Selected' : 'Schedule Interview'"></button>
            </template>
            <template x-if="rAp.status === 'selected'">
              <button type="button" class="wa-btn w-full" disabled>Selected ✓</button>
            </template>
            <template x-if="rAp.status === 'rejected'">
              <button type="button" class="wa-btn-ghost w-full" :disabled="rStatusBusy" @click="rReopen()">Reopen Application</button>
            </template>
          </div>
          <button type="button" class="wa-btn-ghost flex-1" @click="rMoreOpen = true">More</button>
        </div>
      </div>
    </template>

    {{-- More --}}
    <div x-show="rMoreOpen" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
      <div @click.outside="rMoreOpen = false" class="w-full max-w-[440px] rounded-t-sheet bg-surface px-sm pb-lg pt-md shadow-raised sm:rounded-dialog">
        <button type="button" class="{{ $sheetItem }}" @click="rCall()" :disabled="!rApP.phone"><span x-html="ICONS.phone" class="{{ $sheetIcon }}"></span>Call</button>
        <button type="button" class="{{ $sheetItem }}" @click="rEmail()" :disabled="!rApP.email"><span x-html="ICONS.mail" class="{{ $sheetIcon }}"></span>Email</button>
        <button type="button" class="{{ $sheetItem }}" @click="rMessage()"><span x-html="ICONS.message" class="{{ $sheetIcon }}"></span>Message</button>
        <button type="button" class="{{ $sheetItem }}" @click="rDownloadResume()"><span x-html="ICONS.download" class="{{ $sheetIcon }}"></span>Download Resume</button>
        <button type="button" class="{{ $sheetItem }}" @click="rMoreOpen = false; rStatusSheet = true"><span x-html="ICONS.refresh" class="{{ $sheetIcon }}"></span>Change Status</button>
        <template x-if="rAp && rAp.status !== 'rejected'">
          <button type="button" class="{{ $sheetItem }} text-danger" @click="rConfirmReject()"><span x-html="ICONS.xCircle" class="text-danger [&>svg]:h-5 [&>svg]:w-5"></span>Reject Candidate</button>
        </template>
      </div>
    </div>

    {{-- Change status --}}
    <div x-show="rStatusSheet" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
      <div @click.outside="rStatusSheet = false" class="w-full max-w-[440px] rounded-t-sheet bg-surface px-sm pb-lg pt-lg shadow-raised sm:rounded-dialog">
        <h3 class="px-md text-h4 text-ink">Change status</h3>
        <div class="mt-sm">
          <template x-for="s in R_APP_STATUSES" :key="s">
            <button type="button" class="{{ $sheetItem }}" @click="rChangeStatus(s)">
              <span class="h-2 w-2 rounded-full" :class="rAppDot(s)"></span>
              <span class="flex-1" x-text="rAppLabel(s)"></span>
              <span x-show="rAp && rAp.status === s" x-html="ICONS.check" class="text-primary [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            </button>
          </template>
        </div>
      </div>
    </div>

    {{-- Schedule Interview --}}
    <div x-show="rInterview.open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
      <div @click.outside="rInterview.open = false" class="max-h-[92vh] w-full max-w-[480px] overflow-y-auto rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog">
        <h3 class="text-h2 text-ink">Schedule Interview</h3>
        <p class="mt-[2px] text-bodysm text-ink-secondary" x-text="'with ' + (rAp ? rApName(rAp) : '')"></p>
        <div class="mt-lg space-y-lg">
          @include('webapp.ui.input', ['label' => 'Date', 'model' => 'rInterview.date', 'type' => 'date', 'attrs' => ':min="rInterviewMinDate" :max="rInterviewMaxDate"'])
          @include('webapp.ui.input', ['label' => 'Time', 'model' => 'rInterview.time', 'type' => 'time'])
          <div>
            <p class="wa-section-title mb-sm">Interview type</p>
            <div class="flex gap-sm">
              <button type="button" class="wa-chip" :class="rInterview.type === 'online' && 'wa-chip-on'" @click="rInterview.type = 'online'">Online</button>
              <button type="button" class="wa-chip" :class="rInterview.type === 'inPerson' && 'wa-chip-on'" @click="rInterview.type = 'inPerson'">In Person</button>
            </div>
          </div>
          <label class="block">
            <span class="wa-label" x-text="rInterview.type === 'online' ? 'Meeting link' : 'Location'"></span>
            <input type="text" x-model="rInterview.location_or_link" :placeholder="rInterview.type === 'online' ? 'https://meet…' : 'Ward office, 2nd floor'" class="wa-input" maxlength="255">
          </label>
          @include('webapp.ui.textarea', ['label' => 'Notes (optional)', 'model' => 'rInterview.notes', 'rows' => 3, 'placeholder' => 'Anything the candidate should know…'])
        </div>
        <button type="button" class="wa-btn mt-xl w-full" :disabled="!rInterviewReady || rInterview.busy" @click="rScheduleInterview()">Schedule Interview</button>
        <button type="button" class="mt-sm h-11 w-full rounded-button text-btnghost text-ink-secondary hover:bg-surface-muted" @click="rInterview.open = false">Cancel</button>
      </div>
    </div>
  </div>
</template>
