{{--
  Post a Job ▸ Choose plan — the price grid.

  Per-posting price and the struck-through MRP for every tier × reach ×
  posting count, in rupees. The app shows exactly these numbers and the
  server charges exactly these numbers: price × postings. "4+" is the
  per-posting rate for four or more.
--}}
<template x-if="view === 'jobPlans'">
  <div class="animate-enter-up" x-init="loadJobPlans()">
    @include('admin.partials.page-header', [
      'kicker' => 'Job plans',
      'title' => 'Job posting plans',
      'description' => 'Prices employers see on the Choose plan step of Post a Job. Per posting, in rupees.',
    ])

    <template x-if="jobPlans.busy && !jobPlans.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading prices…'])
    </template>

    <template x-if="jobPlans.data">
      <div>
        <template x-for="reach in jobPlans.data.reaches.filter((r) => r.id === 'district')" :key="reach.id">
          <div class="mb-xl rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card">
            <div class="border-b border-hairline-divider px-lg py-md">
              <span class="block text-kicker text-ink-secondary">PRICE PER POSTING</span>
              <span class="block text-caption text-ink-muted">What the app charges for each posting, by tier and number of postings.</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr>
                    <th scope="col" class="border-b border-hairline px-lg py-md text-kicker text-ink-muted">TIER</th>
                    <template x-for="q in jobPlans.data.quantities" :key="q">
                      <th scope="col" class="border-b border-hairline px-lg py-md text-kicker text-ink-muted"
                          x-text="(q === jobPlans.data.quantities[jobPlans.data.quantities.length - 1] ? q + '+' : q) + (q === 1 ? ' POSTING' : ' POSTINGS')"></th>
                    </template>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="tier in jobPlans.data.tiers" :key="tier.id">
                    <tr class="border-b border-hairline-divider last:border-0">
                      <td class="px-lg py-md align-top">
                        <span class="block text-bodysm font-semibold text-ink" x-text="tier.name"></span>
                        <span x-show="tier.popular" class="text-caption text-primary-dark">Most popular</span>
                      </td>
                      <template x-for="q in jobPlans.data.quantities" :key="q">
                        <td class="px-lg py-md align-top">
                          <label class="block text-caption text-ink-muted">Price ₹</label>
                          <input type="number" min="1" step="1" :disabled="!canWrite"
                                 x-model="planCell(tier.id, reach.id, q).price"
                                 class="h-[38px] w-[110px] rounded-field bg-surface-muted px-sm text-input text-ink border-hair border-hairline outline-none focus:border-primary">
                          <label class="mt-xs block text-caption text-ink-muted">MRP ₹ (struck through)</label>
                          <input type="number" min="0" step="1" :disabled="!canWrite"
                                 x-model="planCell(tier.id, reach.id, q).mrp"
                                 class="h-[38px] w-[110px] rounded-field bg-surface-muted px-sm text-input text-ink border-hair border-hairline outline-none focus:border-primary">
                        </td>
                      </template>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </template>

        <div class="flex items-center justify-end gap-md" x-show="canWrite">
          <span class="text-caption text-ink-muted">Changes apply to the next plan an employer opens.</span>
          <button @click="saveJobPlans()" :disabled="jobPlans.saving"
                  class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-lg text-btn font-semibold
                         bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                         hover:bg-primary-dark active:scale-[0.97] disabled:opacity-60">
            <span x-text="jobPlans.saving ? 'Saving…' : 'Save prices'"></span>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
