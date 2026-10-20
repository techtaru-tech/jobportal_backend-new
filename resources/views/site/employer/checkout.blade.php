@extends('site.employer.layout')

@section('title', 'Choose a plan — Inthes employer')
@section('employer_key', 'jobs')

@section('employer')
<div x-data="empCheckout(@js(['jobId' => $jobId]))">
  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && loadError">
    <div class="wa-card mx-auto max-w-[520px] text-center">
      <p class="text-h4 text-ink">Couldn't open checkout</p>
      <p class="mt-xs text-bodysm text-ink-secondary" x-text="loadError"></p>
      <div class="mt-lg flex justify-center gap-sm">
        <a href="{{ url('/employer/jobs') }}" class="wa-btn-ghost-sm">Go to jobs</a>
        <button type="button" class="wa-btn-sm" @click="load()">Try again</button>
      </div>
    </div>
  </template>

  <template x-if="!loading && submitted">
    <div class="py-lg">@include('site.employer.partials.submitted')</div>
  </template>

  <template x-if="!loading && !loadError && !submitted && job">
    <div>
      <div class="mb-xl">
        <a :href="jobUrl(job) + '/edit'" class="mb-xs inline-flex items-center gap-[2px] text-caption text-ink-secondary hover:text-ink">
          @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-4 w-4']) Edit job post
        </a>
        <h1 class="text-h1 text-ink">Choose a plan</h1>
        <div class="mt-sm flex flex-wrap gap-x-lg gap-y-xs">
          @foreach (['Start getting applications', 'Instant access to candidates', 'Personalised support'] as $perk)
            <span class="inline-flex items-center gap-[5px] text-bodysm text-ink-secondary">
              <span class="text-success">@include('admin.partials.icon', ['name' => 'checkCircle', 'class' => 'h-4 w-4'])</span>{{ $perk }}
            </span>
          @endforeach
        </div>
      </div>

      <div class="grid gap-xl lg:grid-cols-[minmax(0,1fr)_330px]">
        <div class="min-w-0 space-y-lg">
          <div class="flex gap-sm rounded-field border-hair border-primary-line bg-primary-light p-md text-bodysm leading-[1.45] text-primary-dark">
            <span class="mt-[1px] shrink-0 text-primary">@include('admin.partials.icon', ['name' => 'info', 'class' => 'h-[18px] w-[18px]'])</span>
            Your post has been saved! Add a plan to make it live and connect with the right candidates.
          </div>

          {{-- company document, when the company still needs one --}}
          <template x-if="needsDoc">
            <div class="rounded-card border-hair border-warning/30 bg-warning-bg p-lg">
              <p class="flex items-center gap-sm text-h5 text-ink">
                <span class="text-warning">@include('admin.partials.icon', ['name' => 'alert', 'class' => 'h-[18px] w-[18px]'])</span>
                Upload your company document to continue
              </p>
              <p class="mt-xs text-bodysm text-ink-secondary" x-text="'We need a GST certificate, shop licence or registration for ' + org.name + ' before the post can be paid for and go live.'"></p>
              <label class="wa-btn-ghost-sm mt-md cursor-pointer bg-surface">
                <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="uploadDoc($event)" :disabled="docBusy">
                <span x-html="docBusy ? ICONS.loader : ICONS.upload" :class="docBusy && 'animate-spin'" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                <span x-text="docBusy ? 'Uploading…' : 'Upload document'"></span>
              </label>
              <span class="ml-sm text-caption text-ink-muted">PDF, JPG or PNG · up to 10 MB</span>
            </div>
          </template>

          {{-- postings left from an earlier pack --}}
          <template x-for="credit in credits" :key="credit.id">
            <div class="flex flex-wrap items-center gap-md rounded-card border-hair border-success/30 bg-success-bg p-lg">
              <span class="shrink-0 text-success">@include('admin.partials.icon', ['name' => 'award', 'class' => 'h-6 w-6'])</span>
              <div class="min-w-0 flex-1">
                <p class="text-h5 text-ink" x-text="'You have ' + credit.remaining + ' posting' + (credit.remaining === 1 ? '' : 's') + ' left'"></p>
                <p class="text-caption text-ink-secondary" x-text="credit.label + ' — post this job on it at no extra cost.'"></p>
              </div>
              <button type="button" class="inline-flex h-10 items-center rounded-button bg-success px-lg text-btnghost font-semibold text-ink-onPrimary hover:bg-success/90 disabled:opacity-60"
                      :disabled="paying" @click="useCredit(credit)">Use 1</button>
            </div>
          </template>

          <section class="wa-card">
            <h2 class="text-h4 text-ink">How many job postings do you need?</h2>
            <p class="mt-xs text-caption text-ink-secondary">This job uses one. The rest stay on your account for your next posts.</p>
            <div class="mt-md flex rounded-[12px] bg-surface-muted p-[4px]">
              <template x-for="q in quantities" :key="q">
                <button type="button" @click="quantity = q"
                        :class="quantityOn(q) ? 'bg-surface text-ink shadow-card' : 'text-ink-secondary hover:text-ink'"
                        class="flex-1 rounded-[9px] py-[10px] text-caption font-semibold transition-colors sm:text-bodysm" x-text="quantityLabel(q)"></button>
              </template>
            </div>
            <template x-if="quantity >= largestBucket">
              <div class="mt-md flex items-center justify-center gap-md">
                <button type="button" @click="changeQuantity(-1)" :disabled="quantity <= largestBucket" aria-label="Fewer" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary-light text-h3 text-primary-dark disabled:opacity-40">−</button>
                <span class="min-w-[120px] text-center text-h4 tabular-nums text-ink" x-text="quantity + ' postings'"></span>
                <button type="button" @click="changeQuantity(1)" :disabled="quantity >= maxQuantity" aria-label="More" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary-light text-primary-dark disabled:opacity-40">
                  @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-5 w-5'])
                </button>
              </div>
            </template>
          </section>

          <section>
            <h2 class="mb-md text-h4 text-ink">Select a plan</h2>
            <div class="grid gap-md xl:grid-cols-3">
              <template x-for="t in (plans?.tiers || [])" :key="t.id">
                <button type="button" @click="tier = t.id"
                        :class="tier === t.id ? 'border-primary ring-1 ring-primary' : 'border-hairline hover:border-hairline-strong'"
                        class="relative flex flex-col rounded-card border-hair bg-surface p-lg text-left shadow-card transition-colors">
                  <template x-if="t.popular">
                    <span class="absolute -top-[10px] left-lg rounded-full bg-primary px-md py-[2px] text-[10px] font-bold tracking-[0.6px] text-ink-onPrimary">MOST POPULAR</span>
                  </template>
                  <span class="flex items-center">
                    <span class="flex-1 text-h4 text-ink" x-text="t.name"></span>
                    <span class="flex h-5 w-5 items-center justify-center rounded-full border-2" :class="tier === t.id ? 'border-primary' : 'border-ink-muted'">
                      <span x-show="tier === t.id" class="h-[10px] w-[10px] rounded-full bg-primary"></span>
                    </span>
                  </span>
                  <template x-if="!unitPrice(t.id)"><span class="mt-xs text-bodysm text-ink-secondary">Price not set</span></template>
                  <template x-if="unitPrice(t.id)">
                    <span class="mt-sm block">
                      <span class="flex flex-wrap items-baseline gap-x-sm">
                        <span class="text-[26px] font-bold leading-none text-ink" x-text="rupees(unitPrice(t.id).price_paise)"></span>
                        <span x-show="unitPrice(t.id).mrp_paise > unitPrice(t.id).price_paise" class="text-bodysm text-ink-muted line-through" x-text="rupees(unitPrice(t.id).mrp_paise)"></span>
                      </span>
                      <span class="mt-xs flex items-center gap-sm">
                        <span class="text-caption text-ink-secondary">per posting</span>
                        <span x-show="unitPrice(t.id).discount_percent > 0" class="rounded-[10px] border border-success px-[6px] py-[1px] text-[11px] font-semibold text-success" x-text="unitPrice(t.id).discount_percent + '% off'"></span>
                      </span>
                    </span>
                  </template>
                  <ul class="mt-md space-y-[5px] border-t border-hairline-divider pt-md">
                    <template x-for="feat in t.features" :key="feat">
                      <li class="flex items-start gap-sm text-bodysm text-ink-secondary">
                        <span class="mt-[3px] shrink-0 text-success">@include('admin.partials.icon', ['name' => 'check', 'class' => 'h-[14px] w-[14px]'])</span>
                        <span x-text="feat"></span>
                      </li>
                    </template>
                  </ul>
                </button>
              </template>
            </div>
          </section>
        </div>

        {{-- order summary --}}
        <aside>
          <div class="space-y-lg lg:sticky lg:top-[92px]">
            <div class="wa-card !p-lg">
              <p class="wa-section-title">Job post</p>
              <p class="mt-sm text-h5 text-ink" x-text="job.title"></p>
              <p class="text-caption text-ink-secondary" x-text="[job.organisation, job.city].filter(Boolean).join(' · ')"></p>
              <div class="mt-sm flex flex-wrap gap-xs">
                <span x-show="salaryOf(job)" class="wa-tag bg-primary-light font-semibold text-primary-dark" x-text="salaryOf(job)"></span>
                <span x-show="job.type" class="wa-tag" x-text="job.type"></span>
                <span x-show="job.shift" class="wa-tag" x-text="job.shift"></span>
              </div>
              <a :href="jobUrl(job) + '/edit'" class="mt-md inline-flex items-center gap-xs text-btnghost text-primary hover:underline">
                @include('admin.partials.icon', ['name' => 'pencil', 'class' => 'h-[15px] w-[15px]']) Edit job post
              </a>
            </div>

            <div class="wa-card !p-lg">
              <p class="wa-section-title">Order summary</p>
              <dl class="mt-md space-y-sm text-bodysm">
                <div class="flex justify-between gap-md"><dt class="text-ink-secondary">Plan</dt><dd class="font-semibold text-ink" x-text="tierName + ' · District reach'"></dd></div>
                <div class="flex justify-between gap-md" x-show="unit">
                  <dt class="text-ink-secondary" x-text="quantity + ' × ' + (unit ? rupees(unit.price_paise) : '')"></dt>
                  <dd class="text-ink" x-text="rupees(mrpPaise)" :class="savingPaise > 0 && 'text-ink-muted line-through'"></dd>
                </div>
                <div class="flex justify-between gap-md text-success" x-show="savingPaise > 0">
                  <dt>Discount</dt><dd x-text="'− ' + rupees(savingPaise)"></dd>
                </div>
              </dl>
              <div class="mt-md flex items-baseline justify-between border-t border-hairline-divider pt-md">
                <span class="text-h5 text-ink">Total</span>
                <span class="text-h2 tabular-nums text-ink" x-text="totalPaise == null ? '—' : rupees(totalPaise)"></span>
              </div>
              <button type="button" class="wa-btn mt-lg w-full" :disabled="totalPaise == null || paying || needsDoc" @click="pay()">
                <span x-show="paying" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span x-text="paying ? 'Processing…' : (totalPaise == null ? 'Proceed to pay' : 'Pay ' + rupees(totalPaise))"></span>
              </button>
              <p class="mt-sm flex items-center justify-center gap-xs text-caption text-ink-secondary">
                @include('admin.partials.icon', ['name' => 'lock', 'class' => 'h-[13px] w-[13px]']) Pay securely with UPI, card or net banking.
              </p>
              <p x-show="needsDoc" class="mt-xs text-center text-caption text-warning">Upload your company document to continue.</p>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-checkout'])
@endsection
