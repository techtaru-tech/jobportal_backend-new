{{-- Apply (the submit step), Success, Saved, My Applications and Track. --}}

{{-- ── Apply: a short wait while the server checks and submits ─────── --}}
<template x-if="is('apply')">
  <div class="wa-narrow">
    <div class="wa-card mt-xxl flex flex-col items-center py-xxxl text-center">
      @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-8 w-8 animate-spin text-primary'])
      <p class="mt-lg text-h4 text-ink" x-text="applyMsg || 'Applying…'"></p>
    </div>
  </div>
</template>

{{-- ── Success ─────────────────────────────────────────────────────── --}}
<template x-if="is('success')">
  <div class="wa-narrow">
    <template x-if="!succ.app">@include('webapp.ui.spinner')</template>
    <template x-if="succ.app">
      <div class="space-y-lg">
        <section class="wa-card flex flex-col items-center text-center">
          <span class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-success-bg text-success animate-pop-in">
            @include('admin.partials.icon', ['name' => 'checkCircle', 'class' => 'h-10 w-10'])
          </span>
          <h1 class="mt-lg text-h1 text-ink">Application sent</h1>
          <p class="mt-sm text-body text-ink-secondary">
            <span class="font-semibold text-ink" x-text="succ.app.job?.title"></span><br>
            <span x-text="(succ.app.job?.organisation || '') + ' · ' + (succ.app.job?.city || '')"></span>
          </p>
          <div class="mt-lg rounded-field bg-surface-muted px-lg py-sm">
            <span class="text-caption text-ink-muted">Application ID</span>
            <p class="font-mono text-bodysm font-semibold text-ink" x-text="succ.app.id"></p>
          </div>
        </section>

        <section class="wa-card">
          <h2 class="wa-section-title">WHAT HAPPENS NEXT</h2>
          <ol class="mt-md space-y-md">
            <li class="flex gap-md"><span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-light text-caption font-bold text-primary-dark">1</span><span class="text-body text-ink">The employer reviews your profile and resume.</span></li>
            <li class="flex gap-md"><span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-light text-caption font-bold text-primary-dark">2</span><span class="text-body text-ink">Shortlisted? They can message you right here.</span></li>
            <li class="flex gap-md"><span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-light text-caption font-bold text-primary-dark">3</span><span class="text-body text-ink">You are notified the moment your status changes.</span></li>
          </ol>
        </section>

        <div class="grid gap-sm sm:grid-cols-3">
          <a :href="'#/track/' + encodeURIComponent(succ.app.id)" class="wa-btn">Track this application</a>
          <a href="#/jobs" class="wa-btn-ghost">Find More Jobs</a>
          <a href="#/home" class="wa-btn-ghost !border-hairline-strong !text-ink">Back to Home</a>
        </div>
      </div>
    </template>
  </div>
</template>

{{-- ── Saved ───────────────────────────────────────────────────────── --}}
<template x-if="is('saved')">
  <div class="wa-page">
    <div class="mb-xl flex items-center justify-between gap-md">
      <h1 class="text-h2 text-ink">Saved Jobs</h1>
      <a href="#/alerts" class="wa-btn-ghost-sm"><span x-html="ICONS.bell" class="[&>svg]:h-4 [&>svg]:w-4"></span>Job alerts</a>
    </div>
    <template x-if="savedList.busy">@include('webapp.ui.spinner')</template>
    <template x-if="!savedList.busy && !savedList.list.length">
      <div class="space-y-md text-center">
        @include('webapp.ui.empty', ['icon' => 'bookmark', 'title' => 'No Saved Jobs', 'text' => 'Jobs you save will appear here.'])
        <a href="#/jobs" class="wa-btn-sm">Explore Jobs</a>
      </div>
    </template>
    <div class="grid gap-md md:grid-cols-2 xl:grid-cols-3">
      <template x-for="job in savedList.list" :key="job.id">@include('webapp.screens.jobs.card')</template>
    </div>
  </div>
</template>

{{-- ── My Applications ─────────────────────────────────────────────── --}}
<template x-if="is('applied')">
  <div class="wa-page">
    <h1 class="mb-lg text-h2 text-ink">My Applications</h1>
    <div class="no-scrollbar mb-lg flex gap-xs overflow-x-auto pb-xs">
      <template x-for="t in APP_TABS" :key="t.key">
        <button @click="apps.tab = t.key" :class="apps.tab === t.key && 'wa-chip-on'" class="wa-chip shrink-0">
          <span x-text="t.label"></span><span class="text-ink-muted" x-text="appsCount(t.key)"></span>
        </button>
      </template>
    </div>

    <template x-if="apps.busy">@include('webapp.ui.spinner')</template>
    <template x-if="!apps.busy && !apps.list.length">
      <div class="space-y-md text-center">
        @include('webapp.ui.empty', ['icon' => 'clipboard', 'title' => 'No Applications Yet', 'text' => 'Start exploring healthcare opportunities.'])
        <a href="#/jobs" class="wa-btn-sm">Find Jobs</a>
      </div>
    </template>
    <template x-if="!apps.busy && apps.list.length && !appsFiltered.length">
      @include('webapp.ui.empty', ['icon' => 'inbox', 'title' => 'Nothing here yet', 'text' => 'Applications in this stage will show up here.'])
    </template>

    <div class="grid gap-md md:grid-cols-2">
      <template x-for="a in appsFiltered" :key="a.id">
        <a :href="'#/track/' + encodeURIComponent(a.id)" class="wa-card flex items-start gap-md !p-lg transition-shadow hover:shadow-raised">
          <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[14px] font-bold text-primary-dark" x-text="initials(a.job?.organisation)"></span>
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-sm">
              <h3 class="truncate text-h4 text-ink" x-text="a.job?.title"></h3>
              <span class="wa-pill shrink-0" :class="appStatus(a.status).tone" x-text="appStatus(a.status).label"></span>
            </div>
            <p class="mt-[2px] truncate text-bodysm text-ink-secondary" x-text="(a.job?.organisation || '') + ' · ' + (a.job?.city || '')"></p>
            <p class="mt-sm text-caption text-ink-muted" x-text="'Applied ' + fmtDate(a.applied_at)"></p>
            <div class="mt-sm h-[5px] overflow-hidden rounded-full bg-surface-muted">
              <div class="h-full rounded-full" :class="a.status === 'rejected' ? 'bg-ink-muted' : 'bg-primary'" :style="`width:${a.progress_percent ?? 33}%`"></div>
            </div>
          </div>
        </a>
      </template>
    </div>
  </div>
</template>

{{-- ── Track ───────────────────────────────────────────────────────── --}}
<template x-if="is('track')">
  <div class="wa-narrow">
    @include('webapp.ui.head', ['title' => 'Application', 'back' => '/applied'])
    <template x-if="trk.busy">@include('webapp.ui.spinner')</template>
    <template x-if="!trk.busy && trk.error">
      @include('webapp.ui.empty', ['icon' => 'alert', 'title' => 'Application not available', 'text' => "We couldn't load this application. Check your connection and try again."])
    </template>

    <template x-if="!trk.busy && trk.app">
      <div class="space-y-lg" x-data="{ get app() { return trk.app } }">
        <a :href="'#/job/' + app.job_id" class="wa-card flex items-start gap-md hover:shadow-raised">
          <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[14px] font-bold text-primary-dark" x-text="initials(app.job?.organisation)"></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-h3 text-ink" x-text="app.job?.title"></h2>
            <p class="mt-[2px] text-bodysm text-ink-secondary" x-text="(app.job?.organisation || '') + ' · Applied ' + fmtDate(app.applied_at)"></p>
            <p class="mt-xs font-mono text-caption text-ink-muted" x-text="'#' + app.id"></p>
          </div>
          <span class="wa-pill shrink-0" :class="appStatus(app.status).tone" x-text="appStatus(app.status).label"></span>
        </a>

        <section class="wa-card">
          <div class="flex items-center justify-between">
            <h2 class="text-h4 text-ink">Progress</h2>
            <span class="text-caption text-ink-muted" x-text="appStatus(app.status).note"></span>
          </div>
          <ol class="mt-lg">
            <template x-for="(s, i) in trackSteps(app)" :key="s.stage">
              <li class="relative flex gap-md pb-lg last:pb-0">
                <span x-show="i < 2" class="absolute left-[13px] top-[28px] h-[calc(100%-28px)] w-[2px]" :class="trackSteps(app)[i + 1]?.done ? 'bg-primary' : 'bg-hairline'"></span>
                <span class="relative z-10 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                      :class="s.done ? 'bg-primary text-ink-onPrimary' : 'border-2 border-hairline bg-surface text-ink-muted'">
                  <span x-show="s.done" x-html="ICONS.check" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </span>
                <div>
                  <p class="text-h5" :class="s.done ? 'text-ink' : 'text-ink-muted'" x-text="s.label"></p>
                  <p class="text-caption text-ink-muted" x-show="s.at" x-text="fmtDate(s.at)"></p>
                </div>
              </li>
            </template>
          </ol>
          <template x-if="app.status === 'rejected'">
            <p class="mt-lg rounded-field bg-surface-muted p-md text-bodysm text-ink-secondary" x-text="'This employer has closed your application on ' + fmtDate(rejectedAt(app)) + '.'"></p>
          </template>
        </section>

        <template x-if="app.interview">
          <section class="wa-card border-primary-line bg-primary-light/40">
            <h2 class="flex items-center gap-sm text-h4 text-ink"><span x-html="ICONS.calendar" class="text-primary [&>svg]:h-5 [&>svg]:w-5"></span>Your interview</h2>
            <p class="mt-md text-h5 text-ink" x-text="app.interview.date + ' · ' + app.interview.time"></p>
            <p class="mt-xs text-bodysm text-ink-secondary" x-text="interviewTypeLabel(app.interview.type) + ' · ' + (app.interview.location_or_link || '')"></p>
            <template x-if="app.interview.type === 'online' && /^https?:/.test(app.interview.location_or_link || '')">
              <a :href="app.interview.location_or_link" target="_blank" rel="noopener" class="wa-btn-sm mt-md">Join meeting</a>
            </template>
            <p class="mt-sm text-bodysm text-ink" x-show="app.interview.notes" x-text="app.interview.notes"></p>
          </section>
        </template>

        <div class="grid gap-sm sm:grid-cols-2">
          <a :href="'#/chat/' + encodeURIComponent(app.id)" class="wa-btn-ghost"><span x-html="ICONS.message" class="[&>svg]:h-5 [&>svg]:w-5"></span>Message employer</a>
          <a :href="'#/job/' + app.job_id" class="wa-btn-ghost !border-hairline-strong !text-ink">View job</a>
        </div>

        <template x-if="app.profile_snapshot">
          <details class="wa-card group">
            <summary class="flex cursor-pointer list-none items-center justify-between text-h4 text-ink">
              Submitted snapshot
              <span x-html="ICONS.chevronDown" class="transition-transform group-open:rotate-180 [&>svg]:h-5 [&>svg]:w-5"></span>
            </summary>
            <dl class="mt-md grid grid-cols-2 gap-md text-bodysm">
              <template x-for="row in [['Name', app.profile_snapshot.name], ['Qualification', app.profile_snapshot.qualification], ['Experience', app.profile_snapshot.experience], ['Location', (app.profile_snapshot.location || []).join(', ')], ['Phone', app.profile_snapshot.phone], ['Email', app.profile_snapshot.email]].filter(r => r[1])" :key="row[0]">
                <div><dt class="text-caption text-ink-muted" x-text="row[0]"></dt><dd class="text-ink" x-text="row[1]"></dd></div>
              </template>
            </dl>
          </details>
        </template>
      </div>
    </template>
  </div>
</template>
