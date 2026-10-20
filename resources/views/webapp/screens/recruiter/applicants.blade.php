{{-- Applicants (recruiter/applicants/views/applicants_view.dart + applicant_card / applicant_filter_sheet). --}}
<template x-if="is('rApplicants')">
  <div class="wa-page">
    <div class="mb-lg flex items-center gap-md">
      <button type="button" @click="back('/r/jobs')" aria-label="Back" class="-ml-sm inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
      </button>
      <div class="min-w-0 flex-1">
        <h1 class="truncate text-h3 text-ink" x-text="rAppl.job?.title || 'Applicants'"></h1>
        <p class="truncate text-caption text-ink-secondary" x-show="rAppl.job"
           x-text="rApplFiltered.length + ' applicant' + (rApplFiltered.length === 1 ? '' : 's') + ' · ' + (rAppl.job?.organisation || '')"></p>
      </div>
    </div>

    <template x-if="rAppl.missing">
      <div>@include('webapp.ui.empty', ['icon' => 'users', 'title' => 'Applicants', 'text' => 'This posting is no longer available.'])</div>
    </template>

    <template x-if="!rAppl.missing">
      <div>
        <div class="flex gap-sm">
          <div class="relative flex-1">
            <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[18px] w-[18px]'])</span>
            <input type="search" x-model="rApplQuery" placeholder="Search applicant…" class="wa-input pl-[42px]">
          </div>
          <button type="button" @click="rOpenApplFilters()" aria-label="Filter Applicants" title="Filter Applicants"
                  class="relative inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-field bg-surface-muted text-ink hover:bg-hairline">
            @include('admin.partials.icon', ['name' => 'sliders', 'class' => 'h-5 w-5'])
            <span x-show="rApplFilterCount > 0" class="absolute right-[6px] top-[6px] h-2 w-2 rounded-full bg-primary"></span>
          </button>
          <button type="button" @click="rApplSheet = 'sort'" aria-label="Sort by" title="Sort by"
                  class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-field bg-surface-muted text-ink hover:bg-hairline">
            @include('admin.partials.icon', ['name' => 'filter', 'class' => 'h-5 w-5'])
          </button>
        </div>

        <div class="no-scrollbar -mx-page mt-md flex gap-sm overflow-x-auto px-page lg:mx-0 lg:px-0">
          <template x-for="s in ['', ...R_APP_STATUSES]" :key="s || 'all'">
            <button type="button" @click="rApplStatus = s" :class="rApplStatus === s && 'wa-chip-on'" class="wa-chip shrink-0 py-[6px]" x-text="s ? rAppLabel(s) : 'All'"></button>
          </template>
        </div>

        <template x-if="rAppl.loading">
          <div>@include('webapp.ui.spinner')</div>
        </template>

        <template x-if="!rAppl.loading && rApplFiltered.length === 0">
          <div class="mt-xl">
            <div class="flex flex-col items-center justify-center rounded-card border-hair border-dashed border-hairline-strong bg-surface px-xl py-xxxl text-center">
              <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">@include('admin.partials.icon', ['name' => 'users', 'class' => 'h-6 w-6'])</span>
              <p class="mt-lg text-h4 text-ink" x-text="rApplHasFilters ? 'No Candidates Found' : 'No Applications Yet'"></p>
              <p class="mt-xs max-w-[360px] text-bodysm text-ink-secondary" x-text="rApplHasFilters ? 'Try changing your filters.' : 'Your applicants will appear here when candidates apply for this job.'"></p>
              <button type="button" x-show="rApplHasFilters" class="wa-btn-ghost-sm mt-lg" @click="rClearApplFilters()">Clear Filters</button>
            </div>
          </div>
        </template>

        <div class="mt-md grid gap-md lg:grid-cols-2" x-show="!rAppl.loading">
          <template x-for="a in rApplFiltered" :key="a.application_id">
            <div class="wa-card cursor-pointer transition-shadow hover:shadow-raised" @click="go('/r/applicant/' + encodeURIComponent(a.job_id) + '/' + encodeURIComponent(a.application_id))">
              <div class="flex items-start gap-md">
                <span class="flex h-[46px] w-[46px] shrink-0 items-center justify-center overflow-hidden rounded-[12px] bg-primary-light text-h5 text-primary-dark">
                  <template x-if="a.profile?.photo_url"><img :src="a.profile.photo_url" alt="" class="h-full w-full object-cover"></template>
                  <template x-if="!a.profile?.photo_url"><span x-text="initials(rApName(a))"></span></template>
                </span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-h4 text-ink" x-text="rApName(a)"></p>
                  <p class="truncate text-bodysm text-ink-secondary" x-text="rApDesignation(a)"></p>
                  <p class="mt-[2px] flex items-center gap-[2px] truncate text-caption text-ink-secondary">
                    <span x-html="ICONS.mapPin" class="shrink-0 text-ink-muted [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
                    <span x-text="rApLocation(a)"></span>
                  </p>
                </div>
                <span class="flex shrink-0 items-center gap-[5px] text-caption text-ink-secondary">
                  <span class="h-[7px] w-[7px] rounded-full" :class="rAppDot(a.status)"></span>
                  <span x-text="rAppLabel(a.status)"></span>
                </span>
              </div>
              <div class="mt-md flex flex-wrap gap-sm">
                <span class="wa-tag" x-show="a.profile?.experience" x-text="a.profile?.experience"></span>
                <span class="wa-tag" x-show="a.profile?.qualification" x-text="a.profile?.qualification"></span>
              </div>
              <div class="mt-md flex items-center gap-[6px]">
                <span class="text-caption text-ink-secondary">Profile strength</span>
                <span class="h-[5px] flex-1 overflow-hidden rounded-[3px] bg-hairline"><span class="block h-full bg-primary" :style="'width:' + rApStrength(a) + '%'"></span></span>
                <span class="text-caption font-bold text-ink" x-text="rApStrength(a) + '%'"></span>
              </div>
              <p class="mt-[6px] text-caption text-ink-muted" x-text="'Applied ' + rDay(a.applied_at)"></p>
              <button type="button" class="wa-btn-ghost-sm mt-md h-[42px] w-full" @click.stop="go('/r/applicant/' + encodeURIComponent(a.job_id) + '/' + encodeURIComponent(a.application_id))">View Profile</button>
            </div>
          </template>
        </div>
      </div>
    </template>

    {{-- Sort by --}}
    <div x-show="rApplSheet === 'sort'" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
      <div @click.outside="rApplSheet = ''" class="w-full max-w-[440px] rounded-t-sheet bg-surface px-sm pb-lg pt-lg shadow-raised sm:rounded-dialog">
        <h3 class="px-md text-h4 text-ink">Sort by</h3>
        <div class="mt-sm">
          <template x-for="s in R_SORTS" :key="s.id">
            <button type="button" @click="rApplSort = s.id; rApplSheet = ''" class="flex w-full items-center rounded-field px-md py-md text-left text-bodysm font-semibold text-ink hover:bg-surface-muted">
              <span class="flex-1" x-text="s.label"></span>
              <span x-show="rApplSort === s.id" x-html="ICONS.check" class="text-primary [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            </button>
          </template>
        </div>
      </div>
    </div>

    {{-- Filter Applicants — facets from the applicants actually on this job --}}
    <div x-show="rApplSheet === 'filter'" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
      <div @click.outside="rApplSheet = ''" class="flex max-h-[88vh] w-full max-w-[520px] flex-col rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog">
        <div class="flex items-center justify-between">
          <h3 class="text-h2 text-ink">Filter Applicants</h3>
          <button type="button" class="wa-btn-quiet text-primary" @click="rApplDraft = { experience: [], qualification: [], location: [] }">Reset</button>
        </div>
        <div class="thin-scrollbar -mx-xs mt-sm flex-1 overflow-y-auto px-xs">
          <template x-for="[key, title] in [['experience', 'Experience'], ['qualification', 'Qualification'], ['location', 'Location']]" :key="key">
            <div x-show="rApplFacets[key].length" class="mt-xl">
              <p class="wa-section-title mb-sm" x-text="title"></p>
              <div class="flex flex-wrap gap-xs">
                <template x-for="item in rApplFacets[key]" :key="item">
                  <button type="button" @click="toggleIn(rApplDraft[key], item)" :class="rApplDraft[key].includes(item) && 'wa-chip-on'" class="wa-chip">
                    <span x-show="rApplDraft[key].includes(item)" x-html="ICONS.check" class="text-primary [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
                    <span x-text="item"></span>
                  </button>
                </template>
              </div>
            </div>
          </template>
        </div>
        <button type="button" class="wa-btn mt-xl w-full" @click="rApplyDraft()" x-text="'Show ' + rApplyApplicantFilters(rApplDraft).length + ' candidates'"></button>
      </div>
    </div>
  </div>
</template>
