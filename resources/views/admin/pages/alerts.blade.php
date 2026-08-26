{{--
  The operator's own alert feed — the queue, plus aggregate push-delivery
  health.

  Never `app_notifications`: that is the *users'* inbox, addressed to
  candidates and recruiters. An admin is not a recipient of any of it, and
  listing those rows put private per-person messages in front of staff while
  telling them nothing about their own work.
--}}
<template x-if="view === 'alerts'">
  <div class="animate-enter-up" x-init="loadAlerts()">
    @include('admin.partials.page-header', [
      'kicker' => 'Alerts',
      'title' => 'Needs attention',
      'description' => 'Work waiting on a human, and whether push notifications are actually being read.',
      'actions' => '
        <button @click="loadAlerts()" aria-label="Refresh" title="Refresh"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline">
          <span x-html="ICONS.refresh" :class="alerts.busy && \'animate-spin\'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>',
    ])

    <template x-if="alerts.busy && !alerts.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the queue…'])
    </template>

    <template x-if="alerts.data">
      <div class="space-y-xl">

        {{-- Only `action` groups count toward the headline. A data-quality
             warning is worth showing but not worth interrupting anyone for,
             and folding the two together is how a badge becomes background
             noise nobody clears. --}}
        <template x-if="(alerts.data.groups || []).length === 0">
          <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
            @include('admin.partials.empty-state', [
              'icon' => 'shieldCheck', 'title' => 'Every queue is clear',
              'message' => 'Nothing is waiting on an operator right now.',
            ])
          </div>
        </template>

        <template x-if="(alerts.data.groups || []).length">
          <div class="space-y-md">
            <template x-for="group in alerts.data.groups" :key="group.key">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-md border-b border-hairline-divider px-lg py-md">
                  <div class="flex min-w-0 items-center gap-md">
                    <span :class="alertSeverityClass(group.severity)"
                          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-bodysm font-bold tabular-nums"
                          x-text="group.count > 99 ? '99+' : group.count"></span>
                    <div class="min-w-0">
                      <h2 class="truncate text-h4 text-ink" x-text="group.label"></h2>
                      <p class="mt-[2px] text-caption text-ink-muted" x-text="group.description"></p>
                    </div>
                  </div>
                  <template x-if="group.href">
                    <button @click="openAlertGroup(group)"
                            class="shrink-0 rounded-button px-sm py-xs text-btnghost text-primary-dark transition-colors hover:bg-primary-light">
                      Open the queue
                    </button>
                  </template>
                </div>

                <ul>
                  <template x-for="item in group.items" :key="item.id">
                    <li class="border-b border-hairline-divider px-lg py-md last:border-0">
                      <p class="text-bodysm text-ink" x-text="item.title"></p>
                      <p class="mt-[2px] text-caption text-ink-muted">
                        <template x-if="item.detail"><span x-text="item.detail + ' · '"></span></template>
                        <span x-text="timeAgo(item.at)"></span>
                      </p>
                    </li>
                  </template>
                </ul>

                {{-- The count is always the true total; the list above is a
                     preview capped at five. --}}
                <template x-if="group.count > group.items.length">
                  <div class="border-t border-hairline-divider px-lg py-sm">
                    <p class="text-caption text-ink-muted">
                      Showing <span x-text="group.items.length"></span> of
                      <span x-text="group.count"></span>.
                    </p>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </template>

        {{-- Counts and rates only. No text, no recipient, no row ids: the
             question is "is push working and is anyone reading it", which
             needs none of those. --}}
        <template x-if="alerts.data.delivery">
          <section>
            @include('admin.partials.section-eyebrow', [
              'title' => 'Push delivery', 'hint' => 'Whether notifications are landing and being opened',
            ])

            <div class="grid grid-cols-1 gap-md sm:grid-cols-3">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <span class="block text-kicker text-ink-secondary">SENT</span>
                <span class="mt-[3px] block text-stat tabular-nums text-primary" x-text="fmtNumber(alerts.data.delivery.sent)"></span>
              </div>
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <span class="block text-kicker text-ink-secondary">READ</span>
                <span class="mt-[3px] block text-stat tabular-nums text-primary" x-text="fmtNumber(alerts.data.delivery.read)"></span>
              </div>
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <span class="block text-kicker text-ink-secondary">READ RATE</span>
                <span class="mt-[3px] block text-stat tabular-nums text-primary" x-text="alerts.data.delivery.read_rate + '%'"></span>
                <div class="mt-md h-[6px] overflow-hidden rounded-chip bg-surface-muted" role="progressbar">
                  <div class="h-full origin-left rounded-chip bg-primary animate-grow-x"
                       :style="`width:${alerts.data.delivery.read_rate}%`"></div>
                </div>
              </div>
            </div>

            <template x-if="(alerts.data.delivery.by_type || []).length">
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
                      <template x-for="row in alerts.data.delivery.by_type" :key="row.type + row.audience">
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
                            {{-- A rate is a verdict here, so it earns the
                                 semantic colour a raw count does not. --}}
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
          </section>
        </template>
      </div>
    </template>
  </div>
</template>
