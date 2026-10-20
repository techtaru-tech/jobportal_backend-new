@extends('site.employer.layout')

@section('title', 'Jobs — Inthes employer')
@section('employer_key', 'jobs')

@section('employer')
<div x-data="empJobs()">
  <div class="mb-lg flex flex-wrap items-center justify-between gap-md">
    <div>
      <h1 class="text-h1 text-ink">Jobs</h1>
      <p class="mt-xs text-bodysm text-ink-secondary" x-show="!loading" x-text="jobs.length === 1 ? '1 job posted' : jobs.length + ' jobs posted'"></p>
    </div>
    <a href="{{ url('/employer/jobs/new') }}" class="wa-btn-sm">@include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4']) Post a job</a>
  </div>

  {{-- tabs + search --}}
  <div class="mb-lg flex flex-col gap-md lg:flex-row lg:items-center">
    <nav class="no-scrollbar -mx-page flex gap-xs overflow-x-auto px-page lg:mx-0 lg:flex-1 lg:px-0">
      <template x-for="t in TABS" :key="t.key">
        <button type="button" @click="status = t.key" x-show="!t.key || count(t.key) > 0 || status === t.key"
                :class="status === t.key ? 'border-ink bg-ink text-ink-onPrimary' : 'border-hairline bg-surface text-ink-secondary hover:text-ink'"
                class="inline-flex shrink-0 items-center gap-xs rounded-full border-hair px-md py-[7px] text-bodysm font-medium transition-colors">
          <span x-text="t.label"></span>
          <span class="tabular-nums opacity-70" x-text="count(t.key)"></span>
        </button>
      </template>
    </nav>
    <label class="relative block lg:w-[280px]">
      <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[18px] w-[18px]'])</span>
      <input type="search" x-model.debounce.200ms="q" placeholder="Search title, code, city" class="wa-input h-10 bg-surface pl-[40px]">
    </label>
  </div>

  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && error">
    <div class="wa-card text-center"><p class="text-bodysm text-danger" x-text="error"></p><button type="button" class="wa-btn-ghost-sm mt-md" @click="load()">Try again</button></div>
  </template>

  <template x-if="!loading && !error && !jobs.length">
    <div>
      @include('site.ui.empty', ['icon' => 'briefcase', 'title' => 'No jobs posted yet', 'text' => 'Post your first job — applicants appear here as soon as it is live.'])
      <div class="mt-lg text-center"><a href="{{ url('/employer/jobs/new') }}" class="wa-btn">Post a job</a></div>
    </div>
  </template>

  <template x-if="!loading && !error && jobs.length && !filtered.length">
    <div class="wa-card text-center">
      <p class="text-h5 text-ink">No jobs match</p>
      <p class="mt-xs text-bodysm text-ink-secondary">Try another tab or clear the search.</p>
      <button type="button" class="wa-btn-ghost-sm mt-md" @click="q = ''; status = ''">Show all jobs</button>
    </div>
  </template>

  <template x-if="!loading && filtered.length">
    <div>
      {{-- Desktop: a table --}}
      <div class="hidden overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card md:block">
        <table class="w-full text-left">
          <thead class="border-b border-hairline bg-surface-muted/60 text-caption uppercase tracking-[0.4px] text-ink-secondary">
            <tr>
              <th class="px-lg py-md font-semibold">Job</th>
              <th class="hidden px-md py-md font-semibold xl:table-cell">Company</th>
              <th class="px-md py-md font-semibold">Status</th>
              <th class="px-md py-md text-right font-semibold">Applicants</th>
              <th class="hidden px-md py-md font-semibold lg:table-cell">Posted</th>
              <th class="w-12 px-sm py-md"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline-divider">
            <template x-for="job in filtered" :key="job.id">
              <tr class="group cursor-pointer align-top hover:bg-surface-muted/50" @click="location.href = jobUrl(job)">
                <td class="px-lg py-md">
                  <a :href="jobUrl(job)" class="block text-h5 text-ink group-hover:text-primary-dark" x-text="job.title" @click.stop></a>
                  <p class="mt-[2px] text-caption text-ink-secondary">
                    <span class="font-mono" x-text="job.code"></span>
                    <span x-text="' · ' + [job.city, job.type].filter(Boolean).join(' · ')"></span>
                    <span class="xl:hidden" x-text="' · ' + job.organisation"></span>
                  </p>
                  <template x-for="n in noticesOf(job)" :key="n.message">
                    <p class="mt-xs flex items-center gap-xs text-caption" :class="n.tone === 'danger' ? 'text-danger' : (n.tone === 'brand' ? 'text-primary-dark' : 'text-warning')">
                      <span x-html="ICONS[n.icon]" class="shrink-0 [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
                      <span x-text="n.message"></span>
                      <template x-if="n.action"><a :href="noticeHref(job, n.action)" @click.stop class="ml-xs font-bold underline-offset-2 hover:underline" x-text="n.label"></a></template>
                    </p>
                  </template>
                </td>
                <td class="hidden px-md py-md text-bodysm text-ink-secondary xl:table-cell">
                  <span class="inline-flex items-center gap-xs">
                    <span x-text="job.organisation"></span>
                    <span x-show="job.organisation_verified" class="text-primary" title="Verified company">@include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-[14px] w-[14px]'])</span>
                  </span>
                </td>
                <td class="px-md py-md">
                  <span class="wa-pill" :class="statusPill(job.posting_status)" x-text="statusLabel(job.posting_status)"></span>
                  <p class="mt-[3px] max-w-[190px] text-[11px] leading-tight text-ink-muted" x-text="job.hiring_stage_label"></p>
                </td>
                <td class="px-md py-md text-right">
                  <a :href="jobUrl(job)" @click.stop class="text-h5 tabular-nums text-ink hover:text-primary" x-text="job.applicants_count || 0"></a>
                </td>
                <td class="hidden whitespace-nowrap px-md py-md text-bodysm text-ink-secondary lg:table-cell" x-text="day(job.posted_at)"></td>
                <td class="px-sm py-sm">@include('site.employer.partials.job-menu', ['job' => 'job'])</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      {{-- Phones: cards --}}
      <div class="space-y-md md:hidden">
        <template x-for="job in filtered" :key="job.id">
          <div class="wa-card !p-lg">
            <div class="flex items-start gap-sm">
              <a :href="jobUrl(job)" class="min-w-0 flex-1">
                <p class="text-h5 text-ink" x-text="job.title"></p>
                <p class="mt-[2px] text-caption text-ink-secondary" x-text="[job.organisation, job.city].filter(Boolean).join(' · ')"></p>
              </a>
              @include('site.employer.partials.job-menu', ['job' => 'job'])
            </div>
            <div class="mt-sm flex flex-wrap items-center gap-sm">
              <span class="wa-pill" :class="statusPill(job.posting_status)" x-text="statusLabel(job.posting_status)"></span>
              <span class="text-[11px] text-ink-muted" x-text="job.hiring_stage_label"></span>
            </div>
            <template x-for="n in noticesOf(job)" :key="n.message">
              <div class="mt-sm flex items-center gap-sm rounded-field border-hair px-md py-sm" :class="noticeClass(n.tone)">
                <span x-html="ICONS[n.icon]" class="shrink-0 [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
                <p class="flex-1 text-caption leading-[1.4]" x-text="n.message"></p>
                <template x-if="n.action"><a :href="noticeHref(job, n.action)" class="shrink-0 text-caption font-bold" x-text="n.label"></a></template>
              </div>
            </template>
            <a :href="jobUrl(job)" class="mt-md flex items-center justify-between border-t border-hairline-divider pt-md text-bodysm">
              <span class="text-ink-secondary"><span class="font-semibold text-ink" x-text="job.applicants_count || 0"></span> applicants</span>
              <span class="text-caption text-ink-muted" x-text="'Posted ' + day(job.posted_at)"></span>
            </a>
          </div>
        </template>
      </div>
    </div>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-jobs'])
@endsection
