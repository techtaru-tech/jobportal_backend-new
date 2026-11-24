@extends('site.employer.layout')

@section('title', ($jobId ? 'Edit job' : 'Post a job').' — Inthes employer')
@section('employer_key', $jobId ? 'jobs' : 'post')

@php
  $card = 'rounded-card border-hair border-hairline bg-surface p-lg shadow-card sm:p-xl';
  $sectionHead = 'mb-lg flex items-center gap-md';
  $num = 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink text-caption font-bold text-ink-onPrimary';
  $chipIcon = 'text-ink-muted [&>svg]:h-[15px] [&>svg]:w-[15px]';
  $addLink = 'inline-flex min-h-[32px] items-center gap-xs text-btnghost text-primary hover:underline';
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
      {{-- header: the app's "POST A JOB · Hire the right people, fast" and its 4-step bar --}}
      <div class="mx-auto mb-lg max-w-[760px]">
        <div class="flex items-start gap-md">
          <div class="min-w-0 flex-1">
            <p class="text-kicker uppercase tracking-[1.2px] text-ink-secondary" x-text="mode === 'edit' ? 'Edit job' : 'Post a job'"></p>
            <h1 class="mt-xs text-h1 text-ink" x-text="mode === 'edit' ? (job.title + ' · ' + job.code) : (mode === 'draft' ? 'Finish your job post' : 'Hire the right people, fast')"></h1>
          </div>
          <template x-if="job">
            <span class="wa-pill mt-xs" :class="statusPill(job.posting_status)" x-text="statusLabel(job.posting_status)"></span>
          </template>
        </div>
        @include('site.employer.partials.post-steps', ['current' => 'wiz'])
      </div>

      <template x-if="mode === 'edit' && job.posting_status === 'active'">
        <div class="mx-auto mb-lg flex max-w-[760px] items-start gap-sm rounded-field border-hair border-warning/25 bg-warning-bg px-lg py-md text-bodysm text-warning">
          <span class="mt-[1px] shrink-0">@include('admin.partials.icon', ['name' => 'info', 'class' => 'h-[18px] w-[18px]'])</span>
          This job is live. Saving changes sends it back for approval, and it is paused for candidates until an admin reviews it.
        </div>
      </template>
      <template x-if="mode === 'edit' && job.posting_status === 'rejected' && job.rejection_reason">
        <div class="mx-auto mb-lg flex max-w-[760px] items-start gap-sm rounded-field border-hair border-danger/25 bg-danger-bg px-lg py-md text-bodysm text-danger">
          <span class="mt-[1px] shrink-0">@include('admin.partials.icon', ['name' => 'xCircle', 'class' => 'h-[18px] w-[18px]'])</span>
          <span x-text="'Not approved: ' + job.rejection_reason + ' — fix it below and save to resubmit.'"></span>
        </div>
      </template>
      <template x-if="mode === 'draft'">
        <div class="mx-auto mb-lg flex max-w-[760px] items-start gap-sm rounded-field border-hair border-primary-line bg-primary-light px-lg py-md text-bodysm text-primary-dark">
          <span class="mt-[1px] shrink-0">@include('admin.partials.icon', ['name' => 'wallet', 'class' => 'h-[18px] w-[18px]'])</span>
          <span>This post is saved. Review it, then <a :href="noticeHref(job, 'plan')" class="font-semibold underline">choose a plan</a> to make it live.</span>
        </div>
      </template>

      <div class="mx-auto max-w-[760px]">
        <form class="min-w-0 space-y-lg" @submit.prevent="wizNext()" novalidate>

          {{-- ── 1 · job details ─────────────────────────────────────── --}}
          <section x-show="wiz === 0" class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">1</span><h2 class="min-w-0 text-h3 text-ink">Job Details</h2></div>
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
                    @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-[18px] w-[18px]']) Add job description
                  </button>
                </template>
                <template x-if="showDescription">
                  <div>
                    @include('site.ui.textarea', ['label' => 'Job description', 'model' => 'f.about', 'rows' => 5, 'max' => 4000, 'placeholder' => 'Briefly describe the role and the team…'])
                    <button type="button" class="-mt-md min-h-[32px] text-caption text-ink-secondary hover:text-ink" @click="showDescription = false; f.about = ''">Remove description</button>
                  </div>
                </template>
              </div>

              <div class="grid gap-lg sm:grid-cols-[160px_1fr]">
                <div data-field="openings" @input="recheck('openings')">
                  @include('site.ui.input', ['label' => 'Number of openings', 'model' => 'f.openings', 'placeholder' => 'e.g. 5', 'required' => true, 'attrs' => 'inputmode=numeric maxlength=4 @input="digits(\'openings\', 4)"', 'error' => 'errors.openings'])
                </div>
                <div data-field="salary">
                  <span class="wa-label">Monthly salary @include('site.ui.mark', ['required' => true])</span>
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
                  @include('site.ui.chips', ['label' => 'Job shift', 'model' => 'f.shift', 'iconKind' => 'shift', 'items' => "opt('shifts')", 'labels' => "Object.fromEntries(opt('shifts').map((s) => [s, shiftLabel(s)]))", 'required' => true, 'error' => 'errors.shift'])
                </div>
                <div data-field="type" @click="$nextTick(() => recheck('type'))">
                  @include('site.ui.chips', ['label' => 'Job type', 'model' => 'f.type', 'iconKind' => 'jobType', 'items' => "opt('job_types')", 'required' => true, 'error' => 'errors.type'])
                </div>
              </div>

              <div>
                <span class="wa-label">Office location @include('site.ui.mark', ['required' => true])</span>
                {{-- The app's two links: current position, or a spot picked on the map. --}}
                <div class="mb-md flex flex-wrap items-center gap-x-xl gap-y-sm">
                  <button type="button" @click="useLocation()" :disabled="locating" class="inline-flex min-h-[36px] items-center gap-sm text-btn font-semibold text-primary hover:underline disabled:opacity-60">
                    <span x-html="locating ? ICONS.loader : ICONS.locate" :class="locating && 'animate-spin'" class="[&>svg]:h-5 [&>svg]:w-5"></span>
                    Use current location
                  </button>
                  <button type="button" @click="chooseOnMap()" :disabled="locating" class="inline-flex min-h-[36px] items-center gap-sm text-btn font-semibold text-primary hover:underline disabled:opacity-60">
                    <span x-html="ICONS.map" class="[&>svg]:h-5 [&>svg]:w-5"></span> Pick on map
                  </button>
                </div>
                <template x-if="f.latitude != null && locRough">
                  <p class="mb-md flex items-start gap-xs rounded-field bg-warning-bg px-md py-sm text-caption text-warning" data-rough-location>
                    <span x-html="ICONS.alert" class="mt-[1px] shrink-0 [&>svg]:h-4 [&>svg]:w-4"></span>
                    <span>This device could only find the location roughly (<span x-text="locRoughLabel"></span>), so the address may be a nearby area. Check it, or use <b>Pick on map</b> to pin the exact spot.</span>
                  </p>
                </template>
                <div class="grid gap-lg sm:grid-cols-2">
                  <div data-field="state">
                    <label class="block">
                      <span class="wa-label">State @include('site.ui.mark', ['required' => true])</span>
                      <div class="relative">
                        <span class="pointer-events-none absolute left-md top-1/2 z-[1] -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'map', 'class' => 'h-[18px] w-[18px]'])</span>
                        <select :value="f.state" @change="pickState($event.target.value)" class="wa-input cursor-pointer appearance-none pl-[44px] pr-10">
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
                      <span class="wa-label">City / district @include('site.ui.mark', ['required' => true])</span>
                      <div class="relative">
                        <span class="pointer-events-none absolute left-md top-1/2 z-[1] -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'mapPin', 'class' => 'h-[18px] w-[18px]'])</span>
                        <select x-model="f.city" :disabled="!f.state" class="wa-input cursor-pointer appearance-none pl-[44px] pr-10 disabled:cursor-not-allowed disabled:opacity-60">
                          <option value="" x-text="f.state ? 'Select the city' : 'Pick a state first'"></option>
                          <template x-for="c in cities" :key="c"><option :value="c" x-text="c" :selected="c === f.city"></option></template>
                        </select>
                        <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
                      </div>
                    </label>
                    <p x-show="errors.city" x-text="errors.city" class="wa-error"></p>
                  </div>
                </div>
                <div class="mt-lg">
                  @include('site.ui.input', ['label' => 'Address', 'model' => 'f.locality', 'placeholder' => 'House / flat no., street, area — e.g. 21-987, Jeedimetla', 'attrs' => 'maxlength=120'])
                </div>
                <p x-show="f.city" class="mt-sm flex items-center gap-sm text-caption text-ink-secondary">
                  <span x-html="ICONS.info" class="text-primary [&>svg]:h-4 [&>svg]:w-4"></span>
                  <span x-text="'You will receive candidates from ' + f.city"></span>
                </p>
              </div>

              <div data-field="charges_fee">
                @include('site.ui.yesno', ['label' => 'For this job, will you charge any fee from candidates?', 'model' => 'f.charges_fee', 'required' => true])
                <p x-show="errors.charges_fee" x-text="errors.charges_fee" class="wa-error"></p>
              </div>
            </div>
          </section>

          {{-- ── 2 · candidate requirements ──────────────────────────── --}}
          <section x-show="wiz === 1" class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">2</span><h2 class="min-w-0 text-h3 text-ink">Candidate Requirements</h2></div>
            <div class="space-y-xl">
              <div data-field="min_education" @click="$nextTick(() => recheck('min_education'))">
                @include('site.ui.chips', ['label' => 'Minimum education', 'model' => 'f.min_education', 'iconKind' => 'education', 'items' => "opt('education_levels')", 'required' => true, 'error' => 'errors.min_education'])
              </div>
              <div data-field="experience" @click="$nextTick(() => recheck('experience'))">
                @include('site.ui.chips', ['label' => 'Experience', 'model' => 'f.experience', 'items' => 'experienceOptions', 'required' => true, 'error' => 'errors.experience'])
              </div>
              @include('site.ui.chips', ['label' => 'Preferred gender', 'model' => 'f.preferred_gender', 'iconKind' => 'gender', 'items' => "opt('gender_preferences')", 'labels' => "({ Any: 'Any gender' })", 'required' => true])

              <div>
                <template x-if="!showMoreReqs">
                  <button type="button" @click="showMoreReqs = true" class="{{ $addLink }}">
                    @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-[18px] w-[18px]']) Add skills & qualifications
                  </button>
                </template>
                <template x-if="showMoreReqs">
                  <div class="space-y-xl">
                    @include('site.ui.multi-add', ['label' => 'Skills required', 'model' => 'f.skills', 'items' => "opt('professional_skills')", 'placeholder' => 'Add a skill'])
                    @include('site.ui.multi-add', ['label' => 'Specific qualifications', 'model' => 'f.qualifications', 'items' => "opt('qualifications')", 'placeholder' => 'e.g. B.Sc Nursing, DMLT'])
                  </div>
                </template>
              </div>
            </div>
          </section>

          {{-- ── 2b · contact preferences (part of the requirements step) ── --}}
          <section x-show="wiz === 1" class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">@include('admin.partials.icon', ['name' => 'phone', 'class' => 'h-[14px] w-[14px]'])</span><h2 class="min-w-0 text-h3 text-ink">Contact Preferences</h2></div>
            <div class="space-y-lg" data-field="contact_mode">
              <div>
                <span class="wa-label">Manage candidates through @include('site.ui.mark', ['required' => true])</span>
                <div class="flex flex-wrap gap-xs">
                  <template x-for="[id, label] in Object.entries(contactModes)" :key="id">
                    <button type="button" @click="f.contact_mode = id; errors.contact_mode = ''" :class="f.contact_mode === id && 'wa-chip-on'" class="wa-chip">
                      <span x-html="id === 'calls' ? ICONS.phone : (id === 'calls_whatsapp' ? ICONS.whatsapp : ICONS.clipboard)" class="{{ $chipIcon }}" :class="id === 'calls_whatsapp' ? '!text-[#25D366]' : (f.contact_mode === id && '!text-primary')"></span>
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

          {{-- ── 3 · company ──────────────────────────────────────────── --}}
          <section x-show="wiz === 2" class="{{ $card }}">
            <div class="{{ $sectionHead }}"><span class="{{ $num }}">3</span><h2 class="min-w-0 text-h3 text-ink">Company Details</h2></div>

            <template x-if="orgs.length">
              <div class="mb-lg">
                <span class="wa-label">Hiring for @include('site.ui.mark', ['required' => true])</span>
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

            {{-- A saved company: a summary, and the full form on request. --}}
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

          {{-- footer: Back · Continue (the app's bottom bar) --}}
          <div class="sticky bottom-0 z-20 -mx-page border-t border-hairline bg-canvas-alt/95 px-page py-md backdrop-blur-md sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
            <div class="flex items-center gap-sm">
              <button type="button" x-show="wiz > 0" @click="wizBack()" :disabled="busy" aria-label="Back" class="wa-btn-quiet h-[54px] w-[54px] shrink-0 border-hair border-hairline !px-0">
                <span x-html="ICONS.arrowLeft" class="[&>svg]:h-5 [&>svg]:w-5"></span>
              </button>
              <a x-show="wiz === 0" :href="job ? jobUrl(job) : '/employer/jobs'" class="wa-btn-quiet hidden h-[54px] sm:inline-flex">Cancel</a>
              <button type="submit" class="wa-btn ml-auto flex-1 sm:min-w-[240px] sm:flex-none" :disabled="busy" data-post-continue>
                <span x-show="busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span x-text="busy ? busyText : wizLabel"></span>
              </button>
            </div>
            <p x-show="mode !== 'edit' && wiz === 2" class="mt-sm text-center text-caption text-ink-muted sm:text-right">Next: choose a plan. Your post goes live after a quick review.</p>
          </div>
        </form>

      </div>
    </div>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-post'])
@endsection
