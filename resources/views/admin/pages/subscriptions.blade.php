{{--
  Plans and subscribers.

  Read-only, with one thing worth understanding: **there is no payment
  gateway**. `POST /subscription` activates a plan immediately and
  `paid_period_days` is a config constant, so there are no invoices,
  transactions or refunds to display and nothing here pretends otherwise.
--}}
<template x-if="view === 'subscriptions'">
  <div class="animate-enter-up" x-init="loadPlans(); loadSubs()">
    @include('admin.partials.page-header', [
      'kicker' => 'Plans',
      'title' => 'Plans &amp; subscribers',
      'description' => 'The catalogue as configured, and who is on each plan.',
    ])

    <template x-if="plans.data">
      <div class="mb-xl">
        @include('admin.partials.section-eyebrow', [
          'title' => 'Catalogue', 'hint' => 'Configured in config/plans.php — not editable here',
        ])
        <div class="grid grid-cols-1 gap-md lg:grid-cols-2">
          <template x-for="audience in Object.keys(plans.data.audiences)" :key="audience">
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card">
              <div class="border-b border-hairline-divider px-lg py-md">
                <span class="block text-kicker text-ink-secondary" x-text="audience.replace('_', ' ').toUpperCase()"></span>
              </div>
              <ul>
                <template x-for="plan in plans.data.audiences[audience]" :key="plan.id">
                  <li class="flex items-center justify-between gap-md border-b border-hairline-divider px-lg py-md last:border-0">
                    <div class="min-w-0">
                      <div class="flex items-center gap-sm">
                        <span class="truncate text-bodysm font-semibold text-ink" x-text="plan.name"></span>
                        {{-- `is_popular` is the catalogue's own emphasis, so it
                             earns the accent tag rather than a second grey one. --}}
                        <template x-if="plan.is_popular">
                          <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-primary-light text-primary-dark">Popular</span>
                        </template>
                      </div>
                      <span class="block truncate text-caption text-ink-muted" x-text="plan.price_label"></span>
                    </div>
                    <div class="shrink-0 text-right">
                      <span class="block text-h4 tabular-nums text-ink" x-text="fmtNumber(plan.subscribers)"></span>
                      <span class="block text-caption text-ink-muted">subscribers</span>
                    </div>
                  </li>
                </template>
              </ul>
            </div>
          </template>
        </div>

        {{-- Recruiters on a free plan who have used up their `active_jobs`
             allowance — the only direct upsell signal this product has. --}}
        <template x-if="(plans.data.at_free_ceiling || []).length">
          <div class="mt-md rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card">
            <div class="border-b border-hairline-divider px-lg py-md">
              <h2 class="text-h4 text-ink">At the free-plan ceiling</h2>
              <p class="mt-[2px] text-caption text-ink-muted">Recruiters who have used up their free posting allowance.</p>
            </div>
            <ul>
              <template x-for="row in plans.data.at_free_ceiling" :key="row.id">
                <li class="border-b border-hairline-divider last:border-0">
                  <button @click="openUser(row.id)"
                          class="flex w-full items-center justify-between gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                    <span class="text-bodysm text-ink" x-text="row.phone"></span>
                    <span class="text-caption tabular-nums text-ink-muted"
                          x-text="row.active_jobs + ' of ' + row.limit + ' active'"></span>
                  </button>
                </li>
              </template>
            </ul>
          </div>
        </template>
      </div>
    </template>

    @include('admin.partials.section-eyebrow', [
      'title' => 'Subscribers', 'hint' => 'A lapsed row still reads as the free plan to the app',
    ])

    @include('admin.partials.list-shell', [
      'state' => 'subs',
      'loader' => 'loadSubs',
      'cols' => 5,
      'showSearch' => false,
      'emptyTitle' => 'No subscriptions match',
      'emptyMessage' => 'Try a different audience or state.',
      'filters' => '
        <div class="relative">
          <select x-model="subs.audience" @change="loadSubs(1)" aria-label="Audience"
                  class="h-[46px] w-[170px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">Any audience</option>
            <option value="job_seeker">Job seeker</option>
            <option value="recruiter">Recruiter</option>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>
        <div class="relative">
          <select x-model="subs.state" @change="loadSubs(1)" aria-label="State"
                  class="h-[46px] w-[150px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">Any state</option>
            <option value="active">Active</option>
            <option value="lapsed">Lapsed</option>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>',

      'head' => '
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">ACCOUNT</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">PLAN</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden md:table-cell">AUDIENCE</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">STATE</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right hidden lg:table-cell">EXPIRES</th>',

      'body' => '
        <template x-for="row in subs.data" :key="row.user.id + row.plan_id + row.started_at">
          <tr @click="openUser(row.user.id)"
              class="group border-b border-hairline-divider last:border-0 cursor-pointer transition-colors duration-micro hover:bg-canvas-alt">
            <td class="relative px-lg py-md align-middle text-bodysm text-ink">
              <span aria-hidden="true" class="absolute inset-y-0 left-0 w-[2px] origin-top scale-y-0 bg-primary transition-transform duration-micro ease-out group-hover:scale-y-100"></span>
              <span x-text="row.user.phone"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink">
              <span class="block truncate font-semibold" x-text="row.plan_name"></span>
              <span class="block truncate text-caption text-ink-muted" x-text="row.price_label || \'\'"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink hidden md:table-cell">
              <span class="capitalize" x-text="row.audience.replace(\'_\', \' \')"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm">
              <span class="inline-flex items-center gap-[6px] whitespace-nowrap">
                <span class="h-[7px] w-[7px] shrink-0 rounded-full" :class="row.is_active ? \'bg-success\' : \'bg-ink-muted\'"></span>
                <span class="text-caption text-ink-secondary" x-text="row.is_active ? \'Active\' : \'Lapsed\'"></span>
              </span>
            </td>
            <td class="px-lg py-md align-middle text-right hidden lg:table-cell">
              {{-- Null means never expires, which is how free plans are stored
                   — not an open-ended paid plan. --}}
              <span class="text-caption text-ink-muted" x-text="row.expires_at ? fmtDate(row.expires_at) : \'Never\'"></span>
            </td>
          </tr>
        </template>',
    ])
  </div>
</template>
