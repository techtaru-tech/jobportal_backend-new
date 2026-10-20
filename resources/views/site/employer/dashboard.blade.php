@extends('site.employer.layout')

@section('title', 'Employer dashboard — Inthes')
@section('description', 'Hire nurses, lab and pharmacy staff on Inthes. Post a job, review applicants and schedule interviews from your browser.')
@section('employer_key', 'dashboard')

@php
  // The plan teaser is the same price grid the checkout shows, read server-side
  // so a signed-out visitor sees real prices.
  $catalog = json_decode(json_encode(app(\App\Services\JobPlanCatalog::class)->toApi()), true);
  $teaser = collect($catalog['tiers'] ?? [])->map(fn ($t) => $t + ['price' => $catalog['prices'][$t['id']]['district']['1'] ?? null]);
@endphp

@section('employer_guest')
<div class="space-y-xxl">
  <section class="relative overflow-hidden rounded-card border-hair border-hairline bg-surface px-xl py-xxl shadow-card lg:px-xxxl lg:py-[56px]">
    <div class="hero-wash pointer-events-none absolute inset-0"></div>
    <div class="relative grid items-center gap-xxl lg:grid-cols-[1.2fr_1fr]">
      <div>
        <p class="wa-section-title text-primary">For employers</p>
        <h1 class="mt-sm text-[30px] font-bold leading-[1.15] tracking-[-0.4px] text-ink lg:text-[38px]">Hire nurses, lab and pharmacy staff</h1>
        <p class="mt-md max-w-[520px] text-body text-ink-secondary">Post a job in minutes and reach paramedical and healthcare candidates near you. Review applicants, shortlist and schedule interviews — all from your browser or the Inthes app.</p>
        <div class="mt-xl flex flex-wrap gap-sm">
          <a href="{{ url('/employer/jobs/new') }}" class="wa-btn sheen">
            @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-5 w-5'])
            Post a job
          </a>
          <button type="button" class="wa-btn-ghost" @click="signIn('to post jobs and manage applicants')">Sign in to post a job</button>
        </div>
        <p class="mt-md text-caption text-ink-muted">Same mobile number, same account as the Inthes app.</p>
      </div>
      <div class="grid gap-sm sm:grid-cols-2">
        @foreach ([
          ['users', 'Candidates near you', 'Your post reaches job seekers in the job’s district.'],
          ['clipboard', 'Simple applicant tracking', 'Shortlist, schedule interviews and mark selections.'],
          ['message', 'Chat with applicants', 'Message candidates without sharing your number.'],
          ['shieldCheck', 'Verified employers', 'Your company badge builds trust with candidates.'],
        ] as [$icon, $title, $text])
          <div class="rounded-card border-hair border-hairline bg-surface p-lg">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-primary-light text-primary">
              @include('admin.partials.icon', ['name' => $icon, 'class' => 'h-5 w-5'])
            </span>
            <p class="mt-md text-h5 text-ink">{{ $title }}</p>
            <p class="mt-xs text-caption text-ink-secondary">{{ $text }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section>
    <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
      <div>
        <h2 class="text-h2 text-ink">Simple pricing, per job post</h2>
        <p class="mt-xs text-bodysm text-ink-secondary">Pay only when you post. Buy more postings at once and use them whenever you like.</p>
      </div>
    </div>
    <div class="grid gap-md md:grid-cols-3">
      @foreach ($teaser as $tier)
        <div class="relative flex flex-col rounded-card border-hair bg-surface p-xl shadow-card {{ !empty($tier['popular']) ? 'border-primary ring-1 ring-primary' : 'border-hairline' }}">
          @if (!empty($tier['popular']))
            <span class="absolute -top-[11px] left-xl rounded-full bg-primary px-md py-[3px] text-[10.5px] font-bold tracking-[0.6px] text-ink-onPrimary">MOST POPULAR</span>
          @endif
          <p class="text-h4 text-ink">{{ $tier['name'] }}</p>
          @if ($tier['price'])
            <p class="mt-sm flex flex-wrap items-baseline gap-sm">
              <span class="text-[28px] font-bold leading-none text-ink">{{ $tier['price']['price_label'] }}</span>
              @if (($tier['price']['mrp_paise'] ?? 0) > $tier['price']['price_paise'])
                <span class="text-bodysm text-ink-muted line-through">{{ $tier['price']['mrp_label'] }}</span>
              @endif
              <span class="text-caption text-ink-secondary">per posting</span>
            </p>
          @endif
          <ul class="mt-lg space-y-xs">
            @foreach ($tier['features'] ?? [] as $f)
              <li class="flex items-start gap-sm text-bodysm text-ink-secondary">
                <span class="mt-[3px] shrink-0 text-success">@include('admin.partials.icon', ['name' => 'check', 'class' => 'h-[14px] w-[14px]'])</span>{{ $f }}
              </li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
  </section>

  <section class="grid gap-md md:grid-cols-3">
    @foreach ([['1', 'Describe the job', 'Title, salary, shift and location — one page.'], ['2', 'Add your company', 'With your GST certificate or registration, so we can verify you.'], ['3', 'Choose a plan', 'Your post goes for a quick review, then live to candidates.']] as [$n, $t, $d])
      <div class="flex gap-md rounded-card bg-surface p-lg">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ink text-bodysm font-bold text-ink-onPrimary">{{ $n }}</span>
        <div><p class="text-h5 text-ink">{{ $t }}</p><p class="mt-xs text-caption text-ink-secondary">{{ $d }}</p></div>
      </div>
    @endforeach
  </section>
</div>
@endsection

@section('employer')
<div x-data="empDashboard()">
  <div class="mb-xl flex flex-wrap items-center justify-between gap-md">
    <div>
      <h1 class="text-h1 text-ink">Dashboard</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">Your jobs and applicants at a glance.</p>
    </div>
    <div class="flex gap-sm">
      <a href="{{ url('/employer/companies') }}" class="wa-btn-ghost-sm">@include('admin.partials.icon', ['name' => 'building', 'class' => 'h-4 w-4']) Companies</a>
      <a href="{{ url('/employer/jobs/new') }}" class="wa-btn-sm">@include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4']) Post a job</a>
    </div>
  </div>

  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && error">
    <div class="wa-card text-center">
      <p class="text-bodysm text-danger" x-text="error"></p>
      <button type="button" class="wa-btn-ghost-sm mt-md" @click="load()">Try again</button>
    </div>
  </template>

  <template x-if="!loading && !error">
    <div class="space-y-xl">
      {{-- KPIs --}}
      <div class="grid grid-cols-2 gap-md lg:grid-cols-4">
        @foreach ([
          ['Active jobs', 'activeCount', 'briefcase', '/employer/jobs?status=active'],
          ['Total applicants', 'totalApplicants', 'users', '/employer/jobs'],
          ['New this week', 'newThisWeek', 'trendingUp', null],
          ['Pending approval', 'pendingCount', 'clock', '/employer/jobs?status=pending_approval'],
        ] as [$label, $expr, $icon, $href])
          <{{ $href ? 'a href='.url($href) : 'div' }} class="wa-card !p-lg {{ $href ? 'transition-shadow hover:shadow-raised' : '' }}">
            <div class="flex items-center justify-between">
              <p class="text-bodysm text-ink-secondary">{{ $label }}</p>
              <span class="text-ink-muted">@include('admin.partials.icon', ['name' => $icon, 'class' => 'h-[18px] w-[18px]'])</span>
            </div>
            <p class="mt-sm text-stat tabular-nums text-ink" x-text="{{ $expr === 'newThisWeek' ? "applicantsLoaded ? fmtNumber(newThisWeek) : '…'" : "fmtNumber($expr)" }}"></p>
          </{{ $href ? 'a' : 'div' }}>
        @endforeach
      </div>

      {{-- First run --}}
      <template x-if="!jobs.length">
        <div class="wa-card flex flex-col items-center px-xl py-xxxl text-center">
          <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">@include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-6 w-6'])</span>
          <p class="mt-lg text-h3 text-ink">Post your first job</p>
          <p class="mt-xs max-w-[420px] text-bodysm text-ink-secondary">Describe the role, add your company and choose a plan. Applicants show up here as soon as your post is live.</p>
          <a href="{{ url('/employer/jobs/new') }}" class="wa-btn mt-xl">Post a job</a>
        </div>
      </template>

      {{-- Needs attention --}}
      <template x-if="attention.length">
        <section class="wa-card !p-0">
          <div class="flex items-center gap-sm border-b border-hairline-divider px-xl py-lg">
            <h2 class="text-h4 text-ink">Needs attention</h2>
            <span class="wa-pill bg-primary-light text-primary-dark" x-text="attention.length"></span>
          </div>
          <ul class="divide-y divide-hairline-divider">
            <template x-for="n in attention" :key="n.key">
              <li class="flex items-center gap-md px-xl py-md">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] [&>svg]:h-[18px] [&>svg]:w-[18px]" :class="toneClass(n.tone)" x-html="ICONS[n.icon]"></span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-h5 text-ink" x-text="n.title"></p>
                  <p class="text-caption text-ink-secondary" x-text="n.text"></p>
                </div>
                <a :href="n.href" class="wa-btn-ghost-sm shrink-0 !px-md" x-text="n.cta"></a>
              </li>
            </template>
          </ul>
        </section>
      </template>

      <div class="grid gap-xl xl:grid-cols-[1.5fr_1fr]" x-show="jobs.length">
        {{-- Recent jobs --}}
        <section class="wa-card min-w-0 !p-0">
          <div class="flex items-center justify-between border-b border-hairline-divider px-xl py-lg">
            <h2 class="text-h4 text-ink">Recent jobs</h2>
            <a href="{{ url('/employer/jobs') }}" class="text-btnghost text-primary hover:underline">All jobs</a>
          </div>
          <ul class="divide-y divide-hairline-divider">
            <template x-for="job in recentJobs" :key="job.id">
              <li class="flex items-center gap-md px-xl py-md hover:bg-surface-muted/60">
                <a :href="jobUrl(job)" class="min-w-0 flex-1">
                  <p class="truncate text-h5 text-ink" x-text="job.title"></p>
                  <p class="truncate text-caption text-ink-secondary" x-text="[job.organisation, job.city, 'Posted ' + day(job.posted_at)].filter(Boolean).join(' · ')"></p>
                </a>
                <span class="hidden text-right sm:block">
                  <span class="block text-h5 tabular-nums text-ink" x-text="job.applicants_count || 0"></span>
                  <span class="block text-[11px] text-ink-muted">applicants</span>
                </span>
                <span class="wa-pill shrink-0" :class="statusPill(job.posting_status)" x-text="statusLabel(job.posting_status)"></span>
                @include('site.employer.partials.job-menu', ['job' => 'job'])
              </li>
            </template>
          </ul>
        </section>

        {{-- Recent applicants --}}
        <section class="wa-card min-w-0 !p-0">
          <div class="flex items-center justify-between border-b border-hairline-divider px-xl py-lg">
            <h2 class="text-h4 text-ink">Latest applicants</h2>
          </div>
          <template x-if="!applicantsLoaded"><div class="py-xl">@include('site.ui.spinner')</div></template>
          <template x-if="applicantsLoaded && !recentApplicants.length">
            <p class="px-xl py-xxl text-center text-bodysm text-ink-secondary">No applications yet. They'll show up here as candidates apply.</p>
          </template>
          <ul class="divide-y divide-hairline-divider">
            <template x-for="a in recentApplicants" :key="a.application_id">
              <li>
                <a :href="jobUrl(a._job) + '?a=' + encodeURIComponent(a.application_id)" class="flex items-center gap-md px-xl py-md hover:bg-surface-muted/60">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-light text-caption font-bold text-primary-dark">
                    <template x-if="a.profile?.photo_url"><img :src="a.profile.photo_url" alt="" class="h-full w-full object-cover"></template>
                    <template x-if="!a.profile?.photo_url"><span x-text="initials(apName(a))"></span></template>
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-bodysm font-semibold text-ink" x-text="apName(a)"></span>
                    <span class="block truncate text-caption text-ink-secondary" x-text="'For ' + a._job.title + ' · ' + timeAgo(a.applied_at)"></span>
                  </span>
                  <span class="wa-pill shrink-0" :class="appPill(a.status)" x-text="appLabel(a.status)"></span>
                </a>
              </li>
            </template>
          </ul>
        </section>
      </div>

      <section class="flex flex-col items-start gap-md rounded-card bg-surface-dark p-xl text-ink-onPrimary sm:flex-row sm:items-center">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[12px] bg-white/10">@include('admin.partials.icon', ['name' => 'smartphone', 'class' => 'h-5 w-5'])</span>
        <div class="flex-1">
          <p class="text-h4">Get applicant alerts on your phone</p>
          <p class="text-bodysm text-white/70">The Inthes app notifies you the moment someone applies.</p>
        </div>
        <a :href="storeUrl()" class="inline-flex h-10 items-center rounded-button bg-surface px-lg text-btnghost font-semibold text-ink hover:bg-surface-muted">Get the app</a>
      </section>
    </div>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-dashboard'])
@endsection
