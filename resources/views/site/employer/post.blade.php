@extends('site.employer.layout')

@section('title', ($jobId ? 'Edit job' : 'Post a job').' — Inthes employer')
@section('employer_key', $jobId ? 'jobs' : 'post')

@php
  $card = 'rounded-card border-hair border-hairline bg-surface p-lg shadow-card sm:p-xl';
  $sectionHead = 'mb-lg flex items-center gap-md';
  $num = 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink text-caption font-bold text-ink-onPrimary';
  $chipIcon = 'text-ink-muted [&>svg]:h-[15px] [&>svg]:w-[15px]';
  $addLink = 'inline-flex items-center gap-xs text-btnghost text-primary hover:underline';
@endphp

@section('employer')
<div x-data="empPost(@js(['jobId' => $jobId]))">
  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && loadError">
    <div class="wa-card text-center"><p class="text-bodysm text-danger" x-text="loadError"></p><button type="button" class="wa-btn-ghost-sm mt-md" @click="load()">Try again</button></div>
  </template>

  {{-- posted on a plan credit --}}
  <template x-if="!loading && submitted">
    <div class="py-lg">@include('site.employer.partials.submitted')</div>
  </template>

  <template x-if="!loading && !loadError && !submitted">
    <div>
      {{-- header --}}
      <div class="mb-xl flex flex-wrap items-start gap-md">
        <div class="min-w-0 flex-1">
          <a href="{{ url('/employer/jobs') }}" class="mb-xs inline-flex items-center gap-[2px] text-caption text-ink-secondary hover:text-ink">
            @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-4 w-4']) Jobs
          </a>
          <h1 class="text-h1 text-ink" x-text="mode === 'edit' ? 'Edit job' : (mode === 'draft' ? 'Finish your job post' : 'Post a job')"></h1>
          <p class="mt-xs text-bodysm text-ink-secondary" x-text="mode === 'edit' ? (job.title + ' · ' + job.code) : 'Fill in the details below — it takes about 3 minutes.'"></p>
        </div>
        <template x-if="job">
          <span class="wa-pill mt-lg" :class="statusPill(job.posting_status)" x-text="statusLabel(job.posting_status)"></span>
        </template>
      </div>

      <template x-if="mode === 'edit' && job.posting_status === 'active'">
        <div class="mb-lg flex items-start gap-sm rounded-field border-hair border-warning/25 bg-warning-bg px-lg py-md text-bodysm text-warning">
          <span class="mt-[1px] shrink-0">@include('admin.partials.icon', ['name' => 'info', 'class' => 'h-[18px] w-[18px]'])</span>
          This job is live. Saving changes sends it back for approval, and it is paused for candidates until an admin reviews it.
        </div>
      </template>
      <template x-if="mode === 'edit' && job.posting_status === 'rejected' && job.rejection_reason">
        <div class="mb-lg flex items-start gap-sm rounded-field border-hair border-danger/25 bg-danger-bg px-lg py-md text-bodysm text-danger">
          <span class="mt-[1px] shrink-0">@include('admin.partials.icon', ['name' => 'xCircle', 'class' => 'h-[18px] w-[18px]'])</span>
          <span x-text="'Not approved: ' + job.rejection_reason + ' — fix it below and save to resubmit.'"></span>
        </div>
      </template>
      <template x-if="mode === 'draft'">
        <div class="mb-lg flex items-start gap-sm rounded-field border-hair border-primary-line bg-primary-light px-lg py-md text-bodysm text-primary-dark">
          <span class="mt-[1px] shrink-0">@include('admin.partials.icon', ['name' => 'wallet', 'class' => 'h-[18px] w-[18px]'])</span>
          <span>This post is saved. Review it, then <a :href="noticeHref(job, 'plan')" class="font-semibold underline">choose a plan</a> to make it live.</span>
        </div>
      </template>

      <div class="grid gap-xl lg:grid-cols-[minmax(0,1fr)_300px] xl:grid-cols-[minmax(0,1fr)_330px]">
        <form class="min-w-0 space-y-lg" @submit.prevent="submit()" novalidate>

          {{-- ── 1 · job details ─────────────────────────────────────── --}}
          <section class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">1</span><h2 class="text-h3 text-ink">Job details</h2></div>
            <div class="space-y-xl">
              <div class="grid gap-lg sm:grid-cols-2">
                <div data-field="title" @input="recheck('title')">
                  @include('site.ui.combo', ['label' => 'Job title', 'model' => 'f.title', 'items' => "opt('designations')", 'placeholder' => 'e.g. Staff Nurse', 'required' => true, 'error' => 'errors.title'])
                </div>
                <div data-field="role" @input="recheck('role')">
                  @include('site.ui.combo', ['label' => 'Job category', 'model' => 'f.role', 'items' => "opt('categories')", 'placeholder' => 'e.g. Nurse', 'required' => true, 'error' => 'errors.role'])
                </div>
              </div>

              <div>
                <template x-if="!showDescription">
                  <button type="button" @click="showDescription = true" class="{{ $addLink }}">
                    @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-[18px] w-[18px]']) Add job description (optional)
                  </button>
                </template>
                <template x-if="showDescription">
                  <div>
                    @include('site.ui.textarea', ['label' => 'Job description (optional)', 'model' => 'f.about', 'rows' => 5, 'max' => 4000, 'placeholder' => 'Briefly describe the role and the team…'])
                    <button type="button" class="-mt-md text-caption text-ink-secondary hover:text-ink" @click="showDescription = false; f.about = ''">Remove description</button>
                  </div>
                </template>
              </div>

              <div class="grid gap-lg sm:grid-cols-[160px_1fr]">
                <div data-field="openings" @input="recheck('openings')">
                  @include('site.ui.input', ['label' => 'Number of openings', 'model' => 'f.openings', 'placeholder' => 'e.g. 5', 'required' => true, 'attrs' => 'inputmode=numeric maxlength=4 @input="digits(\'openings\', 4)"', 'error' => 'errors.openings'])
                </div>
                <div data-field="salary">
                  <span class="wa-label">Monthly salary<span class="text-primary"> *</span></span>
                  <div class="flex items-center gap-sm">
                    <div class="relative flex-1">
                      <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">₹</span>
                      <input type="text" inputmode="numeric" maxlength="7" x-model="f.salary_min" placeholder="Min salary" class="wa-input pl-xxl" aria-label="Minimum monthly salary"
                             @input="digits('salary_min', 7); onSalary()">
                    </div>
                    <span class="text-bodysm text-ink-secondary">to</span>
                    <div class="relative flex-1">
                      <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">₹</span>
                      <input type="text" inputmode="numeric" maxlength="7" x-model="f.salary_max" placeholder="Max salary" class="wa-input pl-xxl" aria-label="Maximum monthly salary"
                             @input="digits('salary_max', 7); onSalary()">
                    </div>
                  </div>
                  <p x-show="errors.salary" x-text="errors.salary" class="wa-error"></p>
                </div>
              </div>

              <div class="grid gap-xl md:grid-cols-2">
                <div data-field="shift" @click="$nextTick(() => recheck('shift'))">
                  @include('site.ui.chips', ['label' => 'Job shift', 'model' => 'f.shift', 'items' => "opt('shifts')", 'labels' => "Object.fromEntries(opt('shifts').map((s) => [s, shiftLabel(s)]))", 'required' => true, 'error' => 'errors.shift'])
                </div>
                <div data-field="type" @click="$nextTick(() => recheck('type'))">
                  @include('site.ui.chips', ['label' => 'Job type', 'model' => 'f.type', 'items' => "opt('job_types')", 'required' => true, 'error' => 'errors.type'])
                </div>
              </div>

              <div>
                <div class="mb-sm flex flex-wrap items-center justify-between gap-sm">
                  <span class="wa-label !mb-0">Office location<span class="text-primary"> *</span></span>
                  <button type="button" @click="useLocation()" :disabled="locating" class="{{ $addLink }} disabled:opacity-60">
                    <span x-html="locating ? ICONS.loader : ICONS.navigation" :class="locating && 'animate-spin'" class="[&>svg]:h-[16px] [&>svg]:w-[16px]"></span>
                    Use current location
                  </button>
                </div>
                <div class="grid gap-lg sm:grid-cols-3">
                  <div data-field="state">
                    <label class="block">
                      <span class="wa-label">State</span>
                      <div class="relative">
                        <select :value="f.state" @change="pickState($event.target.value)" class="wa-input cursor-pointer appearance-none pr-10">
                          <option value="">Select the state</option>
                          <template x-for="s in opt('states')" :key="s"><option :value="s" x-text="s" :selected="s === f.state"></option></template>
                        </select>
                        <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
                      </div>
                    </label>
                    <p x-show="errors.state" x-text="errors.state" class="wa-error"></p>
                  </div>
                  <div data-field="city" @change="recheck('city')">
                    <label class="block">
                      <span class="wa-label">City / district</span>
                      <div class="relative">
                        <select x-model="f.city" :disabled="!f.state" class="wa-input cursor-pointer appearance-none pr-10 disabled:cursor-not-allowed disabled:opacity-60">
                          <option value="" x-text="f.state ? 'Select the city' : 'Pick a state first'"></option>
                          <template x-for="c in cities" :key="c"><option :value="c" x-text="c" :selected="c === f.city"></option></template>
                        </select>
                        <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
                      </div>
                    </label>
                    <p x-show="errors.city" x-text="errors.city" class="wa-error"></p>
                  </div>
                  @include('site.ui.input', ['label' => 'Locality / area (optional)', 'model' => 'f.locality', 'placeholder' => 'e.g. Malviya Nagar', 'attrs' => 'maxlength=120'])
                </div>
                <p x-show="f.city" class="mt-sm flex items-center gap-sm text-caption text-ink-secondary">
                  <span x-html="ICONS.info" class="text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
                  <span x-text="'You will receive candidates from ' + f.city"></span>
                </p>
              </div>

              <div data-field="charges_fee">
                @include('site.ui.yesno', ['label' => 'For this job, will you charge any fee from candidates?', 'model' => 'f.charges_fee'])
                <p x-show="errors.charges_fee" x-text="errors.charges_fee" class="wa-error"></p>
              </div>
            </div>
          </section>

          {{-- ── 2 · candidate requirements ──────────────────────────── --}}
          <section class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">2</span><h2 class="text-h3 text-ink">Candidate requirements</h2></div>
            <div class="space-y-xl">
              <div data-field="min_education" @click="$nextTick(() => recheck('min_education'))">
                @include('site.ui.chips', ['label' => 'Minimum education', 'model' => 'f.min_education', 'items' => "opt('education_levels')", 'required' => true, 'error' => 'errors.min_education'])
              </div>
              <div data-field="experience" @click="$nextTick(() => recheck('experience'))">
                @include('site.ui.chips', ['label' => 'Experience', 'model' => 'f.experience', 'items' => 'experienceOptions', 'required' => true, 'error' => 'errors.experience'])
              </div>
              @include('site.ui.chips', ['label' => 'Preferred gender', 'model' => 'f.preferred_gender', 'items' => "opt('gender_preferences')", 'labels' => "({ Any: 'Any gender' })", 'required' => true])

              <div>
                <template x-if="!showMoreReqs">
                  <button type="button" @click="showMoreReqs = true" class="{{ $addLink }}">
                    @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-[18px] w-[18px]']) Add skills & qualifications (optional)
                  </button>
                </template>
                <template x-if="showMoreReqs">
                  <div class="space-y-xl">
                    @include('site.ui.multi-add', ['label' => 'Skills required (optional)', 'model' => 'f.skills', 'items' => "opt('professional_skills')", 'placeholder' => 'Add a skill'])
                    @include('site.ui.multi-add', ['label' => 'Specific qualifications (optional)', 'model' => 'f.qualifications', 'items' => "opt('qualifications')", 'placeholder' => 'e.g. B.Sc Nursing, DMLT'])
                  </div>
                </template>
              </div>
            </div>
          </section>

          {{-- ── 3 · contact preferences ─────────────────────────────── --}}
          <section class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">3</span><h2 class="text-h3 text-ink">Contact preferences</h2></div>
            <div class="space-y-lg" data-field="contact_mode">
              <div>
                <span class="wa-label">Manage candidates through<span class="text-primary"> *</span></span>
                <div class="flex flex-wrap gap-xs">
                  <template x-for="[id, label] in Object.entries(contactModes)" :key="id">
                    <button type="button" @click="f.contact_mode = id; errors.contact_mode = ''" :class="f.contact_mode === id && 'wa-chip-on'" class="wa-chip">
                      <span x-html="id === 'calls' ? ICONS.phone : (id === 'calls_whatsapp' ? ICONS.whatsapp : ICONS.clipboard)" class="{{ $chipIcon }}" :class="f.contact_mode === id && '!text-primary'"></span>
                      <span x-text="label"></span>
                    </button>
                  </template>
                </div>
                <p x-show="errors.contact_mode" x-text="errors.contact_mode" class="wa-error"></p>
              </div>
              <template x-if="!needsPhone">
                <p class="text-caption text-ink-secondary">Candidates apply on Inthes — your number stays private.</p>
              </template>
              <template x-if="needsPhone">
                <div class="grid gap-lg sm:grid-cols-2">
                  <div data-field="contact_phone" @input="recheck('contact_phone')">
                    @include('site.ui.input', ['label' => 'Number candidates should call', 'model' => 'f.contact_phone', 'placeholder' => '10-digit mobile number', 'required' => true, 'prefix' => '+91', 'attrs' => 'inputmode=numeric maxlength=10 @input="digits(\'contact_phone\', 10)"', 'error' => 'errors.contact_phone'])
                  </div>
                  <template x-if="f.contact_mode === 'calls_whatsapp'">
                    <div class="space-y-sm">
                      <span class="wa-label">WhatsApp</span>
                      <label class="inline-flex h-[50px] cursor-pointer items-center gap-sm text-bodysm text-ink">
                        <input type="checkbox" x-model="f.same_whatsapp" class="h-4 w-4 accent-[#EB0401]">
                        Same number on WhatsApp
                      </label>
                      <template x-if="!f.same_whatsapp">
                        <div data-field="whatsapp_phone" @input="recheck('whatsapp_phone')">
                          @include('site.ui.input', ['label' => 'WhatsApp number', 'model' => 'f.whatsapp_phone', 'placeholder' => '10-digit mobile number', 'required' => true, 'prefix' => '+91', 'attrs' => 'inputmode=numeric maxlength=10 @input="digits(\'whatsapp_phone\', 10)"', 'error' => 'errors.whatsapp_phone'])
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </section>

          {{-- ── 4 · company ──────────────────────────────────────────── --}}
          <section class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">4</span><h2 class="text-h3 text-ink">Company</h2></div>

            <template x-if="orgs.length">
              <div class="mb-lg">
                <span class="wa-label">Hiring for</span>
                <div class="flex flex-wrap gap-xs">
                  <template x-for="o in orgs" :key="o.id">
                    <button type="button" @click="pickOrg(o.id)" :class="orgChoice === o.id && 'wa-chip-on'" class="wa-chip">
                      <span x-show="orgChoice === o.id" x-html="ICONS.check" class="text-primary [&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
                      <span x-text="o.name"></span>
                      <span x-show="o.verified" class="text-primary" title="Verified">@include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-[13px] w-[13px]'])</span>
                    </button>
                  </template>
                  <button type="button" @click="pickOrg('new')" :class="orgChoice === 'new' && 'wa-chip-on'" class="wa-chip">
                    <span x-html="ICONS.plus" class="[&>svg]:h-[14px] [&>svg]:w-[14px]"></span> Add a new company
                  </button>
                </div>
              </div>
            </template>

            {{-- A saved company: a summary, its document if it still needs one, and the full form on request. --}}
            <template x-if="pickedOrg && !coEditing">
              <div class="space-y-lg">
                <div class="flex items-start gap-md rounded-field border-hair border-hairline p-md">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-logo bg-primary-light text-h5 font-semibold text-primary-dark">
                    <template x-if="coLogoSrc()"><img :src="coLogoSrc()" alt="" class="h-full w-full object-cover"></template>
                    <template x-if="!coLogoSrc()"><span x-text="initials(co.name)"></span></template>
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-xs text-h5 text-ink"><span class="truncate" x-text="co.name"></span></p>
                    <p class="truncate text-caption text-ink-secondary" x-text="[co.industry, co.size && co.size + ' company', co.email].filter(Boolean).join(' · ') || 'Details not filled in'"></p>
                    <span class="wa-pill mt-xs" :class="Emp.orgStatePill(pickedOrg)" x-text="Emp.orgStateLabel(pickedOrg)"></span>
                  </div>
                  <button type="button" class="wa-btn-quiet shrink-0 !px-sm" @click="coEditing = true">
                    @include('admin.partials.icon', ['name' => 'pencil', 'class' => 'h-4 w-4']) Edit details
                  </button>
                </div>
                <template x-if="!co.has_doc">
                  <div data-field="co.doc">
                    <span class="wa-label">Company document<span class="text-primary"> *</span></span>
                    <label :class="coErrors.doc ? 'border-danger bg-danger-bg' : (co.doc_name ? 'border-success/40 bg-success-bg' : 'border-hairline bg-surface-muted hover:bg-hairline/50')"
                           class="flex cursor-pointer items-center gap-md rounded-field border-hair p-md transition-colors">
                      <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="coPick('doc', $event)">
                      <span class="flex h-9 w-9 shrink-0 items-center justify-center [&>svg]:h-6 [&>svg]:w-6" :class="co.doc_name ? 'text-success' : 'text-primary'" x-html="co.doc_name ? ICONS.fileText : ICONS.upload"></span>
                      <span class="min-w-0 flex-1">
                        <span class="block truncate text-bodysm font-semibold text-ink" x-text="co.doc_name || 'Upload company document'"></span>
                        <span class="block text-caption text-ink-secondary">GST certificate, shop licence or company registration — PDF, JPG or PNG</span>
                      </span>
                      <button type="button" x-show="co.doc_new" @click.prevent.stop="coRemove('doc')" aria-label="Remove" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-ink-muted hover:bg-surface">
                        @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-4 w-4'])
                      </button>
                    </label>
                    <p class="mt-xs text-caption text-ink-muted">Needed before the post can go live — it's what we check to verify your company.</p>
                    <p x-show="coErrors.doc" x-text="coErrors.doc" class="wa-error"></p>
                  </div>
                </template>
              </div>
            </template>

            <template x-if="!pickedOrg || coEditing">
              <div>
                <template x-if="pickedOrg">
                  <div class="mb-lg flex items-center justify-between gap-md rounded-field bg-surface-muted px-md py-sm">
                    <p class="text-caption text-ink-secondary">Changes here update this company everywhere it's used.</p>
                    <button type="button" class="shrink-0 text-caption font-semibold text-ink-secondary hover:text-ink" @click="pickOrg(orgChoice)">Discard changes</button>
                  </div>
                </template>
                @include('site.employer.partials.company-form')
              </div>
            </template>
          </section>

          {{-- submit --}}
          <div class="sticky bottom-0 z-20 -mx-page border-t border-hairline bg-canvas-alt/95 px-page py-md backdrop-blur-md sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
            <div class="flex flex-col-reverse gap-sm sm:flex-row sm:items-center sm:justify-end">
              <a :href="job ? jobUrl(job) : '/employer/jobs'" class="wa-btn-quiet hidden h-[50px] sm:inline-flex">Cancel</a>
              <button type="submit" class="wa-btn sm:min-w-[240px]" :disabled="busy">
                <span x-show="busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span x-text="busy ? busyText : submitLabel"></span>
              </button>
            </div>
            <p x-show="mode !== 'edit'" class="mt-sm hidden text-caption text-ink-muted sm:block sm:text-right">Next: choose a plan. Your post goes live after a quick review.</p>
          </div>
        </form>

        {{-- live preview + checklist --}}
        <aside class="hidden lg:block">
          <div class="sticky top-[92px] space-y-lg">
            <div>
              <p class="wa-section-title mb-sm">Preview</p>
              <article class="rounded-card border-hair border-hairline bg-surface p-lg shadow-card">
                <div class="flex items-start gap-md">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-logo bg-primary-light text-h5 font-semibold text-primary-dark">
                    <template x-if="coLogoSrc()"><img :src="coLogoSrc()" alt="" class="h-full w-full object-cover"></template>
                    <template x-if="!coLogoSrc()"><span x-text="initials(previewCompany)"></span></template>
                  </span>
                  <div class="min-w-0 flex-1">
                    <h3 class="truncate text-h4" :class="f.title.trim() ? 'text-ink' : 'text-ink-muted'" x-text="f.title.trim() || 'Job title'"></h3>
                    <p class="mt-[2px] flex items-center gap-xs truncate text-bodysm text-ink-secondary">
                      <span class="truncate" x-text="previewCompany"></span>
                      <span x-show="pickedOrg?.verified" class="shrink-0 text-primary">@include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-[14px] w-[14px]'])</span>
                    </p>
                  </div>
                </div>
                <div class="mt-md flex flex-wrap gap-xs">
                  <span class="rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary" x-text="f.city || 'City'"></span>
                  <span x-show="f.type" class="rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary" x-text="f.type"></span>
                  <span x-show="f.shift" class="rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary" x-text="f.shift"></span>
                  <span x-show="previewSalary" class="rounded-field bg-primary-light px-md py-[5px] text-tag font-semibold text-primary-dark" x-text="previewSalary"></span>
                </div>
                <p x-show="f.experience" class="mt-md text-bodysm text-ink-secondary" x-text="'Experience: ' + f.experience"></p>
                <div class="mt-md space-y-[3px] border-t border-hairline-divider pt-md text-caption text-ink-secondary">
                  <p x-show="f.min_education" x-text="'Education: ' + f.min_education + ' or above'"></p>
                  <p x-text="(parseInt(f.openings, 10) || 1) + ((parseInt(f.openings, 10) || 1) === 1 ? ' opening' : ' openings') + (f.preferred_gender && f.preferred_gender !== 'Any' ? ' · ' + f.preferred_gender + ' candidates' : '')"></p>
                  <p x-show="f.charges_fee === false" class="text-success">No fee charged to candidates</p>
                  <p x-show="f.charges_fee === true" class="text-warning">Candidates are charged a fee</p>
                </div>
                <div class="mt-md flex items-center justify-between">
                  <span class="text-caption text-ink-muted">Just now</span>
                  <span class="inline-flex h-9 items-center rounded-button border-btn border-primary px-lg text-btnghost font-semibold text-primary">Apply</span>
                </div>
              </article>
            </div>

            <div class="rounded-card border-hair border-hairline bg-surface p-lg">
              <div class="flex items-center justify-between">
                <p class="text-h5 text-ink">Required details</p>
                <span class="text-caption font-semibold" :class="doneCount === checklist.length ? 'text-success' : 'text-ink-secondary'" x-text="doneCount + '/' + checklist.length"></span>
              </div>
              <span class="mt-sm block h-[5px] overflow-hidden rounded-full bg-hairline"><span class="block h-full rounded-full bg-success transition-[width] duration-component" :style="'width:' + (doneCount / checklist.length * 100) + '%'"></span></span>
              <ul class="mt-md space-y-[6px]">
                <template x-for="c in checklist" :key="c.label">
                  <li class="flex items-center gap-sm text-bodysm" :class="c.ok ? 'text-ink' : 'text-ink-secondary'">
                    <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full [&>svg]:h-3 [&>svg]:w-3"
                          :class="c.ok ? 'bg-success text-ink-onPrimary' : 'border-[1.5px] border-hairline-strong'" x-html="c.ok ? ICONS.check : ''"></span>
                    <span x-text="c.label"></span>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-post'])
@endsection
