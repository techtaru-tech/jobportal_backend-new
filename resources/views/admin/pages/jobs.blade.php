{{--
  Job postings, in **every** status.

  The public `/jobs` endpoint shows only `active` postings and a recruiter's own
  list shows only theirs, so this is the only view of the whole corpus — which
  is what makes it a moderation surface rather than a second browse screen.
--}}
<template x-if="view === 'jobs'">
  <div class="animate-enter-up" x-init="loadJobs()">
    @include('admin.partials.page-header', [
      'kicker' => 'Job postings',
      'title' => 'Job postings',
      'description' => 'Every job in every status. The company is verified first (Companies tab); then its jobs are approved here.',
    ])

    {{--
      The paid-posting pipeline: has the employer paid, and have they given
      their business document. Tap a stage to list only those postings.
    --}}
    <div class="mb-md flex flex-wrap gap-sm" x-show="jobStages.length">
      <template x-for="stage in jobStages" :key="stage.id">
        <button @click="pickJobStage(stage.id)"
                :class="jobs.stage === stage.id ? 'ring-2 ring-primary ' + stageTone(stage.id) : stageTone(stage.id)"
                class="inline-flex items-center gap-xs rounded-chip px-md py-[6px] text-chip whitespace-nowrap transition-shadow duration-150">
          <span x-text="stage.label"></span>
          <span class="rounded-full bg-surface px-[6px] text-caption font-semibold tabular-nums" x-text="stage.count"></span>
        </button>
      </template>
    </div>

    {{-- Opened from a company page: only that company's jobs. --}}
    <template x-if="jobs.org">
      <div class="mb-md flex flex-wrap items-center gap-sm rounded-card border-hair border-primary-line bg-primary-light px-lg py-md">
        <span x-html="ICONS.badgeCheck" class="[&>svg]:h-[18px] [&>svg]:w-[18px] text-primary-dark"></span>
        <p class="flex-1 text-bodysm text-primary-dark">
          Showing all jobs of <span class="font-semibold" x-text="jobs.orgName"></span>
        </p>
        <button @click="openOrganisation(jobs.org)" class="text-bodysm font-semibold text-primary-dark hover:underline">Open company</button>
        <button @click="clearJobCompany()" class="text-bodysm text-ink-secondary hover:underline">Show all companies</button>
      </div>
    </template>

    @include('admin.partials.list-shell', [
      'state' => 'jobs',
      'loader' => 'loadJobs',
      'cols' => 5,
      'placeholder' => 'Search title, code or company…',
      'emptyTitle' => 'No postings match',
      'emptyMessage' => 'Try a different search or filter.',
      'filters' => '
        <div class="relative">
          <select x-model="jobs.status" @change="loadJobs(1)" aria-label="Status"
                  class="h-[46px] w-[170px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">Any status</option>
            <template x-for="s in JOB_STATUSES" :key="s">
              <option :value="s" x-text="s.replace(\'_\', \' \')" class="capitalize"></option>
            </template>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>
        <button @click="jobs.zero = !jobs.zero; loadJobs(1)"
                :class="jobs.zero ? \'border-primary bg-primary-light font-semibold text-primary-dark\' : \'border-transparent bg-surface-muted text-ink hover:bg-hairline\'"
                class="inline-flex shrink-0 items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip whitespace-nowrap transition-colors duration-150">
          <span x-show="jobs.zero" x-html="ICONS.check" class="[&>svg]:h-[14px] [&>svg]:w-[14px] text-primary"></span>
          Zero applicants
        </button>
        <button @click="jobs.unverified = !jobs.unverified; loadJobs(1)"
                :class="jobs.unverified ? \'border-primary bg-primary-light font-semibold text-primary-dark\' : \'border-transparent bg-surface-muted text-ink hover:bg-hairline\'"
                class="inline-flex shrink-0 items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip whitespace-nowrap transition-colors duration-150">
          <span x-show="jobs.unverified" x-html="ICONS.check" class="[&>svg]:h-[14px] [&>svg]:w-[14px] text-primary"></span>
          Company not verified
        </button>
        <button @click="jobs.missingCoords = !jobs.missingCoords; loadJobs(1)"
                :class="jobs.missingCoords ? \'border-primary bg-primary-light font-semibold text-primary-dark\' : \'border-transparent bg-surface-muted text-ink hover:bg-hairline\'"
                class="inline-flex shrink-0 items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip whitespace-nowrap transition-colors duration-150">
          <span x-show="jobs.missingCoords" x-html="ICONS.check" class="[&>svg]:h-[14px] [&>svg]:w-[14px] text-primary"></span>
          Missing location
        </button>',

      'head' => '
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">POSTING</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden md:table-cell">COMPANY</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">STATUS</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">APPLICANTS</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right hidden lg:table-cell">POSTED</th>',

      'body' => '
        <template x-for="row in jobs.data" :key="row.id">
          <tr @click="openJob(row.id)"
              class="group border-b border-hairline-divider last:border-0 cursor-pointer transition-colors duration-micro hover:bg-canvas-alt">
            <td class="relative px-lg py-md align-middle text-bodysm text-ink">
              <span aria-hidden="true" class="absolute inset-y-0 left-0 w-[2px] origin-top scale-y-0 bg-primary transition-transform duration-micro ease-out group-hover:scale-y-100"></span>
              <span class="block truncate font-semibold text-ink" x-text="row.title"></span>
              <span class="block truncate text-caption text-ink-muted">
                <span x-text="row.code"></span> · <span x-text="row.city"></span>
              </span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink hidden md:table-cell">
              <button type="button" @click.stop="row.organisation_id && openOrganisation(row.organisation_id)"
                      class="flex max-w-full items-center gap-xs text-left hover:text-primary-dark">
                <span class="truncate" x-text="row.organisation"></span>
                <span x-show="row.organisation_verified" x-html="ICONS.badgeCheck" class="[&>svg]:h-[14px] [&>svg]:w-[14px] shrink-0 text-primary" title="Verified company"></span>
              </button>
              <span x-show="!row.organisation_verified" class="block text-caption text-warning">Company not verified</span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm">
              <span x-html="statusDot(row.status, \'job\')"></span>
              <span class="mt-[2px] block text-caption text-ink-secondary" x-text="row.stage_label"></span>
              <span x-show="row.plan" class="block text-caption text-ink-muted" x-text="row.plan"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-right tabular-nums" x-text="row.applicants"></td>
            <td class="px-lg py-md align-middle text-right hidden lg:table-cell">
              <span class="text-caption text-ink-muted" x-text="timeAgo(row.posted_at)"></span>
            </td>
          </tr>
        </template>',
    ])
  </div>
</template>

{{-- ── detail ────────────────────────────────────────────────────────── --}}
<template x-if="view === 'jobDetail'">
  <div class="animate-enter-up">
    <template x-if="jobDetail.busy && !jobDetail.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the job…'])
    </template>

    <template x-if="jobDetail.data">
      <div>
        @include('admin.partials.back', ['to' => 'jobs', 'label' => 'Back to job postings'])

        <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
          <div class="min-w-0">
            <span class="block text-kicker text-ink-secondary">POSTING</span>
            <h1 class="mt-[2px] text-h2 text-ink" x-text="jobDetail.data.job.title"></h1>
            <p class="mt-xs text-bodysm text-ink-secondary">
              <span x-text="jobDetail.data.job.code"></span> ·
              <span x-text="jobDetail.data.job.organisation"></span> ·
              <span x-text="jobDetail.data.job.city"></span>
            </p>
            <div class="mt-sm flex flex-wrap items-center gap-sm">
              <span x-html="statusDot(jobDetail.data.job.status, 'job')"></span>
              <template x-if="jobDetail.data.job.salary">
                <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-primary-light text-primary-dark"
                      x-text="jobDetail.data.job.salary"></span>
              </template>
            </div>
          </div>
        </div>

        {{--
          The review queue's own banner. A posting waiting on a decision is the
          one case where the action belongs above the detail rather than beside
          it — an operator here is deciding, not browsing.
        --}}
        {{-- The company comes first: until it is verified, this job cannot be
             approved — the way forward is the company page. --}}
        <template x-if="jobDetail.data.job.awaiting_review && !jobDetail.data.job.can_approve && canWrite">
          <div class="mb-md flex flex-wrap items-center justify-between gap-md rounded-card border-hair border-warning/40 bg-warning-bg p-lg">
            <div class="min-w-0">
              <p class="text-bodysm font-semibold text-warning">Verify the company first.</p>
              <p class="mt-[2px] text-caption text-ink-secondary">
                <span x-text="jobDetail.data.employer?.name || 'This company'"></span>
                is not verified yet. Check its business document and verify it — then this job can be approved.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-sm">
              <template x-if="jobDetail.data.employer">
                <button @click="openOrganisation(jobDetail.data.employer.id)"
                        class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                               bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                               hover:bg-primary-dark active:scale-[0.97]">
                  <span x-html="ICONS.badgeCheck" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                  <span>Open company</span>
                </button>
              </template>
              <button @click="rejectJob()"
                      class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-surface text-danger border-btn border-danger transition-[background-color,transform] duration-micro ease-out
                             hover:bg-danger-bg active:scale-[0.97]">
                <span>Reject</span>
              </button>
            </div>
          </div>
        </template>

        <template x-if="jobDetail.data.job.awaiting_review && jobDetail.data.job.can_approve && canWrite">
          <div class="mb-md flex flex-wrap items-center justify-between gap-md rounded-card border-hair border-primary-line bg-primary-light p-lg">
            <div class="min-w-0">
              <p class="text-bodysm font-semibold text-primary-dark">This job is waiting for approval.</p>
              <p class="mt-[2px] text-caption text-ink-secondary">The company is verified. Candidates cannot see this job until it is approved.</p>
            </div>
            <div class="flex flex-wrap items-center gap-sm">
              <button @click="approveJob()"
                      class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                             hover:bg-primary-dark active:scale-[0.97]">
                <span x-html="ICONS.check" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span>Approve &amp; publish</span>
              </button>
              <button @click="rejectJob()"
                      class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-surface text-danger border-btn border-danger transition-[background-color,transform] duration-micro ease-out
                             hover:bg-danger-bg active:scale-[0.97]">
                <span>Reject</span>
              </button>
            </div>
          </div>
        </template>

        <template x-if="jobDetail.data.job.rejection_reason">
          <div class="mb-md rounded-card border-hair border-danger/30 bg-danger-bg p-lg">
            <span class="block text-kicker text-danger">REJECTED</span>
            <p class="mt-xs text-bodysm text-ink" x-text="jobDetail.data.job.rejection_reason"></p>
            <p class="mt-xs text-caption text-ink-muted" x-text="'Reviewed ' + timeAgo(jobDetail.data.job.reviewed_at)"></p>
          </div>
        </template>

        <div class="grid grid-cols-1 gap-md lg:grid-cols-3">
          <div class="lg:col-span-2 space-y-md">
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Details</h2></div>
              <div class="grid gap-lg grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                @include('admin.partials.field', ['label' => 'Role', 'value' => 'jobDetail.data.job.role'])
                @include('admin.partials.field', ['label' => 'Type', 'value' => 'jobDetail.data.job.type'])
                @include('admin.partials.field', ['label' => 'Shift', 'value' => 'jobDetail.data.job.shift'])
                @include('admin.partials.field', ['label' => 'Experience', 'value' => 'jobDetail.data.job.experience'])
                @include('admin.partials.field', ['label' => 'Salary', 'value' => 'jobDetail.data.job.salary'])
                @include('admin.partials.field', ['label' => 'Openings', 'value' => 'jobDetail.data.job.openings'])
                @include('admin.partials.field', ['label' => 'Minimum education', 'value' => 'jobDetail.data.job.min_education'])
                @include('admin.partials.field', ['label' => 'Gender', 'value' => 'jobDetail.data.job.preferred_gender'])
                @include('admin.partials.field', ['label' => 'State', 'value' => 'jobDetail.data.job.state'])
                @include('admin.partials.field', ['label' => 'Locality', 'value' => 'jobDetail.data.job.locality'])
                @include('admin.partials.field', ['label' => 'Pincode', 'value' => 'jobDetail.data.job.pincode'])
                @include('admin.partials.field', ['label' => 'Candidates contact via', 'value' => 'jobDetail.data.job.contact_mode_label'])
                @include('admin.partials.field', ['label' => 'Contact number', 'value' => 'jobDetail.data.job.contact_phone'])
                @include('admin.partials.field', ['label' => 'WhatsApp number', 'value' => 'jobDetail.data.job.whatsapp_phone'])
                @include('admin.partials.field', ['label' => 'Plan', 'value' => 'jobDetail.data.job.plan'])
                @include('admin.partials.field', ['label' => 'Stage', 'value' => 'jobDetail.data.job.stage_label'])
              </div>

              {{-- Charging candidates is allowed but worth a second look. --}}
              <template x-if="jobDetail.data.job.charges_fee">
                <div class="mt-lg flex items-center gap-sm rounded-field bg-warning-bg px-md py-sm">
                  <span x-html="ICONS.alert" class="[&>svg]:h-4 [&>svg]:w-4 shrink-0 text-warning"></span>
                  <p class="text-caption text-ink">The employer says they charge candidates a fee for this job.</p>
                </div>
              </template>

              {{-- No coordinates and no city fallback means the posting
                   silently drops out of distance sorting for every candidate. --}}
              <template x-if="!jobDetail.data.job.has_coordinates">
                <div class="mt-lg flex items-center gap-sm rounded-field bg-warning-bg px-md py-sm">
                  <span x-html="ICONS.mapPinOff" class="[&>svg]:h-4 [&>svg]:w-4 shrink-0 text-warning"></span>
                  <p class="text-caption text-ink">No map coordinates — this posting drops out of distance sorting.</p>
                </div>
              </template>

              <template x-if="jobDetail.data.job.about">
                <div class="mt-lg">
                  <span class="block text-kicker text-ink-secondary">ABOUT</span>
                  <p class="mt-xs whitespace-pre-line text-bodysm text-ink" x-text="jobDetail.data.job.about"></p>
                </div>
              </template>

              <template x-for="group in [
                  { label: 'DUTIES', items: jobDetail.data.job.duties },
                  { label: 'QUALIFICATIONS', items: jobDetail.data.job.qualifications },
                  { label: 'SKILLS', items: jobDetail.data.job.skills },
                  {{-- Older postings only: Post a Job no longer asks for
                       duties or benefits, and an empty list is hidden. --}}
                  { label: 'BENEFITS', items: jobDetail.data.job.benefits },
                ]" :key="group.label">
                <template x-if="(group.items || []).length">
                  <div class="mt-lg">
                    <span class="block text-kicker text-ink-secondary" x-text="group.label"></span>
                    <div class="mt-sm flex flex-wrap gap-xs">
                      <template x-for="item in group.items" :key="item">
                        <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-surface-muted text-ink-secondary" x-text="item"></span>
                      </template>
                    </div>
                  </div>
                </template>
              </template>
            </div>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="border-b border-hairline-divider px-lg py-md">
                <h2 class="text-h4 text-ink">Applicants</h2>
              </div>
              <template x-if="jobDetail.data.applications.length === 0">
                <p class="p-lg text-bodysm text-ink-muted">Nobody has applied to this posting yet.</p>
              </template>
              <ul class="max-h-[460px] overflow-y-auto thin-scrollbar">
                <template x-for="row in jobDetail.data.applications" :key="row.reference">
                  <li class="border-b border-hairline-divider last:border-0">
                    <button @click="openApplication(row.reference)"
                            class="flex w-full items-center gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                      <span aria-hidden="true" x-text="initials(row.candidate_name)" style="font-size:11.6px"
                            class="inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                      <span class="min-w-0 flex-1">
                        <span class="block truncate text-bodysm font-semibold text-ink" x-text="row.candidate_name"></span>
                        <span class="block truncate text-caption text-ink-muted"
                              x-text="row.snapshot_profile_strength + '% strength · ' + timeAgo(row.applied_at)"></span>
                      </span>
                      <span x-html="statusDot(row.status, 'application')"></span>
                    </button>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <div class="space-y-md">
            {{-- Admin overrides. Unlike the recruiter endpoint this accepts any
                 status, including moves out of states the owner cannot leave —
                 that is the point of an override, and every one is audited. --}}
            <template x-if="canWrite">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Admin controls</h2></div>
                <div class="space-y-md">
                  <div>
                    <span class="mb-[5px] block text-label text-ink-secondary">Override status</span>
                    <div class="flex gap-sm">
                      <div class="relative flex-1">
                        <select x-model="jobStatusPick"
                                class="h-[46px] w-full cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
                          <option value="">Choose…</option>
                          <template x-for="s in JOB_STATUSES" :key="s">
                            <option :value="s" x-text="s.replace('_', ' ')"></option>
                          </template>
                        </select>
                        <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
                      </div>
                      <button @click="setJobStatus()" :disabled="!jobStatusPick"
                              class="inline-flex h-[46px] shrink-0 items-center justify-center rounded-button px-md text-btn font-semibold
                                     bg-surface text-ink border-btn border-hairline transition-[background-color,border-color,transform] duration-micro ease-out
                                     hover:border-hairline-strong hover:bg-surface-muted active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50">
                        Apply
                      </button>
                    </div>
                  </div>

                  {{-- `expires_at` is nullable, not fillable, and written by
                       nothing else in the app — this is its only writer. --}}
                  <div>
                    <span class="mb-[5px] block text-label text-ink-secondary">Expires on</span>
                    <div class="flex gap-sm">
                      <input type="date" x-model="jobExpiryPick"
                             class="h-[46px] flex-1 rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
                      <button @click="setJobExpiry()"
                              class="inline-flex h-[46px] shrink-0 items-center justify-center rounded-button px-md text-btn font-semibold
                                     bg-surface text-ink border-btn border-hairline transition-[background-color,border-color,transform] duration-micro ease-out
                                     hover:border-hairline-strong hover:bg-surface-muted active:scale-[0.97]">
                        Save
                      </button>
                    </div>
                    <p class="mt-xs text-caption text-ink-muted">Leave the date empty and save to clear it.</p>
                  </div>
                </div>
              </div>
            </template>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Company</h2></div>
              <template x-if="jobDetail.data.employer">
                <button @click="openOrganisation(jobDetail.data.employer.id)"
                        class="flex w-full items-center gap-md rounded-field p-sm -m-sm text-left transition-colors duration-micro hover:bg-canvas-alt">
                  <span aria-hidden="true" x-text="initials(jobDetail.data.employer.name)" style="font-size:13.6px"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-bodysm font-semibold text-ink" x-text="jobDetail.data.employer.name"></span>
                    <span class="block truncate text-caption text-ink-muted" x-text="jobDetail.data.employer.gst_number || 'No GST number'"></span>
                  </span>
                  <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                        :class="jobDetail.data.employer.verified ? 'bg-primary-light text-primary-dark' : 'bg-surface-muted text-ink-secondary'"
                        x-text="jobDetail.data.employer.verified ? 'Verified' : 'Unverified'"></span>
                </button>
              </template>
              <template x-if="jobDetail.data.employer">
                <button @click="viewCompanyJobs(jobDetail.data.employer)"
                        class="mt-md block text-bodysm font-semibold text-primary-dark hover:underline">
                  All jobs of this company →
                </button>
              </template>
              <template x-if="jobDetail.data.posted_by">
                <button @click="openUser(jobDetail.data.posted_by.id)"
                        class="mt-md flex w-full items-center justify-between gap-md border-t border-hairline-divider pt-md text-left">
                  <span class="text-caption text-ink-muted">Posted by</span>
                  <span class="text-bodysm text-primary-dark" x-text="jobDetail.data.posted_by.phone"></span>
                </button>
              </template>
            </div>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Applications</h2></div>
              <ul class="space-y-sm">
                <template x-for="stat in jobDetail.data.application_stats" :key="stat.status">
                  <li class="flex items-center justify-between gap-md">
                    <span x-html="statusDot(stat.status, 'application')"></span>
                    <span class="text-bodysm font-semibold tabular-nums text-ink" x-text="stat.count"></span>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
