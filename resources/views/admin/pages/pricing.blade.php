{{--
  Plans & pricing — every price, free limit and duration the website and the
  app use. Prices are in rupees and include GST.

   Employer:   first N job posts free, then one plan: unlimited posts for D days.
   Job seeker: first N applications free, then one plan: unlimited for D days.
   Resume:     watermark-free resume, a separate one-off.
--}}
<template x-if="view === 'pricing'">
  <div class="animate-enter-up" x-init="loadPricing()">
    @include('admin.partials.page-header', [
      'kicker' => 'Plans & pricing',
      'title' => 'Plans & pricing',
      'description' => 'What employers and job seekers pay, and what they get free. Prices include GST. Changes go live on the website and the app at once.',
    ])

    <template x-if="pricing.busy && !pricing.form">
      @include('admin.partials.loading-panel', ['label' => 'Loading plans…'])
    </template>

    <template x-if="pricing.form">
      <div class="grid gap-xl lg:grid-cols-2">

        {{-- Employer --}}
        <section class="rounded-card bg-surface border-hair border-hairline shadow-card">
          <div class="border-b border-hairline-divider px-lg py-md">
            <span class="block text-kicker text-ink-secondary">EMPLOYER · POST A JOB</span>
            <span class="block text-caption text-ink-muted">Free job posts first, then one plan with unlimited job posts.</span>
          </div>
          <div class="grid gap-md p-lg sm:grid-cols-2">
            <label class="block">
              <span class="block text-caption text-ink-muted">Free job posts (per account)</span>
              <input type="number" min="0" step="1" :disabled="!canWrite" x-model.number="pricing.form.recruiter_free_jobs"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="block">
              <span class="block text-caption text-ink-muted">Plan name</span>
              <input type="text" maxlength="30" :disabled="!canWrite" x-model="pricing.form.recruiter_plan_name"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="block">
              <span class="block text-caption text-ink-muted">Plan price ₹ (incl. GST)</span>
              <input type="number" min="1" step="1" :disabled="!canWrite" x-model.number="pricing.form.recruiter_price"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="block">
              <span class="block text-caption text-ink-muted">Plan runs for (days)</span>
              <input type="number" min="1" step="1" :disabled="!canWrite" x-model.number="pricing.form.recruiter_days"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="block sm:col-span-2">
              <span class="block text-caption text-ink-muted">Each approved job stays live for (days)</span>
              <input type="number" min="1" step="1" :disabled="!canWrite" x-model.number="pricing.form.job_live_days"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary sm:w-1/2">
            </label>
          </div>
          <p class="border-t border-hairline-divider px-lg py-md text-caption text-ink-secondary"
             x-text="pricingSummary('recruiter')"></p>
        </section>

        {{-- Job seeker --}}
        <section class="rounded-card bg-surface border-hair border-hairline shadow-card">
          <div class="border-b border-hairline-divider px-lg py-md">
            <span class="block text-kicker text-ink-secondary">JOB SEEKER · APPLY</span>
            <span class="block text-caption text-ink-muted">Free applications first, then one plan with unlimited applications.</span>
          </div>
          <div class="grid gap-md p-lg sm:grid-cols-2">
            <label class="block">
              <span class="block text-caption text-ink-muted">Free job applications (per account)</span>
              <input type="number" min="0" step="1" :disabled="!canWrite" x-model.number="pricing.form.seeker_free_applications"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="block">
              <span class="block text-caption text-ink-muted">Plan name</span>
              <input type="text" maxlength="30" :disabled="!canWrite" x-model="pricing.form.seeker_plan_name"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="block">
              <span class="block text-caption text-ink-muted">Plan price ₹ (incl. GST)</span>
              <input type="number" min="1" step="1" :disabled="!canWrite" x-model.number="pricing.form.seeker_price"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="block">
              <span class="block text-caption text-ink-muted">Plan runs for (days)</span>
              <input type="number" min="1" step="1" :disabled="!canWrite" x-model.number="pricing.form.seeker_days"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary">
            </label>
            <label class="flex items-center gap-sm sm:col-span-2">
              <input type="checkbox" :disabled="!canWrite" x-model="pricing.form.seeker_includes_resume" class="h-4 w-4 accent-primary">
              <span class="text-bodysm text-ink">The plan includes the watermark-free resume</span>
            </label>
          </div>
          <p class="border-t border-hairline-divider px-lg py-md text-caption text-ink-secondary"
             x-text="pricingSummary('jobSeeker')"></p>
        </section>

        {{-- Resume one-off --}}
        <section class="rounded-card bg-surface border-hair border-hairline shadow-card">
          <div class="border-b border-hairline-divider px-lg py-md">
            <span class="block text-kicker text-ink-secondary">JOB SEEKER · RESUME</span>
            <span class="block text-caption text-ink-muted">Download without the INTHES mark — a separate, one-time payment.</span>
          </div>
          <div class="p-lg">
            <label class="block">
              <span class="block text-caption text-ink-muted">Watermark-free resume ₹ (incl. GST)</span>
              <input type="number" min="1" step="1" :disabled="!canWrite" x-model.number="pricing.form.resume_price"
                     class="mt-xs h-[42px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-hairline outline-none focus:border-primary sm:w-1/2">
            </label>
          </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-md lg:col-span-2" x-show="canWrite">
          <span class="text-caption text-ink-muted">Payments already opened keep the price they were opened at.</span>
          <button @click="savePricing()" :disabled="pricing.saving"
                  class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-lg text-btn font-semibold
                         bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                         hover:bg-primary-dark active:scale-[0.97] disabled:opacity-60">
            <span x-text="pricing.saving ? 'Saving…' : 'Save plans'"></span>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
