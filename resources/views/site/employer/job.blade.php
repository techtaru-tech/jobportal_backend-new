@extends('site.employer.layout')

@section('title', 'Job applicants — Inthes employer')
@section('employer_key', 'jobs')

@php
  $tabBtn = 'relative -mb-px inline-flex items-center gap-xs border-b-2 px-xs pb-md pt-xs text-bodysm font-semibold transition-colors';
  $th = 'px-md py-md font-semibold';
@endphp

@section('employer')
<div x-data="empJob(@js(['jobId' => $jobId]))" @keydown.escape.window="interview.open ? interview.open = false : (facetOpen ? facetOpen = '' : close())">
  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && loadError">
    <div>
      @include('site.ui.empty', ['icon' => 'briefcase', 'title' => 'Job not available', 'text' => 'This posting is no longer available, or it belongs to another account.'])
      <div class="mt-lg text-center"><a href="{{ url('/employer/jobs') }}" class="wa-btn-ghost-sm">Back to jobs</a></div>
    </div>
  </template>

  <template x-if="!loading && job">
    <div>
      {{-- ── header ─────────────────────────────────────────────────── --}}
      <a href="{{ url('/employer/jobs') }}" class="mb-sm inline-flex items-center gap-[2px] text-caption text-ink-secondary hover:text-ink">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-4 w-4']) Jobs
      </a>
      <div class="wa-card !p-lg sm:!p-xl">
        <div class="flex flex-wrap items-start gap-md">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-sm">
              <h1 class="text-h1 text-ink" x-text="job.title"></h1>
              <span class="wa-pill" :class="statusPill(job.posting_status)" x-text="statusLabel(job.posting_status)"></span>
            </div>
            <p class="mt-xs flex flex-wrap items-center gap-x-sm gap-y-[2px] text-bodysm text-ink-secondary">
              <span class="inline-flex items-center gap-xs"><span x-text="job.organisation"></span>
                <span x-show="job.organisation_verified" class="text-primary" title="Verified company">@include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-[14px] w-[14px]'])</span></span>
              <span class="text-ink-muted">·</span><span x-text="job.city"></span>
              <span class="text-ink-muted">·</span><span class="font-mono text-caption" x-text="job.code"></span>
            </p>
            <div class="mt-sm flex flex-wrap gap-xs">
              <span x-show="salaryOf(job)" class="wa-tag bg-primary-light font-semibold text-primary-dark" x-text="salaryOf(job)"></span>
              <span x-show="job.type" class="wa-tag" x-text="job.type"></span>
              <span x-show="job.shift" class="wa-tag" x-text="job.shift"></span>
              <span x-show="job.experience" class="wa-tag" x-text="job.experience"></span>
              <span class="wa-tag" x-text="job.hiring_stage_label"></span>
            </div>
          </div>
          <div class="flex items-center gap-xs">
            <template x-if="awaitingPayment(job)">
              <a :href="noticeHref(job, 'plan')" class="wa-btn-sm">Choose plan</a>
            </template>
            <template x-if="canEdit(job)">
              <a :href="jobUrl(job) + '/edit'" class="wa-btn-ghost-sm !px-md">@include('admin.partials.icon', ['name' => 'pencil', 'class' => 'h-4 w-4']) <span class="hidden sm:inline">Edit</span></a>
            </template>
            <template x-if="canToggle(job)">
              <button type="button" class="wa-btn-ghost-sm !px-md" :disabled="!!jobBusy" @click="jobToggle(job)">
                <span x-html="job.posting_status === 'active' ? ICONS.pause : ICONS.play" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                <span class="hidden sm:inline" x-text="job.posting_status === 'active' ? 'Pause' : 'Resume'"></span>
              </button>
            </template>
            @include('site.employer.partials.job-menu', ['job' => 'job', 'hideApplicants' => true])
          </div>
        </div>

        <template x-for="n in noticesOf(job)" :key="n.message">
          <div class="mt-md flex items-center gap-sm rounded-field border-hair px-md py-sm" :class="noticeClass(n.tone)">
            <span x-html="ICONS[n.icon]" class="shrink-0 [&>svg]:h-[15px] [&>svg]:w-[15px]"></span>
            <p class="flex-1 text-caption leading-[1.4]" x-text="n.message"></p>
            <template x-if="n.action"><a :href="noticeHref(job, n.action)" class="shrink-0 px-sm text-caption font-bold hover:underline" x-text="n.label"></a></template>
          </div>
        </template>

        <dl class="mt-lg grid grid-cols-2 gap-md border-t border-hairline-divider pt-lg sm:grid-cols-4">
          <div><dt class="text-caption text-ink-secondary">Applicants</dt><dd class="text-h2 tabular-nums text-ink" x-text="total"></dd></div>
          <div><dt class="text-caption text-ink-secondary">Shortlisted</dt><dd class="text-h2 tabular-nums text-ink" x-text="stats?.by_status?.shortlisted ?? statusCount('shortlisted')"></dd></div>
          <div><dt class="text-caption text-ink-secondary">Selected</dt><dd class="text-h2 tabular-nums text-ink" x-text="stats?.by_status?.selected ?? statusCount('selected')"></dd></div>
          <div><dt class="text-caption text-ink-secondary">Posted</dt><dd class="mt-[5px] text-h5 text-ink" x-text="day(job.posted_at)"></dd></div>
        </dl>
      </div>

      {{-- ── tabs ───────────────────────────────────────────────────── --}}
      <nav class="mt-xl flex gap-xl border-b border-hairline">
        <button type="button" @click="setTab('applicants')" class="{{ $tabBtn }}" :class="tab === 'applicants' ? 'border-primary text-ink' : 'border-transparent text-ink-secondary hover:text-ink'">
          Applicants <span class="wa-pill bg-surface-muted text-ink-secondary" x-text="list.length"></span>
        </button>
        <button type="button" @click="setTab('stats')" class="{{ $tabBtn }}" :class="tab === 'stats' ? 'border-primary text-ink' : 'border-transparent text-ink-secondary hover:text-ink'">Stats</button>
        <button type="button" @click="setTab('details')" class="{{ $tabBtn }}" :class="tab === 'details' ? 'border-primary text-ink' : 'border-transparent text-ink-secondary hover:text-ink'">Job details</button>
      </nav>

      {{-- ── applicants ─────────────────────────────────────────────── --}}
      <section x-show="tab === 'applicants'" class="pt-lg">
        {{-- toolbar --}}
        <div class="flex flex-col gap-md">
          <nav class="no-scrollbar -mx-page flex gap-xs overflow-x-auto px-page lg:mx-0 lg:px-0">
            <template x-for="s in ['', ...APP_STATUSES]" :key="s">
              <button type="button" @click="status = s; selected = []"
                      :class="status === s ? 'border-ink bg-ink text-ink-onPrimary' : 'border-hairline bg-surface text-ink-secondary hover:text-ink'"
                      class="inline-flex shrink-0 items-center gap-xs rounded-full border-hair px-md py-[6px] text-bodysm font-medium transition-colors">
                <span x-text="s ? appLabel(s) : 'All'"></span><span class="tabular-nums opacity-70" x-text="statusCount(s)"></span>
              </button>
            </template>
          </nav>
          <div class="flex flex-wrap items-center gap-sm">
            <label class="relative block min-w-[200px] flex-1 lg:max-w-[300px]">
              <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[17px] w-[17px]'])</span>
              <input type="search" x-model.debounce.150ms="q" placeholder="Search name, role, city" class="wa-input h-10 bg-surface pl-[38px]">
            </label>
            <template x-for="fc in FACETS" :key="fc.key">
              <div class="relative" @click.outside="facetOpen === fc.key && (facetOpen = '')">
                <button type="button" @click="facetOpen = facetOpen === fc.key ? '' : fc.key"
                        :class="filters[fc.key].length ? 'border-primary bg-primary-light text-primary-dark' : 'border-hairline bg-surface text-ink-secondary hover:text-ink'"
                        class="inline-flex h-10 items-center gap-xs rounded-field border-hair px-md text-bodysm font-medium">
                  <span x-text="fc.label"></span>
                  <span x-show="filters[fc.key].length" class="wa-pill bg-primary text-ink-onPrimary" x-text="filters[fc.key].length"></span>
                  <span x-html="ICONS.chevronDown" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </button>
                <div x-show="facetOpen === fc.key" x-cloak class="absolute left-0 top-11 z-40 max-h-[300px] w-[240px] overflow-y-auto rounded-card border-hair border-hairline bg-surface p-sm shadow-raised animate-pop-in">
                  <template x-if="!facets[fc.key].length"><p class="px-sm py-sm text-caption text-ink-muted">No values yet</p></template>
                  <template x-for="v in facets[fc.key]" :key="v">
                    <label class="flex cursor-pointer items-center gap-sm rounded-field px-sm py-[7px] text-bodysm text-ink hover:bg-surface-muted">
                      <input type="checkbox" class="h-4 w-4 accent-[#EB0401]" :checked="filters[fc.key].includes(v)" @change="toggleIn(filters[fc.key], v); selected = []">
                      <span class="flex-1" x-text="v"></span>
                      <span class="text-caption text-ink-muted" x-text="list.filter((a) => (fc.key === 'location' ? apLocation(a) : a.profile?.[fc.key]) === v).length"></span>
                    </label>
                  </template>
                  <button type="button" x-show="filters[fc.key].length" class="mt-xs w-full rounded-field px-sm py-[6px] text-left text-caption font-semibold text-primary hover:bg-surface-muted" @click="filters[fc.key] = []">Clear</button>
                </div>
              </div>
            </template>
            <label class="relative ml-auto">
              <span class="sr-only">Sort</span>
              <select x-model="sort" class="h-10 cursor-pointer appearance-none rounded-field border-hair border-hairline bg-surface pl-md pr-9 text-bodysm text-ink outline-none focus:border-primary">
                <template x-for="s in SORTS" :key="s.id"><option :value="s.id" x-text="s.label" :selected="s.id === sort"></option></template>
              </select>
              <span class="pointer-events-none absolute right-sm top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-4 w-4'])</span>
            </label>
          </div>
          <template x-if="hasFilters">
            <p class="text-caption text-ink-secondary">
              <span x-text="rows.length + ' of ' + list.length + ' applicants'"></span>
              <button type="button" class="ml-sm font-semibold text-primary hover:underline" @click="clearFilters()">Clear filters</button>
            </p>
          </template>
        </div>

        {{-- bulk bar --}}
        <div x-show="selected.length" x-cloak x-transition.opacity class="sticky top-[76px] z-30 mt-md flex flex-wrap items-center gap-sm rounded-card bg-surface-dark px-lg py-sm text-ink-onPrimary shadow-raised">
          <span class="text-bodysm font-semibold" x-text="selected.length + ' selected'"></span>
          <span class="flex-1"></span>
          <button type="button" class="inline-flex h-9 items-center gap-xs rounded-button bg-primary px-md text-btnghost font-semibold hover:bg-primary-dark disabled:opacity-60" :disabled="bulkBusy" @click="bulk('shortlisted')">
            <span x-html="bulkBusy ? ICONS.loader : ICONS.check" :class="bulkBusy && 'animate-spin'" class="[&>svg]:h-4 [&>svg]:w-4"></span> Shortlist
          </button>
          <button type="button" class="inline-flex h-9 items-center gap-xs rounded-button bg-white/10 px-md text-btnghost font-semibold hover:bg-white/20 disabled:opacity-60" :disabled="bulkBusy" @click="bulk('rejected')">
            <span x-html="ICONS.xCircle" class="[&>svg]:h-4 [&>svg]:w-4"></span> Reject
          </button>
          <button type="button" class="inline-flex h-9 items-center rounded-button px-sm text-btnghost text-white/70 hover:text-white" @click="selected = []">Clear</button>
        </div>

        <div class="mt-md">
          <template x-if="listLoading">@include('site.ui.spinner')</template>
          <template x-if="!listLoading && listError">
            <div class="wa-card text-center"><p class="text-bodysm text-danger" x-text="listError"></p><button type="button" class="wa-btn-ghost-sm mt-md" @click="loadApplicants()">Try again</button></div>
          </template>
          <template x-if="!listLoading && !listError && !list.length">
            <div>@include('site.ui.empty', ['icon' => 'users', 'title' => 'No applicants yet', 'text' => 'Candidates who apply to this job appear here. You will also get a notification in the Inthes app.'])</div>
          </template>
          <template x-if="!listLoading && list.length && !rows.length">
            <div class="wa-card text-center">
              <p class="text-h5 text-ink">No applicants match</p>
              <button type="button" class="wa-btn-ghost-sm mt-md" @click="clearFilters()">Clear filters</button>
            </div>
          </template>

          <template x-if="!listLoading && rows.length">
            <div>
              {{-- desktop table --}}
              <div class="hidden overflow-x-auto rounded-card border-hair border-hairline bg-surface shadow-card md:block">
                <table class="w-full min-w-[760px] text-left">
                  <thead class="border-b border-hairline bg-surface-muted/60 text-caption uppercase tracking-[0.4px] text-ink-secondary">
                    <tr>
                      <th class="w-10 py-md pl-lg pr-xs"><input type="checkbox" class="h-4 w-4 accent-[#EB0401]" :checked="allSelected" @change="toggleAll()" aria-label="Select all"></th>
                      <th class="{{ $th }}">Candidate</th>
                      <th class="{{ $th }} hidden xl:table-cell">Current role</th>
                      <th class="{{ $th }}">Experience</th>
                      <th class="{{ $th }}">Qualification</th>
                      <th class="{{ $th }} hidden lg:table-cell">Location</th>
                      <th class="{{ $th }} hidden lg:table-cell">Strength</th>
                      <th class="{{ $th }}">Applied</th>
                      <th class="{{ $th }}">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-hairline-divider">
                    <template x-for="a in rows" :key="a.application_id">
                      <tr class="cursor-pointer text-bodysm" @click="open(a)"
                          :class="openId === a.application_id ? 'bg-primary-light/60' : (isSelected(a.application_id) ? 'bg-surface-muted' : 'hover:bg-surface-muted/60')">
                        <td class="py-md pl-lg pr-xs" @click.stop><input type="checkbox" class="h-4 w-4 accent-[#EB0401]" :checked="isSelected(a.application_id)" @change="toggleSelect(a.application_id)" :aria-label="'Select ' + apName(a)"></td>
                        <td class="px-md py-md">
                          <div class="flex items-center gap-sm">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-light text-[11px] font-bold text-primary-dark">
                              <template x-if="a.profile?.photo_url"><img :src="a.profile.photo_url" alt="" class="h-full w-full object-cover"></template>
                              <template x-if="!a.profile?.photo_url"><span x-text="initials(apName(a))"></span></template>
                            </span>
                            <span class="min-w-0">
                              <span class="block truncate font-semibold text-ink" x-text="apName(a)"></span>
                              <span class="block truncate text-caption text-ink-muted xl:hidden" x-text="apDesignation(a) || '—'"></span>
                              <span x-show="a.interview" class="mt-[1px] flex items-center gap-[3px] text-[11px] font-medium text-info">
                                <span x-html="ICONS.calendar" class="[&>svg]:h-3 [&>svg]:w-3"></span><span x-text="'Interview ' + interviewDate(a.interview)"></span>
                              </span>
                            </span>
                          </div>
                        </td>
                        <td class="hidden px-md py-md text-ink-secondary xl:table-cell" x-text="apDesignation(a) || '—'"></td>
                        <td class="whitespace-nowrap px-md py-md text-ink-secondary" x-text="a.profile?.experience || '—'"></td>
                        <td class="px-md py-md text-ink-secondary" x-text="a.profile?.qualification || '—'"></td>
                        <td class="hidden px-md py-md text-ink-secondary lg:table-cell" x-text="apLocation(a) || '—'"></td>
                        <td class="hidden px-md py-md lg:table-cell">
                          <span class="flex items-center gap-sm">
                            <span class="block h-[5px] w-12 overflow-hidden rounded-full bg-hairline"><span class="block h-full rounded-full bg-primary" :style="'width:' + apStrength(a) + '%'"></span></span>
                            <span class="text-caption tabular-nums text-ink-secondary" x-text="apStrength(a) + '%'"></span>
                          </span>
                        </td>
                        <td class="whitespace-nowrap px-md py-md text-ink-secondary" x-text="day(a.applied_at)"></td>
                        <td class="px-md py-md"><span class="wa-pill whitespace-nowrap" :class="appPill(a.status)" x-text="appLabel(a.status)"></span></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

              {{-- phone cards --}}
              <div class="space-y-sm md:hidden">
                <div class="flex items-center gap-sm px-xs">
                  <label class="inline-flex items-center gap-sm text-caption text-ink-secondary"><input type="checkbox" class="h-4 w-4 accent-[#EB0401]" :checked="allSelected" @change="toggleAll()"> Select all</label>
                </div>
                <template x-for="a in rows" :key="a.application_id">
                  <div class="flex gap-sm rounded-card border-hair bg-surface p-md shadow-card" :class="isSelected(a.application_id) ? 'border-primary' : 'border-hairline'">
                    <input type="checkbox" class="mt-[3px] h-4 w-4 shrink-0 accent-[#EB0401]" :checked="isSelected(a.application_id)" @change="toggleSelect(a.application_id)" :aria-label="'Select ' + apName(a)">
                    <button type="button" class="min-w-0 flex-1 text-left" @click="open(a)">
                      <span class="flex items-start justify-between gap-sm">
                        <span class="min-w-0">
                          <span class="block truncate text-h5 text-ink" x-text="apName(a)"></span>
                          <span class="block truncate text-caption text-ink-secondary" x-text="[apDesignation(a), apLocation(a)].filter(Boolean).join(' · ') || '—'"></span>
                        </span>
                        <span class="wa-pill shrink-0" :class="appPill(a.status)" x-text="appLabel(a.status)"></span>
                      </span>
                      <span class="mt-sm flex flex-wrap gap-xs">
                        <span x-show="a.profile?.experience" class="wa-tag" x-text="a.profile?.experience"></span>
                        <span x-show="a.profile?.qualification" class="wa-tag" x-text="a.profile?.qualification"></span>
                        <span class="wa-tag" x-text="apStrength(a) + '% profile'"></span>
                      </span>
                      <span class="mt-sm block text-caption text-ink-muted" x-text="'Applied ' + day(a.applied_at)"></span>
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </template>
        </div>
      </section>

      {{-- ── stats ──────────────────────────────────────────────────── --}}
      <section x-show="tab === 'stats'" x-cloak class="grid gap-lg pt-lg lg:grid-cols-2">
        <div class="wa-card">
          <p class="wa-section-title">Applicants</p>
          <p class="mt-xs text-[34px] font-bold leading-tight tabular-nums text-primary" x-text="total"></p>
          <p class="text-bodysm text-ink-secondary">Total applications received</p>
          <div class="mt-lg space-y-md">
            <template x-for="s in APP_STATUSES" :key="s">
              <div class="flex items-center gap-sm">
                <span class="w-[100px] text-bodysm text-ink-secondary" x-text="appLabel(s)"></span>
                <span class="h-[8px] flex-1 overflow-hidden rounded-full bg-hairline">
                  <span class="block h-full rounded-full" :class="Emp.APP_DOT[s]" :style="'width:' + (total ? ((stats?.by_status?.[s] ?? statusCount(s)) / total * 100) : 0) + '%'"></span>
                </span>
                <span class="w-8 text-right text-bodysm font-bold tabular-nums text-ink" x-text="stats?.by_status?.[s] ?? statusCount(s)"></span>
              </div>
            </template>
          </div>
        </div>
        <div class="wa-card">
          <p class="wa-section-title">Pipeline</p>
          <ul class="mt-md space-y-md text-bodysm">
            <li class="flex justify-between"><span class="text-ink-secondary">Interviews scheduled</span><span class="font-semibold text-ink" x-text="list.filter((a) => a.interview).length"></span></li>
            <li class="flex justify-between"><span class="text-ink-secondary">Applied this week</span><span class="font-semibold text-ink" x-text="list.filter((a) => Date.now() - new Date(a.applied_at) < 7 * 864e5).length"></span></li>
            <li class="flex justify-between"><span class="text-ink-secondary">Openings</span><span class="font-semibold text-ink" x-text="job.openings || '—'"></span></li>
            <li class="flex justify-between"><span class="text-ink-secondary">Hiring stage</span><span class="font-semibold text-ink" x-text="job.hiring_stage_label"></span></li>
          </ul>
        </div>
      </section>

      {{-- ── job details ────────────────────────────────────────────── --}}
      <section x-show="tab === 'details'" x-cloak class="grid gap-lg pt-lg lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-lg">
          <div class="wa-card">
            <p class="wa-section-title mb-sm">Job description</p>
            <p x-show="job.about" class="whitespace-pre-line text-body text-ink" x-text="job.about"></p>
            <p x-show="!job.about" class="text-bodysm text-ink-muted">No description added.</p>
            <template x-if="(job.duties || []).length">
              <div class="mt-lg">
                <p class="text-h5 text-ink">Responsibilities</p>
                <ul class="mt-xs list-disc space-y-[6px] pl-lg text-bodysm text-ink marker:text-ink-muted"><template x-for="d in job.duties" :key="d"><li x-text="d"></li></template></ul>
              </div>
            </template>
            <template x-if="(job.benefits || []).length">
              <div class="mt-lg">
                <p class="text-h5 text-ink">Benefits</p>
                <ul class="mt-xs list-disc space-y-[6px] pl-lg text-bodysm text-ink marker:text-ink-muted"><template x-for="d in job.benefits" :key="d"><li x-text="d"></li></template></ul>
              </div>
            </template>
          </div>
          <template x-if="(job.skills || []).length">
            <div class="wa-card">
              <p class="wa-section-title mb-sm">Skills</p>
              <div class="flex flex-wrap gap-xs"><template x-for="s in job.skills" :key="s"><span class="wa-tag" x-text="s"></span></template></div>
            </div>
          </template>
        </div>
        <div class="wa-card !py-xs">
          <template x-for="row in [
              ['Job title', job.title], ['Category', job.role], ['Openings', job.openings], ['Salary', salaryOf(job)],
              ['Job type', job.type], ['Shift', job.shift], ['Location', [job.locality, job.city, job.state].filter(Boolean).join(', ')],
              ['Experience', job.experience], ['Minimum education', job.min_education],
              ['Qualification', (job.qualifications || []).join(' / ')], ['Preferred gender', job.preferred_gender],
              ['Candidate fee', job.charges_fee == null ? '' : (job.charges_fee ? 'Yes' : 'No fee')],
              ['Contact', ($store.opts.data?.contact_modes || {})[job.contact_mode] || ''], ['Contact number', job.contact_phone],
              ['Plan', job.plan_tier ? job.plan_tier.charAt(0).toUpperCase() + job.plan_tier.slice(1) : ''], ['Posted', day(job.posted_at)]]" :key="row[0]">
            <div class="flex gap-md border-b border-hairline-divider py-[10px] last:border-0">
              <span class="flex-1 text-bodysm text-ink-secondary" x-text="row[0]"></span>
              <span class="flex-1 text-right text-bodysm font-semibold text-ink" x-text="row[1] || '—'"></span>
            </div>
          </template>
        </div>
      </section>
    </div>
  </template>

  @include('site.employer.partials.applicant-drawer')
</div>
@include('site.employer.partials.script', ['name' => 'employer-job'])
@endsection
