{{--
  Notifications — what has happened on the platform, newest first.

  This replaced an "Alerts" queue that had grown into two screens at once:
  things that happened, and data-quality warnings about *applications* (stuck,
  selected-without-interview, postings with nobody applying). The second half
  was the larger one, and none of it was an operator's work — an application
  belongs to the recruiter who owns the posting. So the feed now carries only
  the events an admin is actually told about: somebody registered, somebody
  registered an employer, somebody posted a job.

  Rows that still need something done carry the brand red; the rest are just
  news. That split is the whole reason the badge stays meaningful.
--}}
<template x-if="view === 'notifications'">
  <div class="animate-enter-up" x-init="loadNotifications()">
    @include('admin.partials.page-header', [
      'kicker' => 'Notifications',
      'title' => 'Notifications',
      'description' => 'Registrations, new employers and new postings — newest first.',
      'actions' => '
        <button @click="loadNotifications(notifications.meta?.page || 1)" aria-label="Refresh" title="Refresh"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline">
          <span x-html="ICONS.refresh" :class="notifications.busy && \'animate-spin\'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>',
    ])

    {{-- Open work, up top: the one number on this screen that is a to-do
         rather than a record. Counted from the live tables, so it does not
         drift with whatever page happens to be showing. --}}
    <template x-if="notifications.data && actionTotal > 0">
      <button @click="orgs.state = 'pending'; go('organisations')"
              class="mb-lg flex w-full items-center justify-between gap-md rounded-card border-hair border-primary-line bg-primary-light p-lg text-left transition-colors duration-micro hover:bg-primary-light/70">
        <div class="flex min-w-0 items-center gap-md">
          <span class="relative flex h-2 w-2 shrink-0">
            <span class="absolute inset-0 animate-pulse-ring rounded-full bg-primary"></span>
            <span class="relative h-2 w-2 rounded-full bg-primary"></span>
          </span>
          <div class="min-w-0">
            <p class="text-bodysm font-semibold text-primary-dark">
              <span x-text="actionTotal"></span>
              <span x-text="actionTotal === 1 ? 'item is' : 'items are'"></span> waiting on you
            </p>
            <p class="mt-[2px] text-caption text-ink-secondary">Employers to verify and postings to approve.</p>
          </div>
        </div>
        <span x-html="ICONS.chevronRight" class="[&>svg]:h-4 [&>svg]:w-4 shrink-0 text-primary-dark"></span>
      </button>
    </template>

    <template x-if="notifications.busy && !notifications.data.length">
      @include('admin.partials.loading-panel', ['label' => 'Loading notifications…'])
    </template>

    <template x-if="!notifications.busy && !notifications.data.length">
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
        @include('admin.partials.empty-state', [
          'icon' => 'bell', 'title' => 'Nothing yet',
          'message' => 'Registrations, new employers and new postings will appear here as they happen.',
        ])
      </div>
    </template>

    <template x-if="notifications.data.length">
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
        <ul>
          <template x-for="row in notifications.data" :key="row.id">
            <li class="border-b border-hairline-divider last:border-0">
              <button @click="openNotification(row)"
                      class="group flex w-full items-start gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                {{-- The glyph says what kind of event it was; the tint says
                     whether it still needs somebody. --}}
                <span x-html="ICONS[notificationIcon(row.type)]"
                      :class="row.severity === 'action'
                        ? 'bg-primary-light text-primary-dark'
                        : 'bg-surface-muted text-ink-secondary'"
                      class="mt-[2px] flex h-9 w-9 shrink-0 items-center justify-center rounded-field [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>

                <span class="min-w-0 flex-1">
                  <span class="flex flex-wrap items-center gap-sm">
                    <span class="text-bodysm font-semibold text-ink" x-text="row.title"></span>
                    <template x-if="row.severity === 'action'">
                      <span class="inline-flex items-center rounded-chip bg-primary px-[7px] py-[1px] text-caption font-bold text-ink-onPrimary">
                        Needs you
                      </span>
                    </template>
                  </span>
                  <span class="mt-[2px] block truncate text-caption text-ink-muted" x-text="row.detail"></span>
                </span>

                <span class="shrink-0 text-caption text-ink-muted" x-text="timeAgo(row.at)"></span>
                <span x-html="ICONS.chevronRight"
                      class="[&>svg]:h-4 [&>svg]:w-4 mt-[3px] shrink-0 text-ink-muted transition-transform duration-micro ease-out group-hover:translate-x-[3px] group-hover:text-primary-dark"></span>
              </button>
            </li>
          </template>
        </ul>

        <div class="border-t border-hairline-divider">
          @include('admin.partials.pagination', ['state' => 'notifications', 'loader' => 'loadNotifications'])
        </div>
      </div>
    </template>

    {{-- Counts and rates only. No text, no recipient, no row ids: the question
         is "is push working and is anyone reading it", and `app_notifications`
         is the users' inbox rather than an admin's. --}}
    <template x-if="notifications.delivery">
      <section class="mt-xl">
        @include('admin.partials.section-eyebrow', [
          'title' => 'Push delivery', 'hint' => 'Whether notifications are landing and being opened',
        ])

        <div class="grid grid-cols-1 gap-md sm:grid-cols-3">
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
            <span class="block text-kicker text-ink-secondary">SENT</span>
            <span class="mt-[3px] block text-stat tabular-nums text-primary" x-text="fmtNumber(notifications.delivery.sent)"></span>
          </div>
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
            <span class="block text-kicker text-ink-secondary">READ</span>
            <span class="mt-[3px] block text-stat tabular-nums text-primary" x-text="fmtNumber(notifications.delivery.read)"></span>
          </div>
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
            <span class="block text-kicker text-ink-secondary">READ RATE</span>
            <span class="mt-[3px] block text-stat tabular-nums text-primary" x-text="notifications.delivery.read_rate + '%'"></span>
            <div class="mt-md h-[6px] overflow-hidden rounded-chip bg-surface-muted" role="progressbar">
              <div class="h-full origin-left rounded-chip bg-primary animate-grow-x"
                   :style="`width:${notifications.delivery.read_rate}%`"></div>
            </div>
          </div>
        </div>

        <template x-if="(notifications.delivery.by_type || []).length">
          <div class="mt-md overflow-hidden rounded-card border-hair border-hairline bg-surface">
            <div class="thin-scrollbar overflow-x-auto">
              <table class="w-full border-collapse text-left">
                <thead class="bg-surface">
                  <tr>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">TYPE</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden sm:table-cell">AUDIENCE</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">SENT</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">READ</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">RATE</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="row in notifications.delivery.by_type" :key="row.type + row.audience">
                    <tr class="border-b border-hairline-divider last:border-0">
                      <td class="px-lg py-md align-middle text-bodysm text-ink">
                        <span class="capitalize" x-text="row.type.replace(/_/g, ' ')"></span>
                      </td>
                      <td class="px-lg py-md align-middle text-bodysm text-ink-secondary hidden sm:table-cell">
                        <span class="capitalize" x-text="row.audience.replace(/_/g, ' ')"></span>
                      </td>
                      <td class="px-lg py-md align-middle text-right text-bodysm tabular-nums" x-text="fmtNumber(row.sent)"></td>
                      <td class="px-lg py-md align-middle text-right text-bodysm tabular-nums" x-text="fmtNumber(row.read)"></td>
                      <td class="px-lg py-md align-middle text-right">
                        {{-- A rate is a verdict, so it earns the semantic colour
                             a raw count does not. --}}
                        <span class="inline-flex items-center rounded-chip px-[7px] py-[2px] text-caption tabular-nums"
                              :class="row.read_rate >= 50 ? 'bg-success-bg text-success' : row.read_rate >= 20 ? 'bg-warning-bg text-warning' : 'bg-surface-muted text-ink-muted'"
                              x-text="row.read_rate + '%'"></span>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </template>

        <p class="mt-md text-caption text-ink-muted">
          The feed covers the last <span x-text="notifications.windowDays"></span> days.
        </p>
      </section>
    </template>
  </div>
</template>
