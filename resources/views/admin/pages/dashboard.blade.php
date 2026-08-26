{{--
  The dashboard. Mirrors admin_panel/src/pages/Dashboard.tsx: headline stat
  tiles with sparklines, the attention queue, a tabbed activity chart beside a
  quick-actions rail, the insight charts, then recent activity.

  Charts are hand-rolled SVG rather than a charting library. Recharts is
  React-only, and the four shapes needed here (area, donut, bars, paired rules)
  are small enough that drawing them directly costs less than shipping and
  theming a second library — and it keeps the exact geometry the React panel
  renders rather than something approximately like it.
--}}
<template x-if="view === 'dashboard'">
  <div class="animate-enter-up" x-init="loadDashboard()">

    @include('admin.partials.page-header', [
      'kicker' => 'Overview',
      'title' => '<span x-text="welcomeTitle()"></span>',
      'description' => "Where the marketplace stands right now, and what's waiting on you.",
      'actions' => '
        <div class="flex items-center gap-sm">
          <div class="relative">
            <select x-model.number="dash.days" @change="loadDashboard()" aria-label="Date range"
                    class="w-[150px] h-[50px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
              <option value="7">Last 7 days</option>
              <option value="30">Last 30 days</option>
              <option value="90">Last 90 days</option>
              <option value="180">Last 180 days</option>
            </select>
            <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
          </div>
          <button @click="loadDashboard()" aria-label="Refresh" title="Refresh"
                  class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline">
            <span x-html="ICONS.refresh" :class="dash.busy && \'animate-spin\'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
          </button>
        </div>',
    ])

    <template x-if="dash.busy && !dash.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the dashboard…'])
    </template>

    <template x-if="dash.error">
      @include('admin.partials.empty-state', [
        'tone' => 'error', 'icon' => 'alert', 'title' => 'Something went wrong',
        'message' => 'That did not load. Please try again.',
      ])
    </template>

    <template x-if="dash.data">
      <div class="space-y-xl">

        {{-- ── headline counters ─────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-md sm:grid-cols-2 xl:grid-cols-4">
          <template x-for="card in dashCards()" :key="card.label">
            <button @click="go(card.to)" class="block h-full text-left">
              <div class="group relative h-full overflow-hidden rounded-card bg-surface border-hair border-hairline shadow-card p-lg
                          transition-[box-shadow,border-color,transform] duration-micro ease-out
                          hover:-translate-y-[2px] hover:border-hairline-strong hover:shadow-raised">

                {{-- The promoted tile earns a red rule along its top edge —
                     enough to pull the eye first without changing its size. --}}
                <span x-show="card.feature" class="red-rule absolute inset-x-0 top-0 h-[2px]" aria-hidden="true"></span>

                <div class="relative flex items-start gap-md">
                  <span x-html="ICONS[card.icon]"
                        class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-field
                               transition-transform duration-micro ease-out group-hover:scale-105
                               [&>svg]:h-[18px] [&>svg]:w-[18px] bg-primary-light text-primary"></span>

                  <div class="relative z-10 min-w-0 flex-1">
                    <span class="block text-kicker text-ink-secondary" x-text="card.label.toUpperCase()"></span>
                    <div class="mt-[3px] flex flex-wrap items-baseline gap-x-sm">
                      <span class="text-stat tabular-nums text-primary" x-text="card.value"></span>
                      <template x-if="card.trend !== null && card.trend !== undefined">
                        {{-- Direction as a tinted pill. Zero renders muted rather
                             than green — "no change" is not good news, and
                             colouring it as such is how a dashboard starts lying. --}}
                        <span :class="trendClass(card.trend)"
                              class="inline-flex items-center gap-[2px] rounded-chip px-[7px] py-[2px] text-caption tabular-nums">
                          <svg x-show="Math.round(card.trend) !== 0" viewBox="0 0 12 12" class="h-[10px] w-[10px]" aria-hidden="true">
                            <path :d="card.trend > 0 ? 'M6 2.5 L10 8 L2 8 Z' : 'M6 9.5 L2 4 L10 4 Z'" fill="currentColor"/>
                          </svg>
                          <span x-text="Math.round(card.trend) === 0 ? '0%' : Math.abs(Math.round(card.trend)) + '%'"></span>
                        </span>
                      </template>
                    </div>
                    <p class="mt-[3px] truncate text-caption text-ink-muted" x-text="card.delta"></p>
                  </div>
                </div>

                {{-- A filled area under the line, not just the line: at this
                     size a hairline stroke alone reads as a scratch. --}}
                <template x-if="card.spark && card.spark.length > 1">
                  <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"
                       class="pointer-events-none absolute bottom-0 right-0 h-12 w-[42%] opacity-70 transition-opacity duration-component group-hover:opacity-100">
                    <defs>
                      <linearGradient :id="'sparkFill-' + card.key" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EB0401" stop-opacity="0.18"/>
                        <stop offset="100%" stop-color="#EB0401" stop-opacity="0"/>
                      </linearGradient>
                    </defs>
                    <polygon :points="sparkArea(card.spark)" :fill="'url(#sparkFill-' + card.key + ')'"/>
                    <polyline :points="sparkPoints(card.spark)" fill="none" stroke="#EB0401" stroke-width="1.5"
                              stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                  </svg>
                </template>
              </div>
            </button>
          </template>
        </div>

        {{-- ── attention queue — the operational to-do list ───────────── --}}
        <template x-if="attentionItems().length === 0">
          <div class="flex items-center gap-md rounded-card bg-success-bg border-hair border-transparent p-lg">
            @include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-5 w-5 shrink-0 text-success'])
            <p class="text-bodysm text-ink">Nothing needs attention right now.</p>
          </div>
        </template>

        {{-- One card holding a row of queues, rather than one card per queue:
             six separate cards for six small numbers was most of the
             dashboard's empty space, and the grouping is what makes them read
             as a single to-do list. --}}
        <template x-if="attentionItems().length > 0">
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card">
            <div class="flex items-center justify-between gap-md border-b border-hairline-divider px-lg py-md">
              <div class="flex items-center gap-sm">
                <span class="relative flex h-2 w-2 shrink-0">
                  <span class="absolute inset-0 animate-pulse-ring rounded-full bg-primary"></span>
                  <span class="relative h-2 w-2 rounded-full bg-primary"></span>
                </span>
                <h2 class="text-h4 text-ink">Needs attention</h2>
              </div>
              <span class="text-caption text-ink-muted"
                    x-text="attentionItems().length + ' queue' + (attentionItems().length === 1 ? '' : 's')"></span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
              <template x-for="item in attentionItems()" :key="item.key">
                <button @click="item.go()"
                        class="group relative flex min-w-0 flex-col gap-sm border-b border-hairline-divider p-lg text-left transition-colors duration-micro hover:bg-canvas-alt sm:border-b-0 sm:[&:not(:last-child)]:border-r">
                  {{-- A red edge that grows in on hover rather than a border
                       that is always there — keeps the resting state calm. --}}
                  <span aria-hidden="true"
                        class="absolute inset-x-0 bottom-0 h-[2px] origin-left scale-x-0 bg-primary transition-transform duration-micro ease-out group-hover:scale-x-100"></span>
                  <span x-html="ICONS[item.icon]" class="[&>svg]:h-4 [&>svg]:w-4 text-ink-muted transition-colors group-hover:text-primary-dark"></span>
                  <div class="min-w-0">
                    <span class="block text-h3 tabular-nums text-ink" x-text="fmtNumber(item.count)"></span>
                    <span class="block truncate text-caption text-ink-secondary" x-text="item.label"></span>
                  </div>
                </button>
              </template>
            </div>
          </div>
        </template>

        {{-- ── activity + quick actions ───────────────────────────────
             The chart takes the wide half because a trend needs horizontal
             room to have a shape; the actions sit as a narrow rail beside it
             rather than a full band, which would push the insights below the
             fold. ───────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-md xl:grid-cols-[1fr,300px]">

          {{-- One wide chart with a tab per series, rather than several small
               charts tiled side by side. Tiling meant each rendered too small
               to read the shape of, and it buried the `messages` series. --}}
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
            <div class="mb-lg flex flex-wrap items-start justify-between gap-md">
              <div>
                <h2 class="text-h3 text-ink">Activity</h2>
                <p class="mt-[2px] text-caption text-ink-muted">
                  <span class="tabular-nums text-ink-secondary" x-text="fmtNumber(activityTotal())"></span> total ·
                  peak <span class="tabular-nums text-ink-secondary" x-text="fmtNumber(activityPeak())"></span> in a day
                </p>
              </div>
              {{-- A segmented control rather than pills on a divider rail:
                   grouping them inside one recessed well reads as "pick one of
                   these", where free-floating pills read as unrelated buttons. --}}
              <div role="tablist" class="no-scrollbar inline-flex max-w-full gap-[2px] overflow-x-auto rounded-chip bg-surface-muted p-[3px]">
                <template x-for="tab in ACTIVITY_TABS" :key="tab.key">
                  <button role="tab" :aria-selected="dash.tab === tab.key" @click="dash.tab = tab.key"
                          :class="dash.tab === tab.key ? 'bg-canvas font-semibold text-primary-dark shadow-card' : 'bg-transparent text-ink-secondary hover:text-ink'"
                          class="shrink-0 whitespace-nowrap rounded-chip px-lg py-[7px] text-chip transition-[background-color,color,box-shadow] duration-micro"
                          x-text="tab.label"></button>
                </template>
              </div>
            </div>
            @include('admin.partials.charts.area')
          </div>

          {{-- The handful of things an admin opens the panel to *do*, rather
               than to look at. Notably there is no "Add user": accounts are
               created by people signing up with an OTP. --}}
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
            <div class="border-b border-hairline-divider px-lg py-md">
              <h2 class="text-h4 text-ink">Quick actions</h2>
            </div>
            <div class="flex flex-1 flex-col">
              <template x-for="action in QUICK_ACTIONS" :key="action.label">
                <button @click="runQuickAction(action)"
                        class="group flex flex-1 items-center gap-md border-b border-hairline-divider px-lg py-md text-left transition-colors duration-micro last:border-b-0 hover:bg-canvas-alt">
                  <span x-html="ICONS[action.icon]"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-field bg-surface-muted text-ink-secondary transition-colors group-hover:bg-primary-light group-hover:text-primary-dark [&>svg]:h-4 [&>svg]:w-4"></span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-bodysm font-semibold text-ink" x-text="action.label"></span>
                    <span class="block truncate text-caption text-ink-muted" x-text="action.hint"></span>
                  </span>
                  <span x-html="ICONS.chevronRight"
                        class="[&>svg]:h-4 [&>svg]:w-4 shrink-0 text-ink-muted transition-transform duration-micro ease-out group-hover:translate-x-[3px] group-hover:text-primary-dark"></span>
                </button>
              </template>
            </div>
          </div>
        </div>

        {{-- ── insights ───────────────────────────────────────────────── --}}
        <section>
          @include('admin.partials.section-eyebrow', [
            'title' => 'Insights', 'hint' => 'How supply and demand are actually meeting',
          ])
          <div class="grid grid-cols-1 gap-md lg:grid-cols-2">
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Application status</h2></div>
              @include('admin.partials.charts.donut')
            </div>
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Profile strength</h2></div>
              @include('admin.partials.charts.histogram')
            </div>
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg flex flex-col">
              <div class="flex items-end gap-md mb-md">
                <h2 class="flex-1 text-h3 text-ink">Top cities</h2>
                @include('admin.partials.supply-demand-legend')
              </div>
              @include('admin.partials.charts.paired-bars', ['rows' => 'topCities()'])
            </div>
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg flex flex-col">
              <div class="flex items-end gap-md mb-md">
                <h2 class="flex-1 text-h3 text-ink">Top roles</h2>
                @include('admin.partials.supply-demand-legend')
              </div>
              @include('admin.partials.charts.paired-bars', ['rows' => 'topRoles()'])
            </div>
          </div>
        </section>

        {{-- ── recent activity ────────────────────────────────────────── --}}
        <section>
          @include('admin.partials.section-eyebrow', [
            'title' => 'Recent activity', 'hint' => 'The last few things that happened',
          ])
          <div class="grid grid-cols-1 gap-md lg:grid-cols-3">

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="flex items-center justify-between gap-md border-b border-hairline-divider px-lg py-md">
                <h3 class="text-h4 text-ink">Recent applications</h3>
                <button @click="go('applications')" class="shrink-0 rounded-button px-sm py-xs text-btnghost text-primary-dark transition-colors hover:bg-primary-light">View all</button>
              </div>
              <template x-if="(dash.data.recent.applications || []).length === 0">
                <p class="p-lg text-bodysm text-ink-muted">Nothing yet.</p>
              </template>
              <ul>
                <template x-for="row in dash.data.recent.applications" :key="row.reference">
                  <li class="border-b border-hairline-divider last:border-0">
                    <button @click="openApplication(row.reference)" class="flex w-full items-center gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                      <span aria-hidden="true" x-text="initials(row.candidate_name)" style="font-size:11.6px"
                            class="inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                      <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-md">
                          <span class="truncate text-bodysm font-semibold text-ink" x-text="row.candidate_name || 'Unnamed candidate'"></span>
                          <span x-html="statusDot(row.status, 'application')"></span>
                        </div>
                        <p class="mt-[2px] truncate text-caption text-ink-muted"
                           x-text="(row.job_title || 'Unknown job') + ' · ' + timeAgo(row.applied_at)"></p>
                      </div>
                    </button>
                  </li>
                </template>
              </ul>
            </div>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="flex items-center justify-between gap-md border-b border-hairline-divider px-lg py-md">
                <h3 class="text-h4 text-ink">Recently posted</h3>
                <button @click="go('jobs')" class="shrink-0 rounded-button px-sm py-xs text-btnghost text-primary-dark transition-colors hover:bg-primary-light">View all</button>
              </div>
              <template x-if="(dash.data.recent.jobs || []).length === 0">
                <p class="p-lg text-bodysm text-ink-muted">Nothing yet.</p>
              </template>
              <ul>
                <template x-for="row in dash.data.recent.jobs" :key="row.id">
                  <li class="border-b border-hairline-divider last:border-0">
                    <button @click="openJob(row.id)" class="flex w-full items-center gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                      <span aria-hidden="true" x-text="initials(row.title)" style="font-size:11.6px"
                            class="inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                      <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-md">
                          <span class="truncate text-bodysm font-semibold text-ink" x-text="row.title"></span>
                          <span x-html="statusDot(row.status, 'job')"></span>
                        </div>
                        <p class="mt-[2px] truncate text-caption text-ink-muted"
                           x-text="row.organisation + ' · ' + row.city + ' · ' + timeAgo(row.posted_at)"></p>
                      </div>
                    </button>
                  </li>
                </template>
              </ul>
            </div>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="flex items-center justify-between gap-md border-b border-hairline-divider px-lg py-md">
                <h3 class="text-h4 text-ink">New accounts</h3>
                <button @click="go('users')" class="shrink-0 rounded-button px-sm py-xs text-btnghost text-primary-dark transition-colors hover:bg-primary-light">View all</button>
              </div>
              <template x-if="(dash.data.recent.users || []).length === 0">
                <p class="p-lg text-bodysm text-ink-muted">Nothing yet.</p>
              </template>
              <ul>
                <template x-for="row in dash.data.recent.users" :key="row.id">
                  <li class="border-b border-hairline-divider last:border-0">
                    <button @click="openUser(row.id)" class="flex w-full items-center gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                      <span aria-hidden="true" x-text="initials(row.name || row.phone)" style="font-size:11.6px"
                            class="inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                      <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-md">
                          <span class="truncate text-bodysm font-semibold text-ink" x-text="row.name || row.phone"></span>
                          <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-surface-muted text-ink-secondary"
                                x-text="row.signed_up_as"></span>
                        </div>
                        <p class="mt-[2px] truncate text-caption text-ink-muted" x-text="timeAgo(row.created_at)"></p>
                      </div>
                    </button>
                  </li>
                </template>
              </ul>
            </div>

          </div>
        </section>
      </div>
    </template>
  </div>
</template>
