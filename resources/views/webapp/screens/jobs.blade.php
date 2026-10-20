{{-- Job seeker: Home, Jobs (results), Search, Job detail, and the filter sheet. --}}

{{-- ── Home ────────────────────────────────────────────────────────── --}}
<template x-if="is('home')">
  <div class="wa-page space-y-xl">
    <section class="flex flex-col gap-lg rounded-card bg-primary p-xl text-ink-onPrimary shadow-button lg:flex-row lg:items-center lg:p-xxl">
      <div class="flex-1">
        <p class="text-bodysm opacity-90" x-text="token ? 'Hi ' + firstName + ' 👋' : 'Hi there 👋'"></p>
        <h1 class="mt-xs text-h1">Find your next healthcare job</h1>
        <p class="mt-xs flex items-center gap-xs text-bodysm opacity-90" x-show="me?.home_city || (me?.location || []).length">
          <span x-html="ICONS.mapPin" class="[&>svg]:h-4 [&>svg]:w-4"></span>
          <span x-text="me?.home_city || (me?.location || [])[0]"></span>
        </p>
      </div>
      <a href="#/search" class="flex h-[50px] w-full items-center gap-md rounded-field bg-surface px-lg text-input text-ink-muted shadow-card lg:max-w-[460px]">
        <span x-html="ICONS.search" class="text-ink-secondary [&>svg]:h-5 [&>svg]:w-5"></span>
        Search jobs, employers, roles…
      </a>
    </section>

    {{-- Profile nudge --}}
    <template x-if="token && me && (me.profile_strength ?? 0) < 100">
      <a href="#/profile" class="wa-card flex items-center gap-lg !p-lg hover:shadow-raised">
        <span class="relative inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-light text-[13px] font-bold text-primary-dark" x-text="(me.profile_strength ?? 0) + '%'"></span>
        <span class="flex-1">
          <span class="block text-h5 text-ink">Profile <span x-text="(me.profile_strength ?? 0) + '%'"></span> complete</span>
          <span class="block text-bodysm text-ink-secondary">A complete profile gets better matches and lets you apply in one tap.</span>
        </span>
        <span class="hidden text-btnghost font-semibold text-primary-dark sm:block">View your profile</span>
      </a>
    </template>

    <section>
      <div class="flex items-center justify-between gap-md">
        <h2 class="wa-section-title">BROWSE BY ROLE</h2>
        <button @click="openFilters()" class="wa-btn-quiet !h-9">
          <span x-html="ICONS.sliders" class="[&>svg]:h-4 [&>svg]:w-4"></span>
          <span x-text="activeFilterCount ? 'Filters (' + activeFilterCount + ')' : 'Filters'"></span>
        </button>
      </div>
      <div class="no-scrollbar mt-md flex gap-xs overflow-x-auto pb-xs">
        <button @click="homeCategory = ''; loadHomeFeed()" :class="!homeCategory && 'wa-chip-on'" class="wa-chip shrink-0">All</button>
        <template x-for="c in jobCategories" :key="c.name">
          <button @click="pickHomeCategory(c.name)" :class="homeCategory === c.name && 'wa-chip-on'" class="wa-chip shrink-0">
            <span x-text="c.name"></span><span class="text-ink-muted" x-text="c.job_count"></span>
          </button>
        </template>
      </div>
    </section>

    <section>
      <div class="flex items-center justify-between">
        <h2 class="text-h3 text-ink" x-text="homeTitle"></h2>
        <a :href="'#/jobs' + (homeCategory ? '?category=' + encodeURIComponent(homeCategory) : '')" class="text-btnghost font-semibold text-primary-dark hover:underline">View All</a>
      </div>
      <template x-if="homeFeed.busy && !homeFeed.list.length">@include('webapp.ui.spinner')</template>
      <template x-if="homeFeed.error && !homeFeed.busy">
        <div class="mt-md">@include('webapp.ui.empty', ['icon' => 'alert', 'title' => 'Something went wrong', 'text' => 'Check your connection and try again.'])</div>
      </template>
      <template x-if="!homeFeed.busy && !homeFeed.error && !homeFeed.list.length">
        <div class="mt-md">@include('webapp.ui.empty', ['icon' => 'briefcase', 'title' => 'No jobs found', 'text' => 'No openings match this role yet.'])</div>
      </template>
      <div class="mt-md grid gap-md md:grid-cols-2 xl:grid-cols-3">
        <template x-for="job in homeFeed.list" :key="job.id">@include('webapp.screens.jobs.card')</template>
      </div>
    </section>

    {{-- Web only: the app itself. --}}
    <section class="wa-card flex flex-col items-start gap-md sm:flex-row sm:items-center">
      <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-card bg-primary-light text-primary">
        @include('admin.partials.icon', ['name' => 'smartphone', 'class' => 'h-6 w-6'])
      </span>
      <div class="flex-1">
        <p class="text-h5 text-ink">Get the Inthes app</p>
        <p class="text-bodysm text-ink-secondary">Instant job alerts and chat notifications on your phone. Same account, same applications.</p>
      </div>
      <a :href="playStoreUrl" class="wa-btn-ghost-sm">Download from Google Play</a>
    </section>
  </div>
</template>

{{-- ── Jobs (results) ──────────────────────────────────────────────── --}}
<template x-if="is('results')">
  <div class="wa-page">
    <div class="flex flex-col gap-md sm:flex-row sm:items-center">
      <a :href="'#/search' + (res.query ? '?q=' + encodeURIComponent(res.query) : '')" class="flex h-[50px] flex-1 items-center gap-md rounded-field border-hair border-hairline bg-surface px-lg text-input shadow-card">
        <span x-html="ICONS.search" class="text-ink-secondary [&>svg]:h-5 [&>svg]:w-5"></span>
        <span :class="res.query ? 'text-ink' : 'text-ink-muted'" class="flex-1 truncate" x-text="res.query || 'Search jobs, employers, roles…'"></span>
        <button x-show="res.query" @click.prevent.stop="clearSearch()" class="text-ink-muted hover:text-ink" title="Clear">
          <span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
      </a>
      <button @click="openFilters()" :class="activeFilterCount && 'border-primary text-primary-dark'" class="inline-flex h-[50px] items-center justify-center gap-sm rounded-field border-hair border-hairline bg-surface px-lg text-btnghost font-semibold shadow-card hover:bg-surface-muted">
        <span x-html="ICONS.sliders" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        <span x-text="activeFilterCount ? 'Filters (' + activeFilterCount + ')' : 'Filters'"></span>
      </button>
    </div>

    <div class="no-scrollbar mt-md flex gap-xs overflow-x-auto pb-xs">
      <button @click="res.category = ''; loadResults(1)" :class="!res.category && 'wa-chip-on'" class="wa-chip shrink-0">All</button>
      <template x-for="c in jobCategories" :key="c.name">
        <button @click="pickResultsCategory(c.name)" :class="res.category === c.name && 'wa-chip-on'" class="wa-chip shrink-0" x-text="c.name"></button>
      </template>
    </div>

    <div class="mt-lg flex items-center justify-between">
      <h1 class="text-h3 text-ink" x-text="res.query ? '“' + res.query + '”' : 'Jobs'"></h1>
      <span class="text-bodysm text-ink-secondary" x-show="!res.busy || res.list.length" x-text="res.total === 1 ? '1 job' : fmtNumber(res.total) + ' jobs'"></span>
    </div>

    <template x-if="res.busy && !res.list.length">@include('webapp.ui.spinner')</template>
    <template x-if="res.error && !res.busy">
      <div class="mt-md">@include('webapp.ui.empty', ['icon' => 'alert', 'title' => 'Something went wrong', 'text' => 'Check your connection and try again.'])</div>
    </template>
    <template x-if="!res.busy && !res.error && !res.list.length">
      <div class="mt-md space-y-md">
        @include('webapp.ui.empty', ['icon' => 'search', 'title' => 'No jobs found', 'text' => 'Try adjusting your search or filters.'])
        <div class="text-center" x-show="activeFilterCount"><button @click="resetAllFilters()" class="wa-btn-ghost-sm">Clear filters</button></div>
      </div>
    </template>

    <div class="mt-md grid gap-md md:grid-cols-2 xl:grid-cols-3">
      <template x-for="job in res.list" :key="job.id">@include('webapp.screens.jobs.card')</template>
    </div>
    <div class="mt-xl text-center" x-show="res.page < res.totalPages">
      <button @click="loadResults(res.page + 1)" :disabled="res.busy" class="wa-btn-ghost-sm">
        <span x-show="!res.busy">Load more</span><span x-show="res.busy">Loading…</span>
      </button>
    </div>
  </div>
</template>

{{-- ── Search ──────────────────────────────────────────────────────── --}}
<template x-if="is('search')">
  <div class="wa-narrow">
    <form @submit.prevent="runSearch()" class="flex items-center gap-sm">
      <button type="button" @click="back('/jobs')" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field hover:bg-surface-muted" aria-label="Back">
        <span x-html="ICONS.chevronLeft" class="[&>svg]:h-6 [&>svg]:w-6"></span>
      </button>
      <div class="flex h-[50px] flex-1 items-center gap-md rounded-field border-hair border-primary bg-surface px-lg shadow-glow">
        <span x-html="ICONS.search" class="text-ink-secondary [&>svg]:h-5 [&>svg]:w-5"></span>
        <input type="search" x-model="srchQ" @input="searchType()" x-init="$nextTick(() => $el.focus())" placeholder="Try 'nurs', 'lab', 'phar'…" class="h-full flex-1 bg-transparent text-input outline-none">
      </div>
    </form>

    <template x-if="srchQ.trim() && srchSuggestions.length">
      <section class="mt-xl">
        <h2 class="wa-section-title">Suggestions</h2>
        <div class="mt-sm divide-y divide-hairline-divider rounded-card border-hair border-hairline bg-surface">
          <template x-for="s in srchSuggestions" :key="s.term">
            <button @click="runSearch(s.term)" class="flex w-full items-center gap-md px-lg py-md text-left hover:bg-surface-muted">
              <span x-html="ICONS.search" class="text-ink-muted [&>svg]:h-4 [&>svg]:w-4"></span>
              <span class="flex-1 text-body text-ink" x-text="s.term"></span>
              <span class="text-caption text-ink-muted" x-text="s.job_count + ' jobs'"></span>
            </button>
          </template>
        </div>
      </section>
    </template>

    <template x-if="!srchQ.trim() && srchRecent.length">
      <section class="mt-xl">
        <div class="flex items-center justify-between">
          <h2 class="wa-section-title">Recent</h2>
          <button @click="clearRecent()" class="text-caption font-semibold text-ink-muted hover:text-ink">Clear</button>
        </div>
        <div class="mt-sm flex flex-wrap gap-xs">
          <template x-for="r in srchRecent" :key="r">
            <button @click="runSearch(r)" class="wa-chip"><span x-html="ICONS.history" class="text-ink-muted [&>svg]:h-[14px] [&>svg]:w-[14px]"></span><span x-text="r"></span></button>
          </template>
        </div>
      </section>
    </template>

    <template x-if="srchTrending.length">
      <section class="mt-xl">
        <h2 class="wa-section-title">Popular right now</h2>
        <div class="mt-sm divide-y divide-hairline-divider rounded-card border-hair border-hairline bg-surface">
          <template x-for="t in srchTrending" :key="t.term">
            <button @click="runSearch(t.term)" class="flex w-full items-center gap-md px-lg py-md text-left hover:bg-surface-muted">
              <span x-html="ICONS.trendingUp" class="text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
              <span class="flex-1 text-body text-ink" x-text="t.term"></span>
              <span class="text-caption text-ink-muted" x-text="t.job_count + ' jobs'"></span>
            </button>
          </template>
        </div>
      </section>
    </template>
  </div>
</template>

{{-- ── Job detail ──────────────────────────────────────────────────── --}}
<template x-if="is('jobDetail')">
  <div class="wa-page !max-w-[960px]">
    <template x-if="jd.busy">@include('webapp.ui.spinner')</template>
    <template x-if="!jd.busy && jd.error">
      <div class="space-y-lg">
        @include('webapp.ui.head', ['title' => 'Job', 'back' => '/jobs'])
        <div class="rounded-card border-hair border-hairline bg-surface p-xl text-center">
          <p class="text-h4 text-ink" x-text="jd.error"></p>
          <a href="#/jobs" class="wa-btn-ghost-sm mt-lg">Find More Jobs</a>
        </div>
      </div>
    </template>

    <template x-if="!jd.busy && jd.job">
      <div class="space-y-lg" x-data="{ get job() { return jd.job } }">
        <div class="flex items-center gap-sm">
          <button @click="back('/jobs')" class="inline-flex h-10 w-10 items-center justify-center rounded-field hover:bg-surface-muted" aria-label="Back">
            <span x-html="ICONS.chevronLeft" class="[&>svg]:h-6 [&>svg]:w-6"></span>
          </button>
          <span class="flex-1"></span>
          <button @click="shareJob(job)" class="inline-flex h-10 w-10 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted" title="Share">
            <span x-html="ICONS.share" class="[&>svg]:h-5 [&>svg]:w-5"></span>
          </button>
          <button @click="toggleSave(job)" :class="isSaved(job) ? 'text-primary' : 'text-ink-secondary'" class="inline-flex h-10 w-10 items-center justify-center rounded-field hover:bg-surface-muted" title="Save">
            <span x-html="ICONS.bookmark" :class="isSaved(job) && '[&>svg]:fill-current'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
          </button>
        </div>

        <div class="grid gap-lg lg:grid-cols-[1fr_320px]">
          <div class="space-y-lg">
            {{-- Header --}}
            <section class="wa-card">
              <div class="flex items-start gap-lg">
                <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-card bg-primary-light text-[16px] font-bold text-primary-dark" x-text="initials(job.organisation)"></span>
                <div class="min-w-0">
                  <p class="flex items-center gap-xs text-bodysm text-ink-secondary">
                    <span x-text="job.organisation"></span>
                    <span x-show="job.organisation_verified" x-html="ICONS.badgeCheck" class="text-info [&>svg]:h-4 [&>svg]:w-4"></span>
                  </p>
                  <h1 class="mt-xs text-h1 text-ink" x-text="job.title"></h1>
                  <p class="mt-xs flex items-center gap-xs text-bodysm text-ink-secondary">
                    <span x-html="ICONS.mapPin" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                    <span x-text="[job.locality, job.city, job.state].filter(Boolean).join(', ')"></span>
                  </p>
                </div>
              </div>
              <p class="mt-md text-caption text-ink-muted" x-text="'Posted ' + postedLabel(job.posted_at)"></p>
            </section>

            {{-- How you match --}}
            <template x-if="token && job.match_percent != null && !job.own_posting">
              <section class="wa-card">
                <div class="flex items-center justify-between">
                  <h2 class="text-h4 text-ink">How you match</h2>
                  <span class="wa-pill" :class="matchTone(job.match_percent).cls" x-text="job.match_percent + '% match'"></span>
                </div>
                <p class="mt-xs text-bodysm text-ink-secondary" x-text="matchTone(job.match_percent).label + ' for your profile'"></p>
                <div class="mt-md flex flex-wrap gap-xs">
                  <template x-for="r in job.match_reasons || []" :key="r">
                    <span class="wa-tag !bg-success-bg !text-success"><span x-html="ICONS.check" class="[&>svg]:h-3 [&>svg]:w-3"></span><span x-text="r"></span></span>
                  </template>
                </div>
                <template x-if="(job.missing_skills || []).length">
                  <div class="mt-md rounded-field bg-warning-bg p-md">
                    <p class="text-bodysm text-warning" x-text="'Skills this job asks for that your profile does not list: ' + job.missing_skills.join(', ')"></p>
                    <a href="#/profile/about" class="mt-sm inline-block text-btnghost font-semibold text-primary-dark hover:underline">Add skills to your profile</a>
                  </div>
                </template>
              </section>
            </template>

            <template x-if="job.charges_fee">
              <div class="flex items-start gap-md rounded-card border-hair border-warning/30 bg-warning-bg p-lg text-warning">
                <span x-html="ICONS.alert" class="mt-[1px] [&>svg]:h-5 [&>svg]:w-5"></span>
                <p class="text-bodysm font-medium">This employer charges candidates a fee for this job.</p>
              </div>
            </template>

            {{-- Info grid --}}
            <section class="wa-card grid grid-cols-2 gap-lg sm:grid-cols-4">
              <template x-for="row in [
                ['Salary', jobSalary(job)], ['Experience', job.experience_display || job.experience], ['Job Type', job.type], ['Shift', job.shift],
                ['Openings', job.openings], ['Education', job.min_education], ['Gender', job.preferred_gender], ['Locality', job.locality],
              ].filter(r => r[1])" :key="row[0]">
                <div>
                  <p class="text-caption text-ink-muted" x-text="row[0]"></p>
                  <p class="mt-[2px] text-h5 text-ink" x-text="row[1]"></p>
                </div>
              </template>
            </section>

            <template x-for="sec in [
              ['SKILLS REQUIRED', job.skills, 'chips'], ['ABOUT JOB', job.about, 'text'], ['RESPONSIBILITIES', job.duties, 'list'],
              ['QUALIFICATIONS', job.qualifications, 'chips'], ['BENEFITS', job.benefits, 'chips'], ['EMPLOYER', job.organisation_note, 'text'],
            ].filter(s => Array.isArray(s[1]) ? s[1].length : s[1])" :key="sec[0]">
              <section class="wa-card">
                <h2 class="wa-section-title" x-text="sec[0]"></h2>
                <template x-if="sec[2] === 'text'"><p class="mt-sm whitespace-pre-line text-body text-ink" x-text="sec[1]"></p></template>
                <template x-if="sec[2] === 'chips'">
                  <div class="mt-sm flex flex-wrap gap-xs"><template x-for="c in sec[1]" :key="c"><span class="wa-tag !text-ink" x-text="c"></span></template></div>
                </template>
                <template x-if="sec[2] === 'list'">
                  <ul class="mt-sm list-disc space-y-xs pl-xl text-body text-ink"><template x-for="d in sec[1]" :key="d"><li x-text="d"></li></template></ul>
                </template>
              </section>
            </template>
          </div>

          {{-- Apply rail — sticky on desktop, a bottom bar on phones. --}}
          <aside class="lg:sticky lg:top-[84px] lg:self-start">
            <div class="wa-card hidden space-y-md lg:block">
              <button @click="applyTo(job)" :disabled="token && hasApplied(job) && !job.own_posting"
                      :class="token && hasApplied(job) && !job.own_posting ? '!bg-success-bg !text-success !shadow-none' : ''"
                      class="wa-btn w-full" x-text="applyCta(job)"></button>
              <div class="flex items-center justify-between text-caption text-ink-secondary">
                <span x-text="readinessLine(job)"></span>
                <span x-show="token" x-text="(me?.profile_strength ?? 0) + '% profile'"></span>
              </div>
              <template x-if="canCall(job) || canWhatsapp(job)">
                <div class="grid grid-cols-2 gap-sm border-t border-hairline-divider pt-md">
                  <a x-show="canCall(job)" :href="'tel:+91' + job.contact_phone" class="wa-btn-ghost-sm"><span x-html="ICONS.phone" class="[&>svg]:h-4 [&>svg]:w-4"></span>Call HR</a>
                  <a x-show="canWhatsapp(job)" :href="whatsappLink(job)" target="_blank" rel="noopener" class="wa-btn-ghost-sm !border-success !text-success hover:!bg-success-bg"><span x-html="ICONS.whatsapp" class="[&>svg]:h-4 [&>svg]:w-4"></span>WhatsApp</a>
                </div>
              </template>
            </div>
          </aside>
        </div>

        <div class="fixed inset-x-0 bottom-[64px] z-30 border-t border-hairline-divider bg-surface/95 px-page py-md backdrop-blur-md lg:hidden" style="margin-bottom: env(safe-area-inset-bottom)">
          <div class="mx-auto flex max-w-[640px] items-center gap-sm">
            <a x-show="canCall(job)" :href="'tel:+91' + job.contact_phone" class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-button border-btn border-primary text-primary" title="Call HR"><span x-html="ICONS.phone" class="[&>svg]:h-5 [&>svg]:w-5"></span></a>
            <a x-show="canWhatsapp(job)" :href="whatsappLink(job)" target="_blank" rel="noopener" class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-button border-btn border-success text-success" title="WhatsApp"><span x-html="ICONS.whatsapp" class="[&>svg]:h-5 [&>svg]:w-5"></span></a>
            <div class="flex-1">
              <button @click="applyTo(job)" :disabled="token && hasApplied(job) && !job.own_posting"
                      :class="token && hasApplied(job) && !job.own_posting ? '!bg-success-bg !text-success !shadow-none' : ''"
                      class="wa-btn w-full" x-text="applyCta(job)"></button>
              <p class="mt-xs text-center text-caption text-ink-secondary" x-text="readinessLine(job)"></p>
            </div>
          </div>
        </div>
        <div class="h-[110px] lg:hidden"></div>
      </div>
    </template>
  </div>
</template>

{{-- ── Filter sheet ────────────────────────────────────────────────── --}}
<div x-show="filterOpen" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
  <div @click.outside="filterOpen = false" class="flex max-h-[88vh] w-full max-w-[560px] flex-col rounded-t-sheet bg-surface shadow-raised sm:rounded-dialog">
    <div class="flex items-center justify-between border-b border-hairline-divider px-xl py-lg">
      <h2 class="text-h3 text-ink">Filters</h2>
      <button @click="filterOpen = false" class="inline-flex h-9 w-9 items-center justify-center rounded-field hover:bg-surface-muted"><span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span></button>
    </div>
    <div class="flex-1 space-y-xl overflow-y-auto px-xl py-lg">
      <template x-for="g in filterGroups" :key="g.key">
        <div>
          <p class="wa-label" x-text="g.label"></p>
          <div class="flex flex-wrap gap-xs">
            <template x-for="o in g.options" :key="o">
              <button type="button" @click="toggleFilter(g.key, o)" :class="(filterDraft[g.key] || []).includes(o) && 'wa-chip-on'" class="wa-chip" x-text="o"></button>
            </template>
          </div>
        </div>
      </template>
    </div>
    <div class="flex gap-sm border-t border-hairline-divider px-xl py-lg">
      <button @click="clearFilters()" class="wa-btn-ghost flex-1">Clear</button>
      <button @click="applyFilters()" class="wa-btn flex-[2]" x-text="filterPreview == null ? 'Show jobs' : (filterPreview === 1 ? 'Show 1 job' : 'Show ' + filterPreview + ' jobs')"></button>
    </div>
  </div>
</div>
