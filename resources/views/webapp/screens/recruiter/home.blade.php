{{-- Hiring Dashboard (recruiter/home/views/recruiter_home_view.dart). --}}
<template x-if="is('rHome')">
  <div class="wa-page">
    <div class="mb-xl">
      <p class="wa-section-title">Recruiter</p>
      <h1 class="text-h2 text-ink">Hiring Dashboard</h1>
    </div>

    <div class="grid gap-xl lg:grid-cols-[360px_1fr] lg:items-start">
      <div class="space-y-lg">
        <div class="grid grid-cols-2 gap-md">
          <div class="wa-card">
            <p class="wa-section-title">Active Jobs</p>
            <p class="mt-sm text-stat tabular-nums text-primary" x-text="rActiveCount"></p>
          </div>
          <div class="wa-card">
            <p class="wa-section-title">Total Applicants</p>
            <p class="mt-sm text-stat tabular-nums text-primary" x-text="fmtNumber(rTotalApplicants)"></p>
          </div>
        </div>
        <a href="#/r/post" class="wa-btn w-full">
          @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-5 w-5'])
          Post a New Job
        </a>
      </div>

      <div>
        <div class="mb-md flex items-center justify-between gap-md">
          <h2 class="text-h3 text-ink">Recently Posted</h2>
          <a href="#/r/jobs" class="wa-btn-quiet text-primary">View All</a>
        </div>

        <template x-if="rJobsLoading && !rJobsLoaded">
          <div>@include('webapp.ui.spinner')</div>
        </template>

        <template x-if="rJobsLoaded && rRecentJobs.length === 0">
          <div>@include('webapp.ui.empty', ['icon' => 'briefcase', 'title' => 'No jobs posted yet', 'text' => 'Post your first job to start receiving applicants.'])</div>
        </template>

        <div class="space-y-md">
          <template x-for="job in rRecentJobs" :key="job.id">
            <a :href="'#/r/job/' + encodeURIComponent(job.id)" class="wa-card block transition-shadow hover:shadow-raised">
              <p class="text-h5 text-ink" x-text="job.title"></p>
              <p class="mt-[2px] text-bodysm text-ink-secondary" x-text="job.organisation + ' · ' + job.city"></p>
              <div class="mt-sm flex items-center gap-md text-caption text-ink-secondary">
                <span class="inline-flex items-center gap-[6px]">
                  <span class="h-[7px] w-[7px] rounded-full" :class="rStatusDot(job.posting_status)"></span>
                  <span x-text="rStatusLabel(job.posting_status)"></span>
                </span>
                <span class="font-semibold text-ink" x-text="(job.applicants_count || 0) + ' applicants'"></span>
              </div>
            </a>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
