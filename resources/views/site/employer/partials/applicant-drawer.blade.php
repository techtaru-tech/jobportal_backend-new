{{-- The applicant drawer on a job's workspace (recruiter/applicant_profile):
     the full profile as frozen at application, resume, intro video, interview,
     and the next decision. A right-side panel on desktop, a full-screen sheet
     on phones. Lives inside empJob(); `?a=<applicationId>` opens it. --}}
@php
  $kv = 'flex gap-md border-b border-hairline-divider py-[9px] last:border-0';
  $kvLabel = 'w-[42%] shrink-0 text-bodysm text-ink-secondary';
  $kvValue = 'min-w-0 flex-1 break-words text-right text-bodysm font-semibold text-ink';
  $menuItem = 'flex w-full items-center gap-md px-lg py-[10px] text-left text-bodysm font-medium text-ink hover:bg-surface-muted disabled:opacity-50';
  $menuIcon = 'text-ink-secondary [&>svg]:h-[17px] [&>svg]:w-[17px]';
  $iconBtn = 'inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted hover:text-ink disabled:opacity-40 disabled:hover:bg-transparent';
@endphp
<div x-show="openId" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true" aria-label="Applicant">
  <div class="absolute inset-0 bg-ink/30" x-show="openId" x-transition.opacity @click="close()"></div>
  <aside x-show="openId" x-transition:enter="transition duration-component ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition duration-micro ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="absolute inset-y-0 right-0 flex w-full flex-col bg-canvas-alt shadow-raised sm:max-w-[600px]">

    {{-- top bar --}}
    <div class="flex items-center gap-xs border-b border-hairline bg-surface px-md py-sm">
      <button type="button" class="{{ $iconBtn }}" @click="close()" aria-label="Close">@include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])</button>
      <p class="flex-1 truncate px-xs text-caption text-ink-secondary">
        <span x-show="position !== -1" x-text="'Applicant ' + (position + 1) + ' of ' + rows.length"></span>
      </p>
      <button type="button" class="{{ $iconBtn }}" :disabled="position <= 0" @click="step(-1)" aria-label="Previous applicant" title="Previous">@include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-5 w-5'])</button>
      <button type="button" class="{{ $iconBtn }}" :disabled="position === -1 || position >= rows.length - 1" @click="step(1)" aria-label="Next applicant" title="Next">@include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-5 w-5'])</button>
    </div>

    <template x-if="!detail">
      <div class="flex-1">
        <template x-if="detailLoading || listLoading">@include('site.ui.spinner')</template>
        <template x-if="!detailLoading && !listLoading">
          <div class="p-xl">@include('site.ui.empty', ['icon' => 'user', 'title' => 'Applicant not available', 'text' => 'We could not load this application.'])</div>
        </template>
      </div>
    </template>

    <template x-if="detail">
      <div class="thin-scrollbar flex-1 overflow-y-auto">
        {{-- header --}}
        <div class="border-b border-hairline bg-surface px-xl py-lg">
          <div class="flex items-start gap-lg">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-[16px] bg-primary-light text-h2 text-primary-dark">
              <template x-if="dp.photo_url"><img :src="dp.photo_url" alt="" class="h-full w-full object-cover"></template>
              <template x-if="!dp.photo_url"><span x-text="initials(apName(detail))"></span></template>
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-h2 text-ink" x-text="apName(detail)"></p>
              <p class="text-bodysm text-ink-secondary" x-text="apDesignation(detail) || (dp.qualification || '')"></p>
              <p x-show="apLocation(detail)" class="mt-xs flex items-center gap-[3px] text-caption text-ink-secondary">
                <span x-html="ICONS.mapPin" class="text-ink-muted [&>svg]:h-[14px] [&>svg]:w-[14px]"></span><span x-text="apLocation(detail)"></span>
              </p>
              <p class="mt-sm flex flex-wrap items-center gap-sm">
                <span class="wa-pill" :class="appPill(detail.status)" x-text="appLabel(detail.status)"></span>
                <span class="text-caption text-ink-muted" x-text="'Applied ' + day(detail.applied_at)"></span>
              </p>
            </div>
          </div>

          {{-- actions: the next real decision first --}}
          <div class="mt-lg flex flex-wrap items-center gap-sm">
            <template x-if="detail.status === 'applied'">
              <button type="button" class="wa-btn-sm" :disabled="statusBusy" @click="shortlist()">@include('admin.partials.icon', ['name' => 'check', 'class' => 'h-4 w-4']) Shortlist</button>
            </template>
            <template x-if="detail.status === 'shortlisted'">
              <button type="button" class="wa-btn-sm" :disabled="statusBusy" @click="markSelected()">@include('admin.partials.icon', ['name' => 'checkCircle', 'class' => 'h-4 w-4']) Mark as selected</button>
            </template>
            <template x-if="detail.status === 'selected'">
              <span class="inline-flex h-10 items-center gap-xs rounded-button bg-success-bg px-lg text-btnghost font-semibold text-success">@include('admin.partials.icon', ['name' => 'checkCircle', 'class' => 'h-4 w-4']) Selected</span>
            </template>
            <template x-if="detail.status === 'rejected'">
              <button type="button" class="wa-btn-ghost-sm" :disabled="statusBusy" @click="reopen()">@include('admin.partials.icon', ['name' => 'refresh', 'class' => 'h-4 w-4']) Reopen</button>
            </template>
            <template x-if="detail.status !== 'rejected'">
              <button type="button" class="wa-btn-ghost-sm" @click="openInterview()">
                @include('admin.partials.icon', ['name' => 'calendar', 'class' => 'h-4 w-4'])
                <span x-text="detail.interview ? 'Reschedule' : 'Schedule interview'"></span>
              </button>
            </template>
            <a :href="messageUrl()" class="wa-btn-quiet border-hair border-hairline bg-surface">@include('admin.partials.icon', ['name' => 'message', 'class' => 'h-4 w-4']) Message</a>
            <div class="relative" @click.outside="moreOpen = false">
              <button type="button" class="wa-btn-quiet border-hair border-hairline bg-surface !px-sm" @click="moreOpen = !moreOpen" aria-label="More actions">@include('admin.partials.icon', ['name' => 'moreVertical', 'class' => 'h-4 w-4'])</button>
              <div x-show="moreOpen" x-cloak class="absolute right-0 top-11 z-10 w-[220px] overflow-hidden rounded-card border-hair border-hairline bg-surface py-xs shadow-raised animate-pop-in sm:left-0 sm:right-auto">
                <button type="button" class="{{ $menuItem }}" :disabled="!dp.phone" @click="call()"><span x-html="ICONS.phone" class="{{ $menuIcon }}"></span><span x-text="dp.phone ? 'Call ' + dp.phone : 'Call'"></span></button>
                <button type="button" class="{{ $menuItem }}" :disabled="!dp.email" @click="email()"><span x-html="ICONS.mail" class="{{ $menuIcon }}"></span>Email</button>
                <button type="button" class="{{ $menuItem }}" @click="resume(true)"><span x-html="ICONS.download" class="{{ $menuIcon }}"></span>Download resume</button>
                <p class="border-t border-hairline-divider px-lg pb-[2px] pt-sm text-[10.5px] font-semibold uppercase tracking-[0.5px] text-ink-muted">Change status</p>
                <template x-for="s in APP_STATUSES" :key="s">
                  <button type="button" class="{{ $menuItem }} !py-[8px]" :disabled="statusBusy" @click="s === 'rejected' ? reject() : changeStatus(s)">
                    <span class="h-2 w-2 rounded-full" :class="Emp.APP_DOT[s]"></span>
                    <span class="flex-1" x-text="appLabel(s)"></span>
                    <span x-show="detail.status === s" x-html="ICONS.check" class="text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
                  </button>
                </template>
              </div>
            </div>
            <template x-if="detail.status !== 'rejected'">
              <button type="button" class="wa-btn-quiet ml-auto text-danger hover:bg-danger-bg hover:text-danger" :disabled="statusBusy" @click="reject()">Reject</button>
            </template>
          </div>
        </div>

        <div class="space-y-lg p-lg sm:p-xl">
          {{-- interview --}}
          <template x-if="detail.interview">
            <section class="rounded-card border-hair border-info/25 bg-info-bg p-lg">
              <p class="flex items-center gap-sm text-h5 text-info">@include('admin.partials.icon', ['name' => 'calendar', 'class' => 'h-[18px] w-[18px]']) Interview scheduled</p>
              <p class="mt-xs text-bodysm text-ink" x-text="interviewDate(detail.interview) + ' at ' + detail.interview.time + ' · ' + (detail.interview.type === 'online' ? 'Online' : 'In person')"></p>
              <p class="mt-[2px] break-words text-bodysm text-ink-secondary" x-text="detail.interview.location_or_link"></p>
              <p x-show="detail.interview.notes" class="mt-xs text-caption text-ink-secondary" x-text="detail.interview.notes"></p>
            </section>
          </template>

          <div class="grid gap-lg sm:grid-cols-2">
            <section class="wa-card !p-lg">
              <p class="wa-section-title mb-sm">Candidate summary</p>
              <ul class="list-disc space-y-xs pl-lg text-bodysm text-ink marker:text-ink-muted">
                <li x-text="(dp.experience || '—') + ' experience'"></li>
                <li x-text="dp.qualification || '—'"></li>
                <li x-text="apLocation(detail) || '—'"></li>
                <li x-text="(dp.preferred_shifts || []).length ? 'Available for ' + dp.preferred_shifts.join(', ') + ' shift' : 'Shift preference not set'"></li>
              </ul>
            </section>
            <section class="wa-card !p-lg">
              <p class="wa-section-title mb-sm">Profile strength</p>
              <p class="flex items-baseline gap-sm">
                <span class="text-[28px] font-bold text-primary" x-text="apStrength(detail) + '%'"></span>
                <span class="text-bodysm text-ink" x-text="strengthLabel(apStrength(detail))"></span>
              </p>
              <span class="mt-sm block h-[7px] overflow-hidden rounded-[4px] bg-hairline"><span class="block h-full bg-primary" :style="'width:' + apStrength(detail) + '%'"></span></span>
            </section>
          </div>

          {{-- resume --}}
          <section class="wa-card flex items-center gap-md !p-lg">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-primary-light text-primary">@include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-5 w-5'])</span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-h5 text-ink" x-text="dp.resume || 'Resume'"></p>
              <p class="text-caption text-ink-secondary" x-text="'Submitted ' + day(detail.applied_at)"></p>
            </div>
            <button type="button" @click="resume(false)" :disabled="resumeBusy" class="wa-btn-ghost-sm !px-md">@include('admin.partials.icon', ['name' => 'eye', 'class' => 'h-4 w-4']) View</button>
            <button type="button" @click="resume(true)" :disabled="resumeBusy" aria-label="Download resume" title="Download resume" class="{{ $iconBtn }}">
              <span x-html="resumeBusy ? ICONS.loader : ICONS.download" :class="resumeBusy && 'animate-spin'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
            </button>
          </section>

          {{-- intro video --}}
          <template x-if="hasVideo || laterVideo">
            <section class="wa-card !p-lg">
              <p class="wa-section-title">Intro video</p>
              <p class="mb-sm mt-[2px] text-caption text-ink-secondary" x-text="laterVideo ? 'A 1-minute self-introduction, recorded after this application.' : 'A 1-minute self-introduction recorded by the candidate.'"></p>
              <template x-if="introUrl">
                <video :src="introUrl" controls autoplay playsinline class="aspect-[9/16] max-h-[440px] w-full rounded-card bg-surface-dark object-contain"></video>
              </template>
              <template x-if="!introUrl">
                <button type="button" @click="playIntro()" class="flex w-full items-center gap-md rounded-field bg-surface-muted p-md text-left hover:bg-hairline/60">
                  <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-ink-onPrimary [&>svg]:h-5 [&>svg]:w-5" x-html="introBusy ? ICONS.loader : ICONS.play" :class="introBusy && '[&>svg]:animate-spin'"></span>
                  <span><span class="block text-h5 text-ink">Watch intro video</span><span class="block text-caption text-ink-secondary" x-text="dp.intro_video_seconds ? dp.intro_video_seconds + ' seconds' : 'Plays here'"></span></span>
                </button>
              </template>
            </section>
          </template>

          <template x-if="about">
            <section class="wa-card !p-lg">
              <p class="wa-section-title mb-sm" x-text="laterAbout ? 'About candidate · added after applying' : 'About candidate'"></p>
              <p class="whitespace-pre-line text-body text-ink" :class="!aboutOpen && 'line-clamp-4'" x-text="about"></p>
              <button type="button" x-show="about.length > 200" class="mt-xs text-btnghost text-primary hover:underline" @click="aboutOpen = !aboutOpen" x-text="aboutOpen ? 'Read less' : 'Read more'"></button>
            </section>
          </template>

          <section class="wa-card !p-lg !py-xs">
            <p class="wa-section-title pb-xs pt-md">Professional information</p>
            <template x-for="row in [['Current role', apDesignation(detail)], ['Total experience', dp.experience],
                ['Current company', apCurrentRole(detail)?.organization], ['Department', apCurrentRole(detail)?.department],
                ['Notice period', dp.notice_period], ['Preferred location', (dp.location || []).join(', ')], ['Preferred shift', (dp.preferred_shifts || []).join(', ')]]" :key="row[0]">
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}" x-text="row[0]"></span><span class="{{ $kvValue }}" x-text="row[1] || '—'"></span></div>
            </template>
          </section>

          <template x-if="(dp.experiences || []).length">
            <section>
              <p class="wa-section-title mb-sm">Work experience</p>
              <div class="space-y-sm">
                <template x-for="e in dp.experiences" :key="e.id || e.designation + e.organization">
                  <div class="wa-card !p-md">
                    <p class="text-h5 text-ink" x-text="e.designation"></p>
                    <p class="text-bodysm text-ink-secondary" x-text="e.organization"></p>
                    <p class="text-caption text-ink-muted" x-text="[e.period, e.duration, e.department, (e.cities || []).join(', ')].filter(Boolean).join(' · ')"></p>
                    <p x-show="e.description" class="mt-sm text-bodysm text-ink" x-text="e.description"></p>
                  </div>
                </template>
              </div>
            </section>
          </template>

          <template x-if="(dp.educations || []).length">
            <section>
              <p class="wa-section-title mb-sm">Education</p>
              <div class="space-y-sm">
                <template x-for="e in dp.educations" :key="e.id || e.qualification + e.year">
                  <div class="wa-card !p-md">
                    <p class="text-h5 text-ink" x-text="e.specialization ? e.qualification + ' · ' + e.specialization : e.qualification"></p>
                    <p class="text-caption text-ink-muted" x-text="[e.institute, e.year, e.course_type, e.percentage ? e.percentage + '%' : ''].filter(Boolean).join(' · ') || '—'"></p>
                  </div>
                </template>
              </div>
            </section>
          </template>

          <div class="grid gap-lg sm:grid-cols-2">
            <section class="wa-card !p-lg">
              <p class="wa-section-title mb-sm">Languages</p>
              <div class="space-y-sm">
                <template x-for="l in (dp.languages || [])" :key="l">
                  <div class="flex justify-between gap-md text-bodysm">
                    <span class="font-semibold text-ink" x-text="l"></span>
                    <span class="text-caption text-ink-secondary" x-text="(dp.language_levels || {})[l] || ''"></span>
                  </div>
                </template>
                <p x-show="!(dp.languages || []).length" class="text-bodysm text-ink-muted">—</p>
              </div>
            </section>
            <section class="wa-card !p-lg">
              <p class="wa-section-title mb-sm">Skills</p>
              <div class="flex flex-wrap gap-xs"><template x-for="s in (dp.skills || [])" :key="s"><span class="wa-tag" x-text="s"></span></template></div>
              <p x-show="!(dp.skills || []).length" class="text-bodysm text-ink-muted">—</p>
            </section>
          </div>

          <section class="wa-card !p-lg">
            <p class="wa-section-title">Job preferences</p>
            <p class="wa-label mt-md">Preferred roles</p>
            <div class="flex flex-wrap gap-xs"><template x-for="r in (dp.preferred_roles || [])" :key="r"><span class="wa-tag" x-text="r"></span></template><span x-show="!(dp.preferred_roles || []).length" class="text-bodysm text-ink-muted">—</span></div>
            <p class="wa-label mt-md">Preferred locations</p>
            <div class="flex flex-wrap gap-xs"><template x-for="r in (dp.location || [])" :key="r"><span class="wa-tag" x-text="r"></span></template><span x-show="!(dp.location || []).length" class="text-bodysm text-ink-muted">—</span></div>
            <template x-if="(dp.preferred_job_types || []).length">
              <div>
                <p class="wa-label mt-md">Preferred job types</p>
                <div class="flex flex-wrap gap-xs"><template x-for="r in dp.preferred_job_types" :key="r"><span class="wa-tag" x-text="r"></span></template></div>
              </div>
            </template>
            <div class="mt-md">
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}">Expected salary</span><span class="{{ $kvValue }}" x-text="dp.expected_salary || '—'"></span></div>
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}">Preferred shift</span><span class="{{ $kvValue }}" x-text="(dp.preferred_shifts || []).join(', ') || '—'"></span></div>
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}">Willing to relocate</span><span class="{{ $kvValue }}" x-text="dp.willing_to_relocate == null ? '—' : (dp.willing_to_relocate ? 'Yes' : 'No')"></span></div>
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}">Immediate joiner</span><span class="{{ $kvValue }}" x-text="dp.immediate_joiner == null ? '—' : (dp.immediate_joiner ? 'Yes' : 'No')"></span></div>
            </div>
          </section>

          <section class="wa-card !p-lg !py-xs">
            <p class="wa-section-title pb-xs pt-md">Personal & contact</p>
            <template x-for="row in [['Phone', dp.phone], ['Email', dp.email], ['WhatsApp', dp.whatsapp_number], ['Gender', dp.gender],
                ['Age', dp.age ? dp.age + ' years' : ''], ['Address', dp.address], ['Marital status', dp.marital_status]]" :key="row[0]">
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}" x-text="row[0]"></span><span class="{{ $kvValue }}" x-text="row[1] || '—'"></span></div>
            </template>
          </section>

          <section class="wa-card !p-lg !py-xs">
            <p class="wa-section-title pb-xs pt-md">Application details</p>
            <template x-for="row in [['Applied for', job?.title || '—'], ['Application date', day(detail.applied_at)],
                ['Application status', appLabel(detail.status)], ['Submitted resume', dp.resume], ['Application ID', detail.application_id]]" :key="row[0]">
              <div class="{{ $kv }}"><span class="{{ $kvLabel }}" x-text="row[0]"></span><span class="{{ $kvValue }}" x-text="row[1] || '—'"></span></div>
            </template>
          </section>
          <p class="pb-lg text-caption text-ink-secondary">Everything above is frozen at the time of application — later profile changes do not affect it.</p>
        </div>
      </div>
    </template>
  </aside>
</div>

{{-- Schedule interview --}}
<div x-show="interview.open" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page">
  <form @submit.prevent="scheduleInterview()" @click.outside="interview.open = false" class="max-h-[92vh] w-full max-w-[480px] overflow-y-auto rounded-t-sheet bg-surface p-xl shadow-raised animate-pop-in sm:rounded-dialog">
    <h3 class="text-h2 text-ink" x-text="detail?.interview ? 'Reschedule interview' : 'Schedule Interview'"></h3>
    <p class="mt-[2px] text-bodysm text-ink-secondary" x-text="'with ' + (detail ? apName(detail) : '')"></p>
    <div class="mt-lg space-y-lg">
      <div class="grid grid-cols-2 gap-md">
        @include('site.ui.input', ['label' => 'Date', 'model' => 'interview.date', 'type' => 'date', 'required' => true, 'attrs' => ':min="interviewMinDate" :max="interviewMaxDate"'])
        @include('site.ui.input', ['label' => 'Time', 'model' => 'interview.time', 'type' => 'time', 'required' => true])
      </div>
      <div>
        <span class="wa-label">Interview type<span class="text-primary"> *</span></span>
        <div class="flex gap-sm">
          <button type="button" class="wa-chip" :class="interview.type === 'online' && 'wa-chip-on'" @click="interview.type = 'online'">@include('admin.partials.icon', ['name' => 'video', 'class' => 'h-4 w-4']) Online</button>
          <button type="button" class="wa-chip" :class="interview.type === 'inPerson' && 'wa-chip-on'" @click="interview.type = 'inPerson'">@include('admin.partials.icon', ['name' => 'building', 'class' => 'h-4 w-4']) In Person</button>
        </div>
      </div>
      <label class="block">
        <span class="wa-label"><span x-text="interview.type === 'online' ? 'Meeting link' : 'Location'"></span><span class="text-primary"> *</span></span>
        <input type="text" x-model="interview.location_or_link" :placeholder="interview.type === 'online' ? 'https://meet…' : 'Ward office, 2nd floor'" class="wa-input" maxlength="255">
      </label>
      @include('site.ui.textarea', ['label' => 'Notes (optional)', 'model' => 'interview.notes', 'rows' => 3, 'max' => 1000, 'placeholder' => 'Anything the candidate should know…'])
      <p x-show="interview.error" x-text="interview.error" class="wa-error"></p>
    </div>
    <div class="mt-xl flex flex-col-reverse gap-sm sm:flex-row sm:justify-end">
      <button type="button" class="wa-btn-quiet h-[50px]" @click="interview.open = false">Cancel</button>
      <button type="submit" class="wa-btn" :disabled="!interviewReady || interview.busy">
        <span x-show="interview.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        Schedule Interview
      </button>
    </div>
    <p class="mt-sm text-center text-caption text-ink-muted sm:text-right">The candidate is notified and moved to Shortlisted.</p>
  </form>
</div>
