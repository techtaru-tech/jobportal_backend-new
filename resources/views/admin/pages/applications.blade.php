{{--
  Applications across every posting — the pipeline view no recruiter can see,
  because each of them only sees their own.

  The operationally useful thing is not the list but the **stuck queue**:
  applications sitting at `applied` with no movement. That is a named, fixable
  problem; "11 applications" is not.
--}}
<template x-if="view === 'applications'">
  <div class="animate-enter-up" x-init="loadApplications()">
    @include('admin.partials.page-header', [
      'kicker' => 'Applications',
      'title' => 'Applications',
      'description' => 'Every application, across every posting. Moving one here notifies the candidate.',
    ])

    @include('admin.partials.list-shell', [
      'state' => 'apps',
      'loader' => 'loadApplications',
      'cols' => 5,
      'placeholder' => 'Search reference, candidate or job…',
      'emptyTitle' => 'No applications match',
      'emptyMessage' => 'Try a different search or filter.',
      'filters' => '
        <div class="relative">
          <select x-model="apps.status" @change="loadApplications(1)" aria-label="Status"
                  class="h-[46px] w-[160px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">Any status</option>
            <template x-for="s in APP_STATUSES" :key="s">
              <option :value="s" x-text="s" class="capitalize"></option>
            </template>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>
        <button @click="apps.stuck = !apps.stuck; loadApplications(1)"
                :class="apps.stuck ? \'border-primary bg-primary-light font-semibold text-primary-dark\' : \'border-transparent bg-surface-muted text-ink hover:bg-hairline\'"
                class="inline-flex shrink-0 items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip whitespace-nowrap transition-colors duration-150">
          <span x-show="apps.stuck" x-html="ICONS.check" class="[&>svg]:h-[14px] [&>svg]:w-[14px] text-primary"></span>
          Stuck
        </button>
        <button @click="apps.missingInterview = !apps.missingInterview; loadApplications(1)"
                :class="apps.missingInterview ? \'border-primary bg-primary-light font-semibold text-primary-dark\' : \'border-transparent bg-surface-muted text-ink hover:bg-hairline\'"
                class="inline-flex shrink-0 items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip whitespace-nowrap transition-colors duration-150">
          <span x-show="apps.missingInterview" x-html="ICONS.check" class="[&>svg]:h-[14px] [&>svg]:w-[14px] text-primary"></span>
          No interview set
        </button>',

      'head' => '
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">CANDIDATE</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden md:table-cell">APPLIED TO</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">STATUS</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right hidden lg:table-cell">STRENGTH</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">APPLIED</th>',

      'body' => '
        <template x-for="row in apps.data" :key="row.reference">
          <tr @click="openApplication(row.reference)"
              class="group border-b border-hairline-divider last:border-0 cursor-pointer transition-colors duration-micro hover:bg-canvas-alt">
            <td class="relative px-lg py-md align-middle text-bodysm text-ink">
              <span aria-hidden="true" class="absolute inset-y-0 left-0 w-[2px] origin-top scale-y-0 bg-primary transition-transform duration-micro ease-out group-hover:scale-y-100"></span>
              <div class="flex items-center gap-md">
                <span aria-hidden="true" x-text="initials(row.candidate_name)" style="font-size:11.6px"
                      class="inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                <div class="min-w-0">
                  <span class="block truncate font-semibold text-ink" x-text="row.candidate_name"></span>
                  <span class="block truncate text-caption text-ink-muted" x-text="row.reference"></span>
                </div>
              </div>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink hidden md:table-cell">
              <span class="block truncate" x-text="row.job?.title || \'—\'"></span>
              <span class="block truncate text-caption text-ink-muted" x-text="row.job?.organisation || \'\'"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm">
              <span x-html="statusDot(row.status, \'application\')"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-right tabular-nums hidden lg:table-cell"
                x-text="row.snapshot_profile_strength + \'%\'"></td>
            <td class="px-lg py-md align-middle text-right">
              {{-- Stuck rows say so in place of the timestamp: the number of
                   days is the reason the row is worth opening. --}}
              <template x-if="row.is_stuck">
                <span class="inline-flex items-center rounded-chip bg-warning-bg px-[7px] py-[2px] text-caption tabular-nums text-warning"
                      x-text="row.days_since_applied + \'d waiting\'"></span>
              </template>
              <template x-if="!row.is_stuck">
                <span class="text-caption text-ink-muted" x-text="timeAgo(row.applied_at)"></span>
              </template>
            </td>
          </tr>
        </template>',
    ])
  </div>
</template>

{{-- ── detail ────────────────────────────────────────────────────────── --}}
<template x-if="view === 'applicationDetail'">
  <div class="animate-enter-up">
    <template x-if="appDetail.busy && !appDetail.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the application…'])
    </template>

    <template x-if="appDetail.data">
      <div>
        @include('admin.partials.back', ['to' => 'applications', 'label' => 'Back to applications'])

        <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
          <div class="flex min-w-0 items-center gap-lg">
            <span aria-hidden="true" x-text="initials(appDetail.data.application.candidate_name)" style="font-size:17px"
                  class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
            <div class="min-w-0">
              <span class="block text-kicker text-ink-secondary">APPLICATION</span>
              <h1 class="mt-[2px] truncate text-h2 text-ink" x-text="appDetail.data.application.candidate_name"></h1>
              <p class="mt-xs text-bodysm text-ink-secondary" x-text="appDetail.data.application.reference"></p>
            </div>
          </div>
          <span x-html="statusDot(appDetail.data.application.status, 'application')"></span>
        </div>

        {{--
          Moving an application here reaches into somebody else's hiring
          decision and fires a push the candidate cannot be un-sent, so the
          reason is required rather than optional.
        --}}
        <template x-if="canWrite">
          <div class="mb-md rounded-card border-hair border-hairline bg-surface shadow-card p-lg">
            <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Change status</h2></div>
            <div class="flex flex-col gap-sm sm:flex-row">
              <div class="relative sm:w-[180px]">
                <select x-model="appStatusPick"
                        class="h-[46px] w-full cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
                  <option value="">Move to…</option>
                  <template x-for="s in APP_STATUSES" :key="s">
                    <option :value="s" x-text="s"></option>
                  </template>
                </select>
                <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
              </div>
              <input x-model="appStatusReason" placeholder="Reason (required, min 3 characters)"
                     class="h-[46px] flex-1 rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
              <button @click="setAppStatus()" :disabled="!appStatusPick || appStatusReason.trim().length < 3"
                      class="inline-flex h-[46px] shrink-0 items-center justify-center rounded-button px-md text-btn font-semibold
                             bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                             hover:bg-primary-dark active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
                Apply
              </button>
            </div>
            <p class="mt-sm text-caption text-ink-muted">The candidate is notified, and this is written to the audit log.</p>
          </div>
        </template>

        <div class="grid grid-cols-1 gap-md lg:grid-cols-3">
          <div class="lg:col-span-2 space-y-md">
            {{--
              The frozen copy the employer received, beside the candidate's
              profile today. Which one is "right" depends on the question being
              asked, so both are shown rather than one replacing the other.
            --}}
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md">
                <h2 class="flex-1 text-h3 text-ink">Snapshot at apply time</h2>
                <template x-if="appDetail.data.strength_drift">
                  <span class="shrink-0 text-caption text-ink-muted">
                    <span x-text="appDetail.data.strength_drift.at_apply + '%'"></span> then ·
                    <span x-text="appDetail.data.strength_drift.now + '%'"></span> now
                  </span>
                </template>
              </div>
              <div class="grid gap-lg grid-cols-1 sm:grid-cols-3">
                @include('admin.partials.field', ['label' => 'Qualification', 'value' => 'appDetail.data.application.snapshot_qualification'])
                @include('admin.partials.field', ['label' => 'Experience', 'value' => 'appDetail.data.application.snapshot_experience'])
                @include('admin.partials.field', ['label' => 'Strength', 'value' => "appDetail.data.application.snapshot_profile_strength + '%'"])
              </div>
            </div>

            <template x-if="appDetail.data.live_profile">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Profile today</h2></div>
                <div class="grid gap-lg grid-cols-1 sm:grid-cols-3">
                  @include('admin.partials.field', ['label' => 'Qualification', 'value' => 'appDetail.data.live_profile.qualification'])
                  @include('admin.partials.field', ['label' => 'Experience', 'value' => 'appDetail.data.live_profile.experience'])
                  @include('admin.partials.field', ['label' => 'Resume', 'value' => "appDetail.data.live_profile.has_resume ? 'On file' : null"])
                </div>
                <div class="mt-lg">
                  <span class="block text-kicker text-ink-secondary">SKILLS</span>
                  <div class="mt-sm flex flex-wrap gap-xs">
                    <template x-if="(appDetail.data.live_profile.skills || []).length === 0">
                      <span class="text-bodysm text-ink-muted">—</span>
                    </template>
                    <template x-for="skill in appDetail.data.live_profile.skills" :key="skill">
                      <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-surface-muted text-ink-secondary" x-text="skill"></span>
                    </template>
                  </div>
                </div>
              </div>
            </template>

            <template x-if="appDetail.data.interview">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Interview</h2></div>
                <div class="grid gap-lg grid-cols-1 sm:grid-cols-2">
                  @include('admin.partials.field', ['label' => 'Date', 'value' => 'appDetail.data.interview.date'])
                  @include('admin.partials.field', ['label' => 'Time', 'value' => 'appDetail.data.interview.time'])
                  @include('admin.partials.field', ['label' => 'Type', 'value' => 'appDetail.data.interview.type'])
                  @include('admin.partials.field', ['label' => 'Where', 'value' => 'appDetail.data.interview.location_or_link'])
                </div>
              </div>
            </template>
          </div>

          <div class="space-y-md">
            <template x-if="appDetail.data.job">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Applied to</h2></div>
                <button @click="openJob(appDetail.data.job.id)"
                        class="block w-full rounded-field p-sm -m-sm text-left transition-colors duration-micro hover:bg-canvas-alt">
                  <span class="block truncate text-bodysm font-semibold text-ink" x-text="appDetail.data.job.title"></span>
                  <span class="block truncate text-caption text-ink-muted" x-text="appDetail.data.job.organisation"></span>
                  <span class="mt-sm inline-block" x-html="statusDot(appDetail.data.job.status, 'job')"></span>
                </button>
              </div>
            </template>

            {{-- The candidate's own Track screen is driven by this same list. --}}
            <template x-if="(appDetail.data.timeline || []).length">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Timeline</h2></div>
                <ol class="relative space-y-md border-l border-hairline pl-lg">
                  <template x-for="entry in appDetail.data.timeline" :key="entry.stage + entry.at">
                    <li class="relative">
                      <span class="absolute -left-[22px] top-[5px] h-[7px] w-[7px] rounded-full bg-primary ring-2 ring-canvas"></span>
                      <span class="block text-bodysm font-semibold capitalize text-ink" x-text="entry.stage"></span>
                      <span class="block text-caption text-ink-muted" x-text="fmtDateTime(entry.at)"></span>
                    </li>
                  </template>
                </ol>
              </div>
            </template>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Candidate</h2></div>
              <button @click="openUser(appDetail.data.candidate.id)"
                      class="flex w-full items-center justify-between gap-md rounded-field p-sm -m-sm text-left transition-colors duration-micro hover:bg-canvas-alt">
                <span class="text-bodysm text-ink" x-text="appDetail.data.candidate.phone"></span>
                <span x-html="ICONS.chevronRight" class="[&>svg]:h-4 [&>svg]:w-4 text-ink-muted"></span>
              </button>
              <template x-if="appDetail.data.conversation">
                <p class="mt-md border-t border-hairline-divider pt-md text-caption text-ink-muted">
                  <span x-text="appDetail.data.conversation.messages"></span> message(s) ·
                  last <span x-text="timeAgo(appDetail.data.conversation.last_message_at)"></span>
                </p>
              </template>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
