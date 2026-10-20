{{--
  The app's job card (core/widgets/job_card.dart). Expects `job` in scope
  from an x-for. Logo initials, title, "organisation · city", share and save,
  tags, match badge, time ago, then "Job Detail" and "Apply" / "Applied ✓".
--}}
<article class="wa-card flex flex-col gap-md !p-lg transition-shadow hover:shadow-raised">
  <div class="flex items-start gap-md">
    <a :href="'#/job/' + job.id" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[14px] font-bold text-primary-dark" x-text="initials(job.organisation)"></a>
    <a :href="'#/job/' + job.id" class="min-w-0 flex-1">
      <h3 class="truncate text-h4 text-ink" x-text="job.title"></h3>
      <p class="mt-[2px] flex items-center gap-xs truncate text-bodysm text-ink-secondary">
        <span class="truncate" x-text="job.organisation"></span>
        <span x-show="job.organisation_verified" x-html="ICONS.badgeCheck" class="shrink-0 text-info [&>svg]:h-[14px] [&>svg]:w-[14px]" title="Verified employer"></span>
        <span>·</span><span class="truncate" x-text="job.city"></span>
      </p>
    </a>
    <div class="flex shrink-0 items-center">
      <button type="button" @click="shareJob(job)" class="inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted hover:text-ink" title="Share">
        <span x-html="ICONS.share" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      </button>
      <button type="button" @click="toggleSave(job)" :class="isSaved(job) ? 'text-primary' : 'text-ink-muted hover:text-ink'"
              class="inline-flex h-9 w-9 items-center justify-center rounded-field hover:bg-surface-muted" :title="isSaved(job) ? 'Saved' : 'Save'">
        <span x-html="ICONS.bookmark" :class="isSaved(job) && '[&>svg]:fill-current'" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      </button>
    </div>
  </div>

  <div class="flex flex-wrap gap-xs">
    <span class="wa-tag" x-show="jobSalary(job)"><span x-text="jobSalary(job)"></span></span>
    <span class="wa-tag" x-show="job.experience_display || job.experience" x-text="job.experience_display || job.experience"></span>
    <span class="wa-tag" x-show="job.type || job.shift" x-text="job.type || job.shift"></span>
  </div>

  <div class="flex items-center justify-between gap-sm">
    <template x-if="token && job.match_percent != null">
      <span class="wa-pill" :class="matchTone(job.match_percent).cls">
        <span x-text="job.match_percent + '% match'"></span><span x-show="isNewJob(job)">&nbsp;· New</span>
      </span>
    </template>
    <template x-if="!(token && job.match_percent != null)">
      <span class="wa-pill bg-primary-light text-primary-dark" x-show="isNewJob(job)">New</span>
    </template>
    <span class="ml-auto text-caption text-ink-muted" x-text="timeAgo(job.posted_at)"></span>
  </div>

  <div class="grid grid-cols-2 gap-sm">
    <a :href="'#/job/' + job.id" class="wa-btn-ghost-sm">Job Detail</a>
    <button type="button" @click="applyTo(job)"
            :class="token && hasApplied(job) ? '!bg-success-bg !text-success !shadow-none' : ''"
            class="wa-btn-sm" x-text="job.own_posting ? 'View applicants' : (token && hasApplied(job) ? 'Applied ✓' : 'Apply')"></button>
  </div>
</article>
