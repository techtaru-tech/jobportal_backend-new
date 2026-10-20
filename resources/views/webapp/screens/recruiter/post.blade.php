{{-- Post a Job — Job details, Candidate requirements, Company details, Choose plan —
     and, once paid, "Your post is under review" (post_job/views/*). One screen serves
     /r/post (a new post, or a saved one being completed) and /r/edit/:id. --}}
@php
  // A row of chips for one closed question — the app's `_IconChoices`.
  $chipOn = "border-primary bg-primary-light font-semibold text-primary-dark";
  $chipOff = "border-hairline bg-surface-muted text-ink hover:bg-hairline/60";
  $chipBase = "inline-flex items-center gap-sm rounded-chip border-chip px-lg py-sm text-chip transition-colors";
@endphp
<template x-if="is('rPost')">
  <div class="wa-narrow">

    {{-- ── after payment ─────────────────────────────────────────── --}}
    <template x-if="rPost.submitted">
      <div class="flex flex-col items-center py-xxl text-center">
        <span class="flex h-[76px] w-[76px] items-center justify-center rounded-full [&>svg]:h-10 [&>svg]:w-10"
              :class="rSubmittedLive ? 'bg-success-bg text-success' : 'bg-warning-bg text-warning'"
              x-html="rSubmittedLive ? ICONS.checkCircle : ICONS.clock"></span>
        <h1 class="mt-lg text-h2 text-ink" x-text="rSubmittedLive ? 'Your post is live' : 'Your post is under review'"></h1>
        <p class="mt-sm max-w-[440px] text-bodysm leading-[1.5] text-ink-secondary"
           x-text="rSubmittedLive ? (rPost.submitted.title || 'Your job') + ' is now visible to candidates.'
                  : (rSubmittedNeedsDoc ? 'Upload your company document so we can verify your company and make the post live.'
                  : 'We\'re verifying your details. You\'ll be notified as soon as it goes live.')"></p>
        <span x-show="rPost.submitted.hiring_stage_label" class="mt-md rounded-chip bg-surface-muted px-md py-[6px] text-caption text-ink-secondary" x-text="rPost.submitted.hiring_stage_label"></span>
        <div class="mt-xl flex w-full max-w-[400px] flex-col gap-sm">
          <template x-if="rSubmittedNeedsDoc">
            <a href="#/r/orgs" class="wa-btn w-full">
              @include('admin.partials.icon', ['name' => 'upload', 'class' => 'h-5 w-5'])
              Upload company document
            </a>
          </template>
          <button type="button" @click="rGoToMyJobs()" :class="rSubmittedNeedsDoc ? 'wa-btn-ghost' : 'wa-btn'" class="w-full">Go to My Jobs</button>
          <button type="button" @click="rPostAnother()" class="wa-btn-quiet h-[50px] w-full">Post another job</button>
        </div>
      </div>
    </template>

    <template x-if="!rPost.submitted">
      <div>
        {{-- header --}}
        <div class="mb-lg flex items-start gap-md">
          <div class="min-w-0 flex-1">
            <p class="wa-section-title" x-text="rPost.editingId ? 'Edit job' : 'Post a job'"></p>
            <h1 class="text-h2 text-ink" x-text="rPost.editingId ? 'Update this post' : 'Hire the right people, fast'"></h1>
          </div>
          <template x-if="rPost.editingId || rPost.draftId">
            <button type="button" @click="rCancelEdit()" aria-label="Cancel" title="Cancel"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-[10px] bg-primary-light text-primary">
              @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
            </button>
          </template>
          <template x-if="!(rPost.editingId || rPost.draftId)">
            <a href="#/r/jobs" title="My Jobs" class="relative inline-flex h-10 w-10 items-center justify-center rounded-[10px] bg-primary-light text-primary">
              @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-5 w-5'])
              <span x-show="rJobsList.length" class="absolute -right-1 -top-1 rounded-[8px] bg-primary px-[4px] py-[1px] text-[9.5px] font-semibold text-ink-onPrimary" x-text="rJobsList.length"></span>
            </a>
          </template>
        </div>

        {{-- step indicator: a finished stage shows a tick and can be tapped to go back --}}
        <div class="mb-xl flex items-start">
          <template x-for="(title, i) in rSteps" :key="title">
            <div class="contents">
              <button type="button" @click="rGoToStep(i)" :disabled="i >= rPost.step" class="flex w-[72px] shrink-0 flex-col items-center gap-[4px] disabled:cursor-default">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border-[1.4px] text-caption font-bold"
                      :class="i < rPost.step ? 'border-success bg-success text-ink-onPrimary' : (i === rPost.step ? 'border-primary bg-primary-light text-primary-dark' : 'border-hairline bg-surface-muted text-ink-muted')">
                  <span x-show="i < rPost.step" x-html="ICONS.check" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                  <span x-show="i >= rPost.step" x-text="i + 1"></span>
                </span>
                <span class="text-center text-[10.5px] leading-tight" :class="i === rPost.step ? 'font-semibold text-ink' : 'text-ink-muted'" x-text="title"></span>
              </button>
              <span x-show="i < rSteps.length - 1" class="mt-[13px] h-[1.5px] flex-1" :class="i < rPost.step ? 'bg-success' : 'bg-hairline'"></span>
            </div>
          </template>
        </div>

        {{-- ── step 1 · job details ──────────────────────────────── --}}
        <template x-if="rPost.step === 0">
          <div class="space-y-xl">
            <h2 class="text-h5 text-ink">About the job</h2>
            @include('webapp.ui.combo', ['label' => 'Job title', 'model' => 'rPost.f.title', 'items' => "opt('designations')", 'placeholder' => 'Select a job title', 'required' => true])
            <div>
              @include('webapp.ui.combo', ['label' => 'Job category', 'model' => 'rPost.f.role', 'items' => "opt('categories')", 'placeholder' => 'Select a category', 'required' => true])
              <template x-if="!rPost.f.show_description">
                <button type="button" @click="rPost.f.show_description = true" class="mt-sm inline-flex items-center gap-xs text-btnghost text-primary hover:underline">
                  @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-[18px] w-[18px]'])
                  Add job description (optional)
                </button>
              </template>
            </div>
            <template x-if="rPost.f.show_description">
              <div>@include('webapp.ui.textarea', ['label' => 'Job description (optional)', 'model' => 'rPost.f.about', 'rows' => 4, 'max' => 4000, 'placeholder' => 'Briefly describe the role and the team…'])</div>
            </template>

            @include('webapp.ui.input', ['label' => 'Number of openings', 'model' => 'rPost.f.openings', 'placeholder' => 'e.g. 5', 'required' => true, 'attrs' => 'inputmode=numeric maxlength=4 @input="rDigits(rPost.f, \'openings\', 4)"'])

            <div>
              <span class="wa-label">Monthly salary<span class="text-primary"> *</span></span>
              <div class="flex items-center gap-sm">
                <div class="relative flex-1">
                  <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">₹</span>
                  <input type="text" inputmode="numeric" maxlength="7" x-model="rPost.f.salary_min" placeholder="Min salary" class="wa-input pl-xxl"
                         @input="rDigits(rPost.f, 'salary_min', 7); rOnSalary()">
                </div>
                <span class="text-bodysm text-ink-secondary">to</span>
                <div class="relative flex-1">
                  <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">₹</span>
                  <input type="text" inputmode="numeric" maxlength="7" x-model="rPost.f.salary_max" placeholder="Max salary" class="wa-input pl-xxl"
                         @input="rDigits(rPost.f, 'salary_max', 7); rOnSalary()">
                </div>
              </div>
              <p x-show="rPost.salaryError" x-text="rPost.salaryError" class="wa-error"></p>
            </div>

            <div>
              <span class="wa-label">Job shift<span class="text-primary"> *</span></span>
              <div class="flex flex-wrap gap-sm">
                <template x-for="s in opt('shifts')" :key="s">
                  <button type="button" @click="rPost.f.shift = s" :class="rPost.f.shift === s ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chipBase }}">
                    <span x-html="ICONS.clock" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                    <span x-text="s.toLowerCase().includes('shift') ? s : s + ' shift'"></span>
                  </button>
                </template>
              </div>
            </div>

            <div>
              <span class="wa-label">Job type<span class="text-primary"> *</span></span>
              <div class="flex flex-wrap gap-sm">
                <template x-for="t in opt('job_types')" :key="t">
                  <button type="button" @click="rPost.f.type = t" :class="rPost.f.type === t ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chipBase }}">
                    <span x-html="ICONS.briefcase" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                    <span x-text="t"></span>
                  </button>
                </template>
              </div>
            </div>

            <div class="space-y-md">
              <span class="wa-label !mb-0">Office location<span class="text-primary"> *</span></span>
              <button type="button" @click="rUseLocation()" :disabled="rPost.locating" class="inline-flex items-center gap-xs text-btnghost text-primary hover:underline disabled:opacity-60">
                <span x-html="rPost.locating ? ICONS.loader : ICONS.navigation" :class="rPost.locating && 'animate-spin'" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                Use current location
              </button>
              <label class="block">
                <span class="wa-label">State</span>
                <div class="relative">
                  <select :value="rPost.f.state" @change="rPickJobState($event.target.value)" class="wa-input cursor-pointer appearance-none pr-10">
                    <option value="">Select the state</option>
                    <template x-for="s in opt('states')" :key="s"><option :value="s" x-text="s" :selected="s === rPost.f.state"></option></template>
                  </select>
                  <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
                </div>
              </label>
              <label class="block">
                <span class="wa-label">City</span>
                <div class="relative">
                  <select x-model="rPost.f.city" :disabled="!rPost.f.state" class="wa-input cursor-pointer appearance-none pr-10 disabled:cursor-not-allowed disabled:opacity-60">
                    <option value="" x-text="rPost.f.state ? 'Search city / town' : 'Select the state first'"></option>
                    <template x-for="c in rCities" :key="c"><option :value="c" x-text="c" :selected="c === rPost.f.city"></option></template>
                  </select>
                  <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
                </div>
              </label>
              @include('webapp.ui.input', ['label' => 'Locality / area (optional)', 'model' => 'rPost.f.locality', 'placeholder' => 'e.g. Malviya Nagar', 'attrs' => 'maxlength=120'])
              <div x-show="rPost.f.city" class="flex items-center gap-sm rounded-field bg-primary-light px-md py-sm text-caption text-primary-dark">
                <span x-html="ICONS.info" class="text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
                <span x-text="'You will receive candidates from ' + rPost.f.city"></span>
              </div>
            </div>

            @include('webapp.ui.yesno', ['label' => 'For this job, will you charge any fee from candidates?', 'model' => 'rPost.f.charges_fee'])
          </div>
        </template>

        {{-- ── step 2 · candidate requirements ───────────────────── --}}
        <template x-if="rPost.step === 1">
          <div class="space-y-xl">
            <h2 class="text-h5 text-ink">Candidate requirements</h2>
            <div>
              <span class="wa-label">Minimum education<span class="text-primary"> *</span></span>
              <div class="flex flex-wrap gap-sm">
                <template x-for="l in opt('education_levels')" :key="l">
                  <button type="button" @click="rPost.f.min_education = l" :class="rPost.f.min_education === l ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chipBase }}">
                    <span x-html="ICONS.graduation" class="[&>svg]:h-4 [&>svg]:w-4"></span><span x-text="l"></span>
                  </button>
                </template>
              </div>
            </div>
            @include('webapp.ui.chips', ['label' => 'Experience', 'model' => 'rPost.f.experience', 'items' => 'rExperienceOptions', 'required' => true])
            <div>
              <span class="wa-label">Preferred gender</span>
              <div class="flex flex-wrap gap-sm">
                <template x-for="g in opt('gender_preferences')" :key="g">
                  <button type="button" @click="rPost.f.preferred_gender = g" :class="rPost.f.preferred_gender === g ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chipBase }}">
                    <span x-html="g === 'Any' ? ICONS.users : ICONS.user" class="[&>svg]:h-4 [&>svg]:w-4"></span><span x-text="g === 'Any' ? 'Any gender' : g"></span>
                  </button>
                </template>
              </div>
            </div>
            @include('webapp.ui.multi-add', ['label' => 'Skills required (optional)', 'model' => 'rPost.f.skills', 'items' => "opt('professional_skills')", 'placeholder' => '+ Add skills'])
            @include('webapp.ui.multi-add', ['label' => 'Specific qualifications (optional)', 'model' => 'rPost.f.qualifications', 'items' => "opt('qualifications')", 'placeholder' => 'e.g. B.Sc Nursing, DMLT'])

            <div>
              <span class="wa-label">Manage candidates through<span class="text-primary"> *</span></span>
              <div class="flex flex-wrap gap-sm">
                <template x-for="[id, label] in Object.entries(rContactModes)" :key="id">
                  <button type="button" @click="rPost.f.contact_mode = id" :class="rPost.f.contact_mode === id ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chipBase }}">
                    <span x-html="id === 'calls' ? ICONS.phone : (id === 'calls_whatsapp' ? ICONS.whatsapp : ICONS.clipboard)" class="[&>svg]:h-4 [&>svg]:w-4"></span><span x-text="label"></span>
                  </button>
                </template>
              </div>
              <template x-if="!rNeedsPhone">
                <p class="mt-sm text-caption text-ink-secondary">Candidates apply in the app — your number stays private.</p>
              </template>
              <template x-if="rNeedsPhone">
                <div class="mt-md space-y-sm">
                  @include('webapp.ui.input', ['label' => 'Number candidates should call', 'model' => 'rPost.f.contact_phone', 'placeholder' => '10-digit mobile number', 'required' => true, 'prefix' => '+91', 'attrs' => 'inputmode=numeric maxlength=10 @input="rDigits(rPost.f, \'contact_phone\', 10)"'])
                  <template x-if="rPost.f.contact_mode === 'calls_whatsapp'">
                    <div class="space-y-sm">
                      <label class="inline-flex cursor-pointer items-center gap-sm text-bodysm text-ink">
                        <input type="checkbox" x-model="rPost.f.same_whatsapp" class="h-4 w-4 accent-[#EB0401]">
                        Same number on WhatsApp
                      </label>
                      <template x-if="!rPost.f.same_whatsapp">
                        <div>@include('webapp.ui.input', ['label' => 'WhatsApp number', 'model' => 'rPost.f.whatsapp_phone', 'placeholder' => '10-digit mobile number', 'required' => true, 'prefix' => '+91', 'attrs' => 'inputmode=numeric maxlength=10 @input="rDigits(rPost.f, \'whatsapp_phone\', 10)"'])</div>
                      </template>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </div>
        </template>

        {{-- ── step 3 · company details ──────────────────────────── --}}
        <template x-if="rPost.step === 2">
          <div class="space-y-lg">
            <h2 class="text-h5 text-ink">Company details</h2>
            @include('webapp.screens.recruiter.company-form', ['ctx' => 'post'])
          </div>
        </template>

        {{-- ── step 4 · choose plan ──────────────────────────────── --}}
        <template x-if="rIsPlanStep">
          <div>
            <template x-if="!rPost.plans">
              <div class="py-xxl text-center">
                <template x-if="rPost.plansLoading"><div>@include('webapp.ui.spinner')</div></template>
                <template x-if="!rPost.plansLoading">
                  <div>
                    <p class="text-bodysm text-ink-secondary">Couldn't load the plans.</p>
                    <button type="button" class="wa-btn-quiet text-primary" @click="rLoadPlans()">Try again</button>
                  </div>
                </template>
              </div>
            </template>
            <template x-if="rPost.plans">
              <div>
                <h2 class="text-h3 text-ink">Select a plan</h2>
                <div class="mt-sm flex flex-wrap gap-x-md gap-y-xs">
                  @foreach (['Start getting applications', 'Instant access to candidates', 'Personalised support'] as $perk)
                    <span class="inline-flex items-center gap-[4px] text-caption text-ink-secondary">
                      <span class="text-success">@include('admin.partials.icon', ['name' => 'checkCircle', 'class' => 'h-4 w-4'])</span>{{ $perk }}
                    </span>
                  @endforeach
                </div>
                <div class="mt-md flex gap-sm rounded-field bg-primary-light p-md text-bodysm leading-[1.4] text-primary-dark">
                  <span class="mt-[1px] shrink-0 text-primary">@include('admin.partials.icon', ['name' => 'info', 'class' => 'h-[18px] w-[18px]'])</span>
                  Your post has been saved! Add a plan to make it live and connect with the right candidates.
                </div>

                <template x-for="credit in (rPost.plans.credits || [])" :key="credit.id">
                  <div class="mt-sm flex items-center gap-sm rounded-field bg-success-bg p-md">
                    <span class="shrink-0 text-success">@include('admin.partials.icon', ['name' => 'award', 'class' => 'h-5 w-5'])</span>
                    <div class="min-w-0 flex-1">
                      <p class="text-bodysm font-semibold text-ink" x-text="'You have ' + credit.remaining + ' posting' + (credit.remaining === 1 ? '' : 's') + ' left'"></p>
                      <p class="text-caption text-ink-secondary" x-text="credit.label"></p>
                    </div>
                    <button type="button" class="inline-flex h-10 items-center rounded-button bg-success px-lg text-btnghost font-semibold text-ink-onPrimary disabled:opacity-60"
                            :disabled="rPost.paying" @click="rUseCredit(credit)">Use 1</button>
                  </div>
                </template>

                <p class="mt-xl text-center text-h5 text-ink">How many job postings do you need?</p>
                <div class="mt-md flex rounded-[12px] bg-surface-muted p-[4px]">
                  <template x-for="q in (rPost.plans.quantities || [1])" :key="q">
                    <button type="button" @click="rPost.quantity = q"
                            :class="rQuantityOn(q) ? 'bg-primary text-ink-onPrimary' : 'text-ink-secondary hover:text-ink'"
                            class="flex-1 rounded-[9px] py-[10px] text-caption font-semibold transition-colors"
                            x-text="q === rLargestBucket ? q + '+ Postings' : (q === 1 ? '1 Posting' : q + ' Postings')"></button>
                  </template>
                </div>
                <template x-if="rPost.quantity >= rLargestBucket">
                  <div class="mt-sm flex items-center justify-center gap-md">
                    <button type="button" @click="rChangeQuantity(-1)" aria-label="Fewer" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary-light text-h3 text-primary-dark">−</button>
                    <span class="min-w-[110px] text-center text-h5 text-ink" x-text="rPost.quantity + ' postings'"></span>
                    <button type="button" @click="rChangeQuantity(1)" aria-label="More" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary-light text-primary-dark">
                      @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-5 w-5'])
                    </button>
                  </div>
                </template>

                <div class="mt-lg space-y-md">
                  <template x-for="tier in (rPost.plans.tiers || [])" :key="tier.id">
                    <button type="button" @click="rPost.tier = tier.id"
                            :class="rPost.tier === tier.id ? 'border-2 border-primary' : 'border border-hairline'"
                            class="block w-full overflow-hidden rounded-card bg-surface text-left transition-colors">
                      <template x-if="tier.popular">
                        <div class="bg-primary py-[4px] text-center text-caption font-bold tracking-[0.6px] text-ink-onPrimary">MOST POPULAR</div>
                      </template>
                      <div class="p-md">
                        <div class="flex items-center">
                          <span class="flex-1 text-h5 text-ink" x-text="tier.name"></span>
                          <span class="flex h-5 w-5 items-center justify-center rounded-full border-2" :class="rPost.tier === tier.id ? 'border-primary' : 'border-ink-muted'">
                            <span x-show="rPost.tier === tier.id" class="h-[10px] w-[10px] rounded-full bg-primary"></span>
                          </span>
                        </div>
                        <template x-if="!rUnitPrice(tier.id)">
                          <p class="mt-xs text-bodysm text-ink-secondary">Price not set</p>
                        </template>
                        <template x-if="rUnitPrice(tier.id)">
                          <div class="mt-xs flex flex-wrap items-center gap-sm">
                            <span class="text-h2 text-ink" x-text="rupees(rUnitPrice(tier.id).price_paise)"></span>
                            <span x-show="rUnitPrice(tier.id).mrp_paise > rUnitPrice(tier.id).price_paise" class="text-bodysm text-ink-muted line-through" x-text="rupees(rUnitPrice(tier.id).mrp_paise)"></span>
                            <span x-show="rUnitPrice(tier.id).discount_percent > 0" class="rounded-[10px] border border-success px-[6px] py-[1px] text-caption text-success" x-text="rUnitPrice(tier.id).discount_percent + '% off'"></span>
                            <span class="text-caption text-ink-secondary">per posting</span>
                          </div>
                        </template>
                        <ul class="mt-sm space-y-[3px]">
                          <template x-for="feat in tier.features" :key="feat">
                            <li class="flex items-start gap-sm text-bodysm text-ink-secondary">
                              <span class="mt-[3px] shrink-0 text-success">@include('admin.partials.icon', ['name' => 'check', 'class' => 'h-[14px] w-[14px]'])</span>
                              <span x-text="feat"></span>
                            </li>
                          </template>
                        </ul>
                      </div>
                    </button>
                  </template>
                </div>

                <div class="mt-lg grid grid-cols-3 gap-sm">
                  <button type="button" class="h-[50px] rounded-button border-btn border-hairline text-btnghost text-ink-secondary hover:bg-surface-muted" @click="rGoToStep(0)">Edit job post</button>
                  <button type="button" class="wa-btn col-span-2 px-md" :disabled="rTotalPaise == null || rPost.paying" @click="rPay()">
                    <span x-show="rPost.paying" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                    <span x-show="!rPost.paying" x-text="rPayLabel + ' →'"></span>
                  </button>
                </div>
                <p class="mt-md text-center text-caption text-ink-secondary">Pay securely with UPI, card or net banking.</p>
              </div>
            </template>
          </div>
        </template>

        {{-- Back / Continue for steps 1–3. Company details saves the post, so its button says so. --}}
        <template x-if="!rIsPlanStep">
          <div class="mt-xxl flex gap-sm border-t border-hairline-divider pt-lg">
            <template x-if="rPost.step > 0">
              <button type="button" class="h-[50px] flex-1 rounded-button border-btn border-hairline text-btn text-ink-secondary hover:bg-surface-muted" @click="rPrevStep()">Back</button>
            </template>
            <button type="button" class="wa-btn flex-[2]" :disabled="rPost.busy" @click="rNextStep()">
              <span x-show="rPost.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
              <span x-show="!rPost.busy" x-text="rPost.step === 2 ? (rPost.editingId ? 'Save changes' : 'Save & continue') : 'Continue'"></span>
            </button>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
