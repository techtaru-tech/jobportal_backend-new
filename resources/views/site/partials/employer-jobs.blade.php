{{--
  My Jobs — every posting this account owns, in any status.

  Status is the point of this screen. A recruiter's first question after posting
  is "is it live yet", and the honest answer is often "no, it is waiting for an
  admin" — so the badge says exactly that rather than leaving them to guess from
  a silent list.
--}}
<template x-if="view === 'jobs'">
  <div class="animate-enter-up">

    <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
      <div class="min-w-0">
        <span class="block text-kicker text-ink-secondary">YOUR POSTINGS</span>
        <h1 class="mt-[2px] text-h1 text-ink">My jobs</h1>
        <p class="mt-xs text-bodysm text-ink-secondary">
          Every posting reaches candidates only after an admin approves it.
        </p>
      </div>
      <button @click="loadJobs()" aria-label="Refresh" title="Refresh"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline">
        <span x-html="ICONS.refresh" :class="jobs.busy && 'animate-spin'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
      </button>
    </div>

    {{-- A recruiter with no employer on file cannot post at all, so the gap is
         stated here rather than discovered at the last step of the wizard. --}}
    <template x-if="organisations.length === 0">
      <div class="mb-md flex flex-wrap items-center justify-between gap-md rounded-card border-hair border-warning/30 bg-warning-bg p-lg">
        <div class="min-w-0">
          <p class="text-bodysm font-semibold text-warning">No employer on file yet</p>
          <p class="mt-[2px] text-caption text-ink-secondary">
            A posting belongs to an employer. Add yours to get started — it takes a name.
          </p>
        </div>
        <button @click="startPost(); orgOpen = true"
                class="inline-flex h-[42px] shrink-0 items-center justify-center rounded-button bg-primary px-lg text-btn font-semibold text-ink-onPrimary shadow-button
                       transition-[background-color,transform] duration-micro hover:bg-primary-dark active:scale-[0.97]">
          Add employer
        </button>
      </div>
    </template>

    <template x-if="jobs.busy && !jobs.data.length">
      <div class="space-y-md">
        @for ($i = 0; $i < 3; $i++)
          <div class="rounded-card border-hair border-hairline bg-surface p-lg">
            <div class="flex items-center gap-md">
              <div class="shimmer-fill animate-shimmer h-11 w-11 shrink-0 rounded-logo" aria-hidden="true"></div>
              <div class="min-w-0 flex-1 space-y-sm">
                <div class="shimmer-fill animate-shimmer h-[15px] w-[40%] rounded-[6px]" aria-hidden="true"></div>
                <div class="shimmer-fill animate-shimmer h-[13px] w-[25%] rounded-[6px]" aria-hidden="true"></div>
              </div>
              <div class="shimmer-fill animate-shimmer h-6 w-[80px] rounded-field" aria-hidden="true"></div>
            </div>
          </div>
        @endfor
      </div>
    </template>

    <template x-if="!jobs.busy && !jobs.data.length && organisations.length > 0">
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
        <div class="flex flex-col items-center justify-center p-xxxl text-center">
          <div class="red-wash flex h-[76px] w-[76px] items-center justify-center rounded-card border-hair border-hairline text-ink-muted">
            @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-[28px] w-[28px]'])
          </div>
          <h2 class="mt-lg text-h4 text-ink">No postings yet</h2>
          <p class="mt-xs max-w-sm text-bodysm text-ink-secondary">
            Post your first opening and it goes into review straight away.
          </p>
          <button @click="startPost()"
                  class="mt-xl inline-flex h-[46px] items-center justify-center rounded-button bg-primary px-lg text-btn font-semibold text-ink-onPrimary shadow-button
                         transition-[background-color,transform] duration-micro hover:bg-primary-dark active:scale-[0.97]">
            Post a job
          </button>
        </div>
      </div>
    </template>

    <template x-if="jobs.data.length">
      <div class="space-y-md">
        <template x-for="job in jobs.data" :key="job.id">
          <article class="rounded-card border-hair border-hairline bg-surface p-lg shadow-card
                          transition-[box-shadow,border-color] duration-micro hover:border-hairline-strong hover:shadow-raised">

            <div class="flex flex-wrap items-start gap-md">
              <span aria-hidden="true" x-text="initials(job.organisation)" style="font-size:13.6px"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>

              <div class="min-w-0 flex-1">
                <h2 class="truncate text-h4 text-ink" x-text="job.title"></h2>
                <p class="mt-[2px] truncate text-caption text-ink-muted">
                  <span x-text="job.organisation"></span> · <span x-text="job.city"></span>
                  · <span x-text="job.code"></span>
                </p>
              </div>

              <span class="inline-flex shrink-0 items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                    :class="statusTone(job.posting_status)"
                    x-text="statusLabel(job.posting_status)"></span>
            </div>

            {{-- A rejection is the one status that comes with something to act
                 on, so the reason is shown rather than hidden behind a tap. --}}
            <template x-if="job.posting_status === 'rejected' && job.rejection_reason">
              <div class="mt-md rounded-field border-hair border-danger/30 bg-danger-bg px-md py-sm">
                <span class="block text-kicker text-danger">WHY IT WAS NOT APPROVED</span>
                <p class="mt-xs text-bodysm text-ink" x-text="job.rejection_reason"></p>
                <p class="mt-xs text-caption text-ink-muted">Edit the posting in the app to resubmit it.</p>
              </div>
            </template>

            <div class="mt-md flex flex-wrap items-center gap-md">
              <div class="flex flex-wrap gap-xs">
                <template x-if="job.type">
                  <span class="inline-flex items-center rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary" x-text="job.type"></span>
                </template>
                <template x-if="job.shift">
                  <span class="inline-flex items-center rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary" x-text="job.shift"></span>
                </template>
                <template x-if="salaryLabel(job) !== '—'">
                  <span class="inline-flex items-center rounded-field bg-primary-light px-md py-[5px] text-tag font-semibold text-primary-dark" x-text="salaryLabel(job)"></span>
                </template>
              </div>

              <span class="text-caption text-ink-muted" x-text="'Posted ' + timeAgo(job.posted_at)"></span>
            </div>

            <div class="mt-lg flex flex-wrap items-center gap-sm border-t border-hairline-divider pt-md">
              <button @click="openApplicants(job)"
                      class="inline-flex h-9 items-center justify-center gap-sm rounded-button border-btn border-primary bg-surface px-lg text-btnghost font-semibold text-primary
                             transition-colors duration-micro hover:bg-primary-light">
                <span x-text="fmtNumber(job.applicants_count ?? 0)"></span>
                <span x-text="(job.applicants_count === 1 ? 'applicant' : 'applicants')"></span>
              </button>

              {{-- Only the transitions the server accepts. A posting in review
                   has no live/paused state, and offering the control anyway
                   turns "waiting on an admin" into an error message. --}}
              <template x-if="canToggle(job)">
                <button @click="setStatus(job, job.posting_status === 'active' ? 'paused' : 'active')"
                        class="inline-flex h-9 items-center justify-center rounded-button border-btn border-hairline bg-surface px-lg text-btnghost font-semibold text-ink
                               transition-colors duration-micro hover:border-hairline-strong hover:bg-surface-muted"
                        x-text="job.posting_status === 'active' ? 'Pause' : 'Resume'"></button>
              </template>

              <template x-if="canToggle(job)">
                <button @click="setStatus(job, 'closed')"
                        class="inline-flex h-9 items-center justify-center rounded-button px-lg text-btnghost font-semibold text-ink-muted
                               transition-colors duration-micro hover:bg-danger-bg hover:text-danger">
                  Close
                </button>
              </template>

              <template x-if="job.posting_status === 'active'">
                <a :href="'/jobs/' + job.code" target="_blank" rel="noopener"
                   class="ml-auto inline-flex h-9 items-center justify-center gap-xs rounded-button px-md text-btnghost font-semibold text-primary-dark
                          transition-colors duration-micro hover:bg-primary-light">
                  <span>View on site</span>
                  <span x-html="ICONS.chevronRight" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </a>
              </template>
            </div>
          </article>
        </template>
      </div>
    </template>
  </div>
</template>
