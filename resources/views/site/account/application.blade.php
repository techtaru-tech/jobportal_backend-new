@extends('site.account.layout')

@section('title', 'Your application — Inthes')
@section('account_key', 'applications')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-application.js') }}?v={{ filemtime(public_path('js/site/pages/account-application.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountApplication(@js(['id' => $applicationId]))">
  <nav aria-label="Breadcrumb" class="mb-md flex items-center gap-xs text-caption text-ink-muted">
    <a href="{{ url('/account/applications') }}" class="hover:text-ink">My applications</a>
    @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-3 w-3'])
    <span class="truncate font-mono text-ink-secondary">{{ $applicationId }}</span>
  </nav>

  <template x-if="busy">@include('site.ui.spinner')</template>
  <template x-if="!busy && error">
    <div class="space-y-md text-center">
      @include('site.ui.empty', ['icon' => 'alert', 'title' => 'Application not available', 'text' => ''])
      <p class="text-bodysm text-ink-secondary" x-text="error"></p>
      <a href="{{ url('/account/applications') }}" class="wa-btn-ghost-sm">Back to my applications</a>
    </div>
  </template>

  <template x-if="!busy && app">
    <div class="grid gap-lg xl:grid-cols-[minmax(0,1fr)_320px]">
      <div class="min-w-0 space-y-lg">
        {{-- The job --}}
        <section class="wa-card">
          <div class="flex items-start gap-md">
            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[14px] font-bold text-primary-dark" x-text="initials(app.job?.organisation)"></span>
            <div class="min-w-0 flex-1">
              <h1 class="text-h2 text-ink" x-text="app.job?.title"></h1>
              <p class="mt-[2px] flex flex-wrap items-center gap-xs text-bodysm text-ink-secondary">
                <span x-text="app.job?.organisation"></span>
                <span x-show="app.job?.organisation_verified" x-html="ICONS.badgeCheck" class="text-info [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
                <span x-show="app.job?.city" x-text="'· ' + app.job?.city"></span>
              </p>
              <p class="mt-xs text-caption text-ink-muted"><span x-text="'Applied ' + fmtDate(app.applied_at, true)"></span> · <span class="font-mono" x-text="app.id"></span></p>
            </div>
            <span class="wa-pill shrink-0" :class="kit.appStatus(app.status).tone" x-text="kit.appStatus(app.status).label"></span>
          </div>
          <div class="mt-md flex flex-wrap gap-xs" x-show="app.job">
            <span x-show="kit.salary(app.job)" class="wa-tag !bg-primary-light font-semibold !text-primary-dark" x-text="kit.salary(app.job)"></span>
            <span x-show="kit.experience(app.job)" class="wa-tag" x-text="kit.experience(app.job)"></span>
            <span x-show="app.job?.type || app.job?.shift" class="wa-tag" x-text="[app.job?.type, app.job?.shift].filter(Boolean).join(' · ')"></span>
          </div>
        </section>

        {{-- Timeline --}}
        <section class="wa-card">
          <div class="flex flex-wrap items-center justify-between gap-sm">
            <h2 class="text-h4 text-ink">Progress</h2>
            <span class="text-caption text-ink-muted" x-text="kit.appStatus(app.status).note"></span>
          </div>
          <ol class="mt-lg">
            <template x-for="(s, i) in steps" :key="s.stage">
              <li class="relative flex gap-md pb-lg last:pb-0">
                <span x-show="i < steps.length - 1" class="absolute left-[13px] top-[28px] h-[calc(100%-28px)] w-[2px]" :class="steps[i + 1]?.done ? 'bg-primary' : 'bg-hairline'"></span>
                <span class="relative z-10 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                      :class="s.done ? 'bg-primary text-ink-onPrimary' : 'border-2 border-hairline bg-surface text-ink-muted'">
                  <span x-show="s.done" x-html="ICONS.check" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </span>
                <div>
                  <p class="text-h5" :class="s.done ? 'text-ink' : 'text-ink-muted'" x-text="s.label"></p>
                  <p class="text-caption text-ink-muted" x-text="s.at ? fmtDate(s.at, true) : (s.current ? 'Now' : 'Not yet')"></p>
                </div>
              </li>
            </template>
          </ol>
          <template x-if="app.status === 'rejected'">
            <p class="mt-lg rounded-field bg-surface-muted p-md text-bodysm text-ink-secondary" x-text="'This employer has closed your application on ' + fmtDate(kit.rejectedAt(app)) + '.'"></p>
          </template>
        </section>

        {{-- Interview --}}
        <template x-if="app.interview">
          <section class="wa-card !border-primary-line !bg-primary-light/40">
            <h2 class="flex items-center gap-sm text-h4 text-ink"><span x-html="ICONS.calendar" class="text-primary [&>svg]:h-5 [&>svg]:w-5"></span>Your interview</h2>
            <dl class="mt-md grid grid-cols-2 gap-md sm:grid-cols-4">
              <div><dt class="text-caption text-ink-muted">Date</dt><dd class="text-h5 text-ink" x-text="app.interview.date || '—'"></dd></div>
              <div><dt class="text-caption text-ink-muted">Time</dt><dd class="text-h5 text-ink" x-text="app.interview.time || '—'"></dd></div>
              <div><dt class="text-caption text-ink-muted">Type</dt><dd class="text-h5 text-ink" x-text="kit.interviewTypeLabel(app.interview.type) || '—'"></dd></div>
              <div class="col-span-2 sm:col-span-1"><dt class="text-caption text-ink-muted" x-text="app.interview.type === 'online' ? 'Link' : 'Location'"></dt><dd class="break-words text-bodysm text-ink" x-text="app.interview.location_or_link || '—'"></dd></div>
            </dl>
            <p class="mt-md text-bodysm text-ink" x-show="app.interview.notes" x-text="app.interview.notes"></p>
            <template x-if="app.interview.type === 'online' && /^https?:/.test(app.interview.location_or_link || '')">
              <a :href="app.interview.location_or_link" target="_blank" rel="noopener" class="wa-btn-sm mt-md">@include('admin.partials.icon', ['name' => 'video', 'class' => 'h-4 w-4'])Join meeting</a>
            </template>
          </section>
        </template>

        {{-- What was sent --}}
        <template x-if="snapshot.length">
          <details class="wa-card group">
            <summary class="flex cursor-pointer list-none items-center justify-between text-h4 text-ink">
              <span>Submitted profile <span class="ml-xs text-caption font-normal text-ink-muted">what the employer received</span></span>
              <span x-html="ICONS.chevronDown" class="transition-transform group-open:rotate-180 [&>svg]:h-5 [&>svg]:w-5"></span>
            </summary>
            <dl class="mt-md grid grid-cols-1 gap-md text-bodysm sm:grid-cols-2">
              <template x-for="row in snapshot" :key="row[0]">
                <div class="min-w-0"><dt class="text-caption text-ink-muted" x-text="row[0]"></dt><dd class="break-words text-ink" x-text="row[1]"></dd></div>
              </template>
            </dl>
            <a x-show="app.profile_snapshot?.resume_url" :href="app.profile_snapshot?.resume_url" target="_blank" rel="noopener" class="wa-btn-ghost-sm mt-md">@include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-4 w-4'])View the resume sent</a>
          </details>
        </template>
      </div>

      {{-- Actions --}}
      <aside class="space-y-md xl:sticky xl:top-[92px] xl:self-start">
        <div class="wa-card space-y-sm">
          <a :href="'/account/messages?c=' + encodeURIComponent(app.id)" class="wa-btn w-full">@include('admin.partials.icon', ['name' => 'message', 'class' => 'h-5 w-5'])Message employer</a>
          <a :href="kit.jobUrl(app.job)" x-show="app.job?.code" class="wa-btn-ghost w-full">View job</a>
          <a href="{{ url('/account/applications') }}" class="wa-btn-quiet w-full">All applications</a>
        </div>
        <div class="wa-card">
          <p class="text-h5 text-ink">Stand out to this employer</p>
          <p class="mt-xs text-bodysm text-ink-secondary">A photo, skills and a short intro make a shortlist far more likely.</p>
          <a href="{{ url('/account') }}" class="mt-md inline-flex text-btnghost font-semibold text-primary-dark hover:underline">Improve your profile</a>
        </div>
      </aside>
    </div>
  </template>
</div>
@endsection
