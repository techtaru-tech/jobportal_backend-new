@extends('site.employer.layout')

@section('title', 'Choose Plan — Inthes employer')
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
        <a :href="jobUrl(job) + '/edit'" class="mb-xs inline-flex min-h-[32px] items-center gap-[2px] text-caption text-ink-secondary hover:text-ink">
          @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-4 w-4']) Edit job post
        </a>
        <h1 class="text-h1 text-ink">Choose Plan</h1>
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
            <span>Your post has been saved! Add a plan to make it live and connect with the right candidates. After payment you'll be asked for your business document — the job goes live once it is verified.</span>
          </div>

          {{-- the free job post, or a plan already running --}}
          <template x-for="credit in credits" :key="credit.id">
            <div class="flex flex-wrap items-center gap-md rounded-card border-hair border-success/30 bg-success-bg p-lg">
              <span class="shrink-0 text-success">@include('admin.partials.icon', ['name' => 'award', 'class' => 'h-6 w-6'])</span>
              <div class="min-w-0 flex-1">
                <p class="text-h5 text-ink" x-text="creditTitle(credit)"></p>
                <p class="text-caption text-ink-secondary" x-text="creditNote(credit)"></p>
              </div>
              <button type="button" class="inline-flex h-10 items-center rounded-button bg-success px-lg text-btnghost font-semibold text-ink-onPrimary hover:bg-success/90 disabled:opacity-60"
                      :disabled="paying" @click="useCredit(credit)" x-text="credit.tier === 'free' ? 'Post free' : 'Post now'"></button>
            </div>
          </template>

          {{-- the plan --}}
          <template x-if="plan && planTier">
            <section class="relative rounded-card border-hair border-primary bg-surface p-lg shadow-card ring-1 ring-primary">
              <span class="absolute -top-[10px] left-lg rounded-full bg-primary px-md py-[2px] text-[10px] font-bold tracking-[0.6px] text-ink-onPrimary">UNLIMITED JOB POSTS</span>
              <div class="flex flex-wrap items-end justify-between gap-md">
                <div>
                  <p class="text-h4 text-ink" x-text="plan.name + ' plan'"></p>
                  <p class="mt-xs text-bodysm text-ink-secondary" x-text="'Post as many jobs as you need for ' + plan.days + ' days'"></p>
                </div>
                <div class="text-right">
                  <span class="text-[30px] font-bold leading-none text-ink" x-text="rupees(plan.price_paise)"></span>
                  <span class="block text-caption text-ink-secondary" x-text="'for ' + plan.days + ' days · incl. GST'"></span>
                </div>
              </div>
              <ul class="mt-md grid gap-[6px] border-t border-hairline-divider pt-md sm:grid-cols-2">
                <template x-for="feat in planTier.features" :key="feat">
                  <li class="flex items-start gap-sm text-bodysm text-ink-secondary">
                    <span class="mt-[3px] shrink-0 text-success">@include('admin.partials.icon', ['name' => 'check', 'class' => 'h-[14px] w-[14px]'])</span>
                    <span x-text="feat"></span>
                  </li>
                </template>
              </ul>
            </section>
          </template>
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
                <div class="flex justify-between gap-md"><dt class="text-ink-secondary">Plan</dt><dd class="font-semibold text-ink" x-text="plan ? plan.name + ' · ' + plan.days + ' days' : ''"></dd></div>
                <div class="flex justify-between gap-md"><dt class="text-ink-secondary">Job posts</dt><dd class="text-ink">Unlimited</dd></div>
                <div class="flex justify-between gap-md"><dt class="text-ink-secondary">GST</dt><dd class="text-ink">Included</dd></div>
              </dl>
              <div class="mt-md flex items-baseline justify-between border-t border-hairline-divider pt-md">
                <span class="text-h5 text-ink">Total</span>
                <span class="text-h2 tabular-nums text-ink" x-text="totalPaise == null ? '—' : rupees(totalPaise)"></span>
              </div>
              <button type="button" class="wa-btn mt-lg w-full" :disabled="totalPaise == null || paying" @click="pay()">
                <span x-show="paying" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span x-text="paying ? 'Processing…' : (totalPaise == null ? 'Proceed to pay' : 'Proceed to pay ' + rupees(totalPaise) + ' →')"></span>
              </button>
              <p class="mt-sm flex items-center justify-center gap-xs text-caption text-ink-secondary">
                @include('admin.partials.icon', ['name' => 'lock', 'class' => 'h-[13px] w-[13px]']) Pay securely with UPI, card or net banking.
              </p>
              <p class="mt-sm flex items-start gap-xs border-t border-hairline-divider pt-sm text-caption text-ink-secondary">
                <span class="mt-[1px] shrink-0 text-primary">@include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-[14px] w-[14px]'])</span>
                <span>After payment you'll be asked for your business document — the job goes live once it is verified.</span>
              </p>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-checkout'])
@endsection
