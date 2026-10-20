{{-- My Posted Jobs (recruiter/my_jobs/views/my_jobs_view.dart). --}}
<template x-if="is('rJobs')">
  <div class="wa-page">
    <div class="mb-xl flex items-center justify-between gap-md">
      <h1 class="text-h2 text-ink">My Posted Jobs</h1>
      <a href="#/r/post" class="wa-btn-sm">
        @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4'])
        Post a job
      </a>
    </div>

    <template x-if="rJobsLoading && !rJobsLoaded">
      <div>@include('webapp.ui.spinner')</div>
    </template>

    <template x-if="rJobsLoaded && rJobsList.length === 0">
      <div>@include('webapp.ui.empty', ['icon' => 'briefcase', 'title' => 'No jobs posted yet', 'text' => 'Jobs you post will appear here with applicant counts and management controls.'])</div>
    </template>

    <div class="grid gap-md lg:grid-cols-2">
      <template x-for="job in rJobsList" :key="job.id">
        <div class="wa-card flex flex-col">
          <div class="flex items-start gap-sm">
            <div class="min-w-0 flex-1">
              <p class="text-h4 text-ink" x-text="job.title"></p>
              <p class="mt-[2px] truncate text-bodysm text-ink-secondary" x-text="job.organisation + ' · ' + job.city"></p>
            </div>
            @include('webapp.screens.recruiter.job-menu', ['job' => 'job', 'view' => "go('/r/job/' + encodeURIComponent(job.id))"])
          </div>

          <div class="mt-md flex flex-wrap gap-sm">
            <span class="wa-tag bg-primary-light text-primary-dark" x-show="job.salary" x-text="job.salary"></span>
            <span class="wa-tag" x-show="job.experience" x-text="job.experience"></span>
            <span class="wa-tag" x-show="job.type" x-text="job.type"></span>
          </div>

          {{-- Why a job has no applicants, and the one thing to do about it. --}}
          <template x-for="n in [rJobNotice(job), rHiddenNotice(job)].filter(Boolean)" :key="n.message">
            <div class="mt-md flex items-center gap-sm rounded-field border-hair px-md py-sm" :class="rNoticeClass(n.tone)">
              <span x-html="ICONS[n.icon]" class="shrink-0 [&>svg]:h-[15px] [&>svg]:w-[15px]"></span>
              <p class="flex-1 text-caption leading-[1.4]" x-text="n.message"></p>
              <template x-if="n.action">
                <button type="button" class="shrink-0 px-sm text-caption font-bold underline-offset-2 hover:underline" @click="rNoticeAction(job, n.action)" x-text="n.label"></button>
              </template>
            </div>
          </template>

          <div class="mt-md flex items-center border-y border-hairline-divider py-sm">
            <div class="flex flex-1 items-baseline gap-[6px]">
              <span class="text-h3 text-primary" x-text="job.applicants_count || 0"></span>
              <span class="text-bodysm font-semibold text-ink" x-text="(job.applicants_count || 0) === 1 ? 'Applicant' : 'Applicants'"></span>
            </div>
            <span class="mr-[6px] h-[7px] w-[7px] rounded-full" :class="rStatusDot(job.posting_status)"></span>
            <span class="text-caption text-ink-secondary" x-text="rStatusLabel(job.posting_status)"></span>
          </div>
          <div class="mt-sm flex flex-wrap items-center justify-between gap-sm text-caption text-ink-muted">
            <span x-text="job.posted_at ? 'Posted ' + timeAgo(job.posted_at) : 'Not posted yet'"></span>
            <span x-show="job.hiring_stage_label" x-text="job.hiring_stage_label"></span>
          </div>

          <div class="mt-auto grid grid-cols-2 gap-sm pt-md">
            <button type="button" class="wa-btn-ghost-sm h-[42px]" @click="go('/r/job/' + encodeURIComponent(job.id))">View Job</button>
            <button type="button" class="wa-btn-sm h-[42px]" @click="go('/r/applicants/' + encodeURIComponent(job.id))">Applicants</button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
