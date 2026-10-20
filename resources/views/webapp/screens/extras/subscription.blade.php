{{-- ── My Plan ─────────────────────────────────────────────────────────
     Both sides' plans arrive in one payload; the side on screen opens
     first. A paid plan goes through the payment sheet (order → confirm);
     Free is a direct switch. --}}
<template x-if="is('subscription')">
  <div class="wa-page">
    <div class="mx-auto max-w-[880px]">
      <div class="mb-xl flex items-start gap-md">
        <button type="button" @click="back(mode === 'recruiter' ? '/r/profile' : '/profile')" aria-label="Back"
                class="-ml-sm mt-[2px] inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
          @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
        </button>
        <h1 class="min-w-0 flex-1 text-h2 text-ink" x-text="subSide === 'recruiter' ? 'Recruiter plans' : 'Job seeker plans'"></h1>
      </div>

      {{-- Which side's plans --}}
      <div class="mb-xl inline-flex rounded-button bg-surface-muted p-[3px]">
        <template x-for="side in subSides" :key="side">
          <button type="button" @click="subSide = side"
                  :class="subSide === side ? 'bg-surface text-ink shadow-card' : 'text-ink-secondary hover:text-ink'"
                  class="h-9 rounded-[10px] px-lg text-btnghost font-semibold transition-colors"
                  x-text="side === 'recruiter' ? 'Hiring' : 'Job search'"></button>
        </template>
      </div>

      <p class="wa-section-title">Choose your plan</p>
      <p class="mt-sm text-bodysm text-ink-secondary"
         x-text="subSide === 'recruiter'
           ? 'Grow your hiring pipeline with more visibility and no posting limits.'
           : 'Stand out to recruiters and get first access to new listings.'"></p>

      <template x-if="!subData">
        <div>@include('webapp.ui.spinner')</div>
      </template>

      <template x-if="subData && subPlans.length === 0">
        <div class="mt-xl">
          @include('webapp.ui.empty', ['icon' => 'crown', 'title' => "Couldn't load plans", 'text' => 'Check your connection and try again.'])
        </div>
      </template>

      <div class="mt-xl grid gap-lg md:grid-cols-2">
        <template x-for="plan in subPlans" :key="plan.id">
          <div class="wa-card flex flex-col" :class="plan.is_popular ? '!border-primary !border-btn' : ''">
            <div class="flex items-center gap-sm">
              <h3 class="flex-1 text-h3 text-ink" x-text="plan.name"></h3>
              <span x-show="plan.is_popular" class="rounded-field bg-primary-light px-sm py-[3px] text-tag font-bold text-primary-dark">POPULAR</span>
            </div>
            <p class="mt-[2px] flex items-baseline gap-xs">
              <span class="text-h2 text-ink" x-text="plan.price_label"></span>
              <span x-show="plan.billing_period" class="text-bodysm text-ink-secondary" x-text="plan.billing_period"></span>
            </p>
            <ul class="mt-md flex-1 space-y-xs">
              <template x-for="f in plan.features" :key="f">
                <li class="flex items-start gap-sm text-bodysm text-ink">
                  <span x-html="ICONS.checkCircle" class="mt-[2px] shrink-0 text-success [&>svg]:h-4 [&>svg]:w-4"></span>
                  <span x-text="f"></span>
                </li>
              </template>
            </ul>
            <p x-show="subIsCurrent(plan) && subActive?.expires_at" class="mt-md text-caption text-ink-secondary"
               x-text="'Active until ' + fmtDate(subActive?.expires_at)"></p>
            <button type="button" @click="subChoose(plan)" :disabled="subIsCurrent(plan) || !!subBusy"
                    :class="subIsCurrent(plan) ? 'wa-btn-ghost' : 'wa-btn'" class="mt-lg w-full">
              <span x-show="subBusy === plan.id" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
              <span x-text="subIsCurrent(plan) ? 'Current plan' : (plan.is_free ? 'Switch to Free' : 'Upgrade')"></span>
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
