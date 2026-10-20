{{-- Job Management (recruiter/job_management/views/job_management_view.dart). --}}
<template x-if="is('rJob')">
  <div class="wa-narrow">
    <div class="mb-lg flex items-center gap-md">
      <button type="button" @click="back('/r/jobs')" aria-label="Back" class="-ml-sm inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
      </button>
      <h1 class="flex-1 truncate text-h3 text-ink">Job Management</h1>
      <template x-if="rJob">
        @include('webapp.screens.recruiter.job-menu', ['job' => 'rJob', 'view' => "go('/job/' + encodeURIComponent(rJob.id))"])
      </template>
    </div>

    <template x-if="rJobMissing">
      <div>@include('webapp.ui.empty', ['icon' => 'briefcase', 'title' => 'Job Management', 'text' => 'This posting is no longer available.'])</div>
    </template>
    <template x-if="!rJob && !rJobMissing">
      <div>@include('webapp.ui.spinner')</div>
    </template>

    <template x-if="rJob">
      <div>
        <h2 class="text-h1 text-ink" x-text="rJob.title"></h2>
        <p class="mt-xs text-bodysm text-ink-secondary" x-text="rJob.organisation"></p>
        <p class="text-bodysm text-ink-secondary" x-text="rJob.city"></p>
        <div class="mt-sm flex items-center gap-[6px] text-bodysm font-semibold text-ink">
          <span class="h-[7px] w-[7px] rounded-full" :class="rStatusDot(rJob.posting_status)"></span>
          <span x-text="rStatusLabel(rJob.posting_status)"></span>
          <span x-show="rJob.hiring_stage_label" class="ml-sm font-normal text-caption text-ink-muted" x-text="'· ' + rJob.hiring_stage_label"></span>
        </div>

        <template x-for="n in [rJobNotice(rJob), rHiddenNotice(rJob)].filter(Boolean)" :key="n.message">
          <div class="mt-md flex items-center gap-sm rounded-field border-hair px-md py-sm" :class="rNoticeClass(n.tone)">
            <span x-html="ICONS[n.icon]" class="shrink-0 [&>svg]:h-[15px] [&>svg]:w-[15px]"></span>
            <p class="flex-1 text-caption leading-[1.4]" x-text="n.message"></p>
            <template x-if="n.action">
              <button type="button" class="shrink-0 px-sm text-caption font-bold hover:underline" @click="rNoticeAction(rJob, n.action)" x-text="n.label"></button>
            </template>
          </div>
        </template>

        <div class="wa-card mt-xl">
          <p class="wa-section-title">Applicants</p>
          <p class="mt-xs text-[34px] font-bold leading-tight text-primary" x-text="rJobTotal"></p>
          <p class="text-bodysm text-ink-secondary">Total applications received</p>
          <div class="mt-lg space-y-sm" x-show="rJobStats">
            <template x-for="s in R_APP_STATUSES" :key="s">
              <div x-show="(rJobStats?.by_status?.[s] || 0) > 0" class="flex items-center gap-sm">
                <span class="w-[92px] text-caption text-ink-secondary" x-text="rAppLabel(s)"></span>
                <span class="h-[6px] flex-1 overflow-hidden rounded-[3px] bg-hairline">
                  <span class="block h-full" :class="rAppDot(s)" :style="'width:' + (rJobTotal ? ((rJobStats?.by_status?.[s] || 0) / rJobTotal * 100) : 0) + '%'"></span>
                </span>
                <span class="w-5 text-right text-caption font-bold text-ink" x-text="rJobStats?.by_status?.[s] || 0"></span>
              </div>
            </template>
          </div>
          <button type="button" class="wa-btn mt-md w-full" @click="go('/r/applicants/' + encodeURIComponent(rJob.id))">View Applicants</button>
        </div>

        <p class="wa-section-title mt-xl">Job information</p>
        <div class="wa-card mt-sm !py-xs">
          <template x-for="row in [
              ['Job Title', rJob.title], ['Category', rJob.role], ['Experience', rJob.experience],
              ['Qualification', (rJob.qualifications || []).length ? rJob.qualifications.join(' / ') : '—'],
              ['Salary', rJob.salary], ['Job Type', rJob.type], ['Shift', rJob.shift], ['Location', rJob.city]]" :key="row[0]">
            <div class="flex gap-md border-b border-hairline-divider py-[10px] last:border-0">
              <span class="flex-1 text-bodysm text-ink-secondary" x-text="row[0]"></span>
              <span class="flex-1 text-right text-bodysm font-semibold text-ink" x-text="row[1] || '—'"></span>
            </div>
          </template>
        </div>

        <p class="wa-section-title mt-xl">Job description</p>
        <div class="mt-sm space-y-lg">
          <p x-show="rJob.about" class="whitespace-pre-line text-body text-ink" x-text="rJob.about"></p>
          <template x-if="(rJob.duties || []).length">
            <div>
              <p class="text-h5 text-ink">Responsibilities</p>
              <ul class="mt-xs list-disc space-y-[6px] pl-lg text-bodysm text-ink marker:text-ink-muted">
                <template x-for="d in rJob.duties" :key="d"><li x-text="d"></li></template>
              </ul>
            </div>
          </template>
          <template x-if="(rJob.benefits || []).length">
            <div>
              <p class="text-h5 text-ink">Benefits</p>
              <ul class="mt-xs list-disc space-y-[6px] pl-lg text-bodysm text-ink marker:text-ink-muted">
                <template x-for="d in rJob.benefits" :key="d"><li x-text="d"></li></template>
              </ul>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>
