{{--
  The plans page, shared by /account/plan and /employer/plan:

    @include('site.plans.plans', ['audience' => 'jobSeeker'])   // or 'recruiter'

  Plan cards side by side (pricing-page style), the current plan and its end
  date, payment history, and — for employers — the job-posting plan prices
  and remaining posting credits. Behaviour in js/site/pages/plans.js.
--}}
@push('page-scripts')
<script defer src="{{ asset('js/site/pages/plans.js') }}?v={{ filemtime(public_path('js/site/pages/plans.js')) }}"></script>
@endpush

@php $isRecruiter = ($audience ?? 'jobSeeker') === 'recruiter'; @endphp

<div x-data="plansPage(@js(['audience' => $isRecruiter ? 'recruiter' : 'jobSeeker']))">
  <div class="mb-xl flex flex-wrap items-end gap-md">
    <div class="min-w-0 flex-1">
      <h1 class="text-h2 text-ink">{{ $isRecruiter ? 'Plan & billing' : 'My plan' }}</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">
        {{ $isRecruiter ? 'Grow your hiring pipeline with more visibility and no posting limits.' : 'Stand out to recruiters and get first access to new listings.' }}
      </p>
    </div>
    <a href="{{ $isRecruiter ? url('/account/plan') : url('/employer/plan') }}" class="wa-btn-quiet">
      {{ $isRecruiter ? 'Job seeker plans' : 'Hiring plans' }}
      @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-4 w-4'])
    </a>
  </div>

  <template x-if="!sub && !loadError">
    <div>@include('site.ui.spinner')</div>
  </template>
  <template x-if="loadError">
    <div class="wa-card text-center">
      <p class="text-bodysm text-ink-secondary" x-text="loadError"></p>
      <button type="button" class="wa-btn-ghost-sm mt-md" @click="load()">Try again</button>
    </div>
  </template>

  <template x-if="sub">
    <div class="space-y-xxl">
      {{-- Current plan --}}
      <div class="flex flex-wrap items-center gap-md rounded-card border-hair border-hairline bg-surface px-xl py-lg shadow-card">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary">
          @include('admin.partials.icon', ['name' => 'crown', 'class' => 'h-5 w-5'])
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-caption text-ink-secondary">Your current plan</p>
          <p class="text-h4 text-ink">
            <span x-text="currentPlan?.name || 'Free'"></span>
            <span class="text-bodysm font-normal text-ink-secondary" x-show="currentPlan && !currentPlan.is_free"
                  x-text="'· ' + currentPlan?.price_label + ' ' + (currentPlan?.billing_period || '')"></span>
          </p>
        </div>
        <p class="text-bodysm text-ink-secondary" x-show="active?.expires_at">
          Active until <span class="font-semibold text-ink" x-text="fmtDate(active?.expires_at)"></span>
        </p>
        <p class="text-bodysm text-ink-secondary" x-show="!active?.expires_at && currentPlan?.is_free">No expiry · free forever</p>
      </div>

      {{-- Plan cards --}}
      <section>
        <p class="wa-section-title">Choose your plan</p>
        <template x-if="plans.length === 0">
          <div class="mt-lg">@include('site.ui.empty', ['icon' => 'crown', 'title' => 'No plans available right now', 'text' => 'Please check back later.'])</div>
        </template>
        <div class="mt-lg grid gap-lg md:grid-cols-2">
          <template x-for="plan in plans" :key="plan.id">
            <div class="relative flex flex-col rounded-card border-hair bg-surface p-xl shadow-card"
                 :class="isCurrent(plan) ? 'border-success' : (plan.is_popular ? 'border-primary ring-gradient' : 'border-hairline')">
              <span x-show="plan.is_popular && !isCurrent(plan)"
                    class="absolute -top-[11px] left-xl rounded-full bg-primary px-md py-[3px] text-[11px] font-bold tracking-wide text-ink-onPrimary">MOST POPULAR</span>
              <span x-show="isCurrent(plan)"
                    class="absolute -top-[11px] left-xl inline-flex items-center gap-xs rounded-full bg-success px-md py-[3px] text-[11px] font-bold tracking-wide text-ink-onPrimary">
                <span x-html="ICONS.check" class="[&>svg]:h-3 [&>svg]:w-3"></span>CURRENT PLAN
              </span>
              <h3 class="text-h3 text-ink" x-text="plan.name"></h3>
              <p class="mt-sm flex items-baseline gap-xs">
                <span class="text-[34px] font-bold leading-none tracking-tight text-ink" x-text="plan.is_free ? rupees(0) : plan.price_label"></span>
                <span class="text-bodysm text-ink-secondary" x-text="plan.billing_period || (plan.is_free ? 'forever' : '')"></span>
              </p>
              <ul class="mt-lg flex-1 space-y-sm border-t border-hairline-divider pt-lg">
                <template x-for="f in plan.features" :key="f">
                  <li class="flex items-start gap-sm text-bodysm text-ink">
                    <span x-html="ICONS.checkCircle" class="mt-[2px] shrink-0 text-success [&>svg]:h-4 [&>svg]:w-4"></span>
                    <span x-text="f"></span>
                  </li>
                </template>
              </ul>
              <p x-show="isCurrent(plan) && active?.expires_at" class="mt-lg text-caption text-ink-secondary"
                 x-text="'Active until ' + fmtDate(active?.expires_at)"></p>
              <button type="button" @click="choose(plan)" :disabled="isCurrent(plan) || !!busy"
                      :class="isCurrent(plan) ? 'wa-btn-ghost !border-hairline !text-ink-secondary' : (plan.is_free ? 'wa-btn-ghost' : 'wa-btn')"
                      class="mt-lg w-full">
                <span x-show="busy === plan.id" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
                <span x-text="buttonLabel(plan)"></span>
              </button>
            </div>
          </template>
        </div>
      </section>

      @if ($isRecruiter)
        {{-- Job-posting plans (bought per job while posting) --}}
        <section>
          <div class="flex flex-wrap items-end gap-md">
            <div class="min-w-0 flex-1">
              <p class="wa-section-title">Job posting plans</p>
              <p class="mt-xs text-bodysm text-ink-secondary">Each job you publish uses one posting. Pick a plan when you post; buy several at once to pay less per job — unused postings stay as credits.</p>
            </div>
            <a href="{{ url('/employer/jobs/new') }}" class="wa-btn-sm">
              @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4'])Post a job
            </a>
          </div>

          {{-- Credits --}}
          <div class="mt-lg rounded-card border-hair border-hairline bg-surface px-xl py-lg shadow-card">
            <template x-if="!jobPlans">
              <p class="text-bodysm text-ink-muted">Loading your posting credits…</p>
            </template>
            <template x-if="jobPlans && credits.length === 0">
              <p class="flex items-center gap-sm text-bodysm text-ink-secondary">
                <span x-html="ICONS.info" class="text-ink-muted [&>svg]:h-4 [&>svg]:w-4"></span>
                No posting credits left. Buy a plan when you post your next job.
              </p>
            </template>
            <template x-if="credits.length > 0">
              <div>
                <p class="text-h5 text-ink"><span x-text="creditsTotal"></span> posting credit<span x-show="creditsTotal !== 1">s</span> left</p>
                <ul class="mt-sm flex flex-wrap gap-sm">
                  <template x-for="c in credits" :key="c.id">
                    <li class="wa-tag">
                      <span x-text="c.label || (tierName(c.tier) + ' · ' + reachName(c.reach) + ' · ' + c.remaining + ' left')"></span>
                      <span x-show="c.expires_at" class="text-ink-muted" x-text="'· until ' + fmtDate(c.expires_at)"></span>
                    </li>
                  </template>
                </ul>
              </div>
            </template>
          </div>

          {{-- Price per job: tier × reach --}}
          <div class="mt-lg grid gap-lg md:grid-cols-3" x-show="tiers.length">
            <template x-for="t in tiers" :key="t.id">
              <div class="relative flex flex-col rounded-card border-hair bg-surface p-xl shadow-card" :class="t.popular ? 'border-primary' : 'border-hairline'">
                <span x-show="t.popular" class="absolute -top-[11px] left-xl rounded-full bg-primary px-md py-[3px] text-[11px] font-bold tracking-wide text-ink-onPrimary">POPULAR</span>
                <h3 class="text-h4 text-ink" x-text="t.name"></h3>
                <div class="mt-md divide-y divide-hairline-divider rounded-field border-hair border-hairline">
                  <template x-for="r in reaches" :key="r.id">
                    <div class="flex items-start gap-sm px-md py-sm" :title="r.description">
                      <div class="min-w-0 flex-1">
                        <p class="text-bodysm font-semibold text-ink" x-text="r.name"></p>
                        <p class="text-[11.5px] text-ink-muted" x-text="bulkPrice(t.id, r.id)"></p>
                      </div>
                      <div class="text-right">
                        <p class="text-bodysm font-bold text-ink" x-text="price(t.id, r.id)?.price_label || '—'"></p>
                        <p class="text-[11.5px]" x-show="price(t.id, r.id)?.mrp_label">
                          <span class="text-ink-muted line-through" x-text="price(t.id, r.id)?.mrp_label"></span>
                          <span class="font-semibold text-success" x-show="price(t.id, r.id)?.discount_percent" x-text="price(t.id, r.id)?.discount_percent + '% off'"></span>
                        </p>
                      </div>
                    </div>
                  </template>
                </div>
                <ul class="mt-lg space-y-xs">
                  <template x-for="f in t.features" :key="f">
                    <li class="flex items-start gap-sm text-bodysm text-ink">
                      <span x-html="ICONS.check" class="mt-[3px] shrink-0 text-success [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
                      <span x-text="f"></span>
                    </li>
                  </template>
                </ul>
              </div>
            </template>
          </div>
          <p class="mt-md text-caption text-ink-muted" x-show="reaches.length">
            Price per job.
            <template x-for="r in reaches" :key="r.id"><span><span class="font-semibold" x-text="r.name + ':'"></span> <span x-text="r.description + ' '"></span></span></template>
          </p>
        </section>
      @endif

      {{-- Payment history --}}
      <section x-show="ordersLoaded && orders.length">
        <p class="wa-section-title">Payment history</p>
        <div class="mt-lg overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card">
          <table class="hidden w-full text-left text-bodysm md:table">
            <thead class="bg-surface-muted text-caption text-ink-secondary">
              <tr>
                <th class="px-lg py-sm font-semibold">Date</th>
                <th class="px-lg py-sm font-semibold">Item</th>
                <th class="px-lg py-sm font-semibold">Method</th>
                <th class="px-lg py-sm text-right font-semibold">Amount</th>
                <th class="px-lg py-sm font-semibold">Status</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="o in orders" :key="o.id">
                <tr class="border-t border-hairline-divider">
                  <td class="px-lg py-md text-ink-secondary" x-text="fmtDate(o.paid_at || o.created_at)"></td>
                  <td class="px-lg py-md font-semibold text-ink" x-text="orderName(o)"></td>
                  <td class="px-lg py-md text-ink-secondary" x-text="methodLabel(o.method)"></td>
                  <td class="px-lg py-md text-right font-semibold text-ink" x-text="o.amount_label || rupees(o.amount_paise)"></td>
                  <td class="px-lg py-md"><span class="wa-pill" :class="statusClass(o)" x-text="o.status_label || o.status"></span></td>
                </tr>
              </template>
            </tbody>
          </table>
          <ul class="divide-y divide-hairline-divider md:hidden">
            <template x-for="o in orders" :key="o.id">
              <li class="flex items-center gap-md px-lg py-md">
                <div class="min-w-0 flex-1">
                  <p class="truncate text-bodysm font-semibold text-ink" x-text="orderName(o)"></p>
                  <p class="text-caption text-ink-muted" x-text="fmtDate(o.paid_at || o.created_at) + ' · ' + methodLabel(o.method)"></p>
                </div>
                <div class="text-right">
                  <p class="text-bodysm font-semibold text-ink" x-text="o.amount_label || rupees(o.amount_paise)"></p>
                  <span class="wa-pill" :class="statusClass(o)" x-text="o.status_label || o.status"></span>
                </div>
              </li>
            </template>
          </ul>
        </div>
      </section>
    </div>
  </template>
</div>
