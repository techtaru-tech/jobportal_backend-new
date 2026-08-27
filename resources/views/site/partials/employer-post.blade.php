{{--
  Post a job.

  Three steps rather than one scrolling form of twenty fields — the same split
  the app's wizard uses, and for the same reason: a recruiter who missed a
  required field should find out on the screen that field is on, while it is
  still in front of them, not from a toast after they reach the bottom.

  There is no plan or payment step here. The API enforces the active-posting
  ceiling on `POST /recruiter/jobs` and answers 422 when it is hit, so the web
  does not need its own paywall — and upgrading is an in-app purchase anyway.
--}}
<template x-if="view === 'post'">
  <div class="animate-enter-up mx-auto max-w-[820px]">

    <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
      <div class="min-w-0">
        <span class="block text-kicker text-ink-secondary">NEW POSTING</span>
        <h1 class="mt-[2px] text-h1 text-ink">Post a job</h1>
      </div>
      <button @click="go('jobs')"
              class="inline-flex h-10 items-center justify-center rounded-button px-md text-btnghost font-semibold text-ink-muted
                     transition-colors duration-micro hover:bg-surface-muted hover:text-ink">
        Cancel
      </button>
    </div>

    {{-- Step rail. Backwards only: jumping forward would skip the validation
         `nextStep` exists to apply. --}}
    <div class="mb-lg">
      <div class="flex gap-[4px]">
        <template x-for="(label, i) in STEPS" :key="label">
          <button @click="i < step && (step = i)" :disabled="i > step"
                  class="h-[4px] flex-1 rounded-[2px] transition-colors duration-micro"
                  :class="i <= step ? 'bg-primary' : 'bg-hairline'"
                  :aria-label="label"></button>
        </template>
      </div>
      <p class="mt-sm text-caption text-ink-muted">
        Step <span x-text="step + 1"></span> of <span x-text="STEPS.length"></span> ·
        <span class="text-ink-secondary" x-text="STEPS[step]"></span>
      </p>
    </div>

    <div class="rounded-card border-hair border-hairline bg-surface p-xl shadow-card">

      {{-- ── step 1: job details ──────────────────────────────────────── --}}
      <div x-show="step === 0" class="space-y-lg">
        <label class="block">
          <span class="mb-[5px] block text-label text-ink-secondary">Job title *</span>
          <input x-model="form.title" maxlength="120" placeholder="e.g. ICU Staff Nurse"
                 class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                        border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                        focus:border-focus focus:border-primary focus:shadow-glow">
        </label>

        <div class="grid grid-cols-1 gap-lg sm:grid-cols-2">
          @include('site.partials.employer-select', [
            'model' => 'form.role', 'label' => 'Role *', 'placeholder' => 'Choose a role',
            'items' => 'options.categories',
          ])
          @include('site.partials.employer-select', [
            'model' => 'form.experience', 'label' => 'Experience', 'placeholder' => 'Any experience',
            'items' => 'options.experience_bands',
          ])
          @include('site.partials.employer-select', [
            'model' => 'form.type', 'label' => 'Job type *', 'placeholder' => 'Choose a type',
            'items' => 'options.job_types',
          ])
          @include('site.partials.employer-select', [
            'model' => 'form.shift', 'label' => 'Shift *', 'placeholder' => 'Choose a shift',
            'items' => 'options.shifts',
          ])
        </div>

        <div class="grid grid-cols-1 gap-lg sm:grid-cols-2">
          <label class="block">
            <span class="mb-[5px] block text-label text-ink-secondary">City *</span>
            {{-- A text input with a datalist, not a select: the city list is
                 admin-editable and freeform is accepted by the API, so a
                 recruiter in a town nobody has listed yet is not blocked. --}}
            <input x-model="form.city" list="employer-cities" maxlength="80" placeholder="e.g. Jaipur"
                   class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                          border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                          focus:border-focus focus:border-primary focus:shadow-glow">
            <datalist id="employer-cities">
              <template x-for="city in options.cities" :key="city">
                <option :value="city"></option>
              </template>
            </datalist>
          </label>

          <label class="block">
            <span class="mb-[5px] block text-label text-ink-secondary">Pincode</span>
            <input x-model="form.pincode" inputmode="numeric" maxlength="10" placeholder="302001"
                   class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                          border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                          focus:border-focus focus:border-primary focus:shadow-glow">
          </label>
        </div>

        <div>
          <span class="mb-[5px] block text-label text-ink-secondary">Monthly salary (₹)</span>
          <div class="flex items-center gap-sm">
            <input x-model="form.salary_min" type="number" min="0" placeholder="Minimum"
                   class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                          border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                          focus:border-focus focus:border-primary focus:shadow-glow">
            <span class="shrink-0 text-ink-muted">–</span>
            <input x-model="form.salary_max" type="number" min="0" placeholder="Maximum"
                   class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                          border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                          focus:border-focus focus:border-primary focus:shadow-glow">
          </div>
          <p class="mt-xs text-caption text-ink-muted">
            Optional, but postings that state a salary get noticeably more applicants.
          </p>
        </div>

        <label class="block">
          <span class="mb-[5px] block text-label text-ink-secondary">About the role</span>
          <textarea x-model="form.about" rows="5" maxlength="4000"
                    placeholder="What the person will be doing, who they report to, what the unit is like…"
                    class="w-full resize-y rounded-field bg-surface-muted px-md py-md text-input leading-normal text-ink placeholder:text-ink-muted
                           border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                           focus:border-focus focus:border-primary focus:shadow-glow"></textarea>
        </label>
      </div>

      {{-- ── step 2: requirements ─────────────────────────────────────── --}}
      <div x-show="step === 1" x-cloak class="space-y-xl">
        @include('site.partials.employer-chips', [
          'label' => 'Qualifications', 'list' => 'form.qualifications',
          'items' => 'options.qualifications',
          'hint' => 'Tap to add. These are suggestions — candidates are not filtered out for missing one.',
        ])

        @include('site.partials.employer-chips', [
          'label' => 'Skills', 'list' => 'form.skills',
          'items' => 'options.skills',
          'hint' => 'What the day actually needs — ventilator handling, phlebotomy, triage.',
        ])

        @include('site.partials.employer-freelist', [
          'label' => 'What they will do', 'list' => 'form.duties', 'draft' => 'dutyDraft',
          'placeholder' => 'Add a duty and press Enter',
        ])

        @include('site.partials.employer-freelist', [
          'label' => 'Benefits', 'list' => 'form.benefits', 'draft' => 'benefitDraft',
          'placeholder' => 'Add a benefit and press Enter',
        ])

        {{--
          The Smart Apply gate. Worth explaining rather than labelling: a
          posting that demands every field converts badly, because the candidate
          has to go and fill in their profile before they can apply at all.
        --}}
        <div>
          <span class="block text-label text-ink-secondary">Required to apply</span>
          <p class="mt-[2px] text-caption text-ink-muted">
            Candidates must have these on their profile before they can apply. Ask for less and
            more people get through.
          </p>
          <div class="mt-md flex flex-wrap gap-xs">
            <template x-for="option in REQUIRED_FIELD_OPTIONS" :key="option.value">
              <button type="button" @click="toggleIn(form.required_fields, option.value)"
                      :class="form.required_fields.includes(option.value)
                        ? 'border-primary bg-primary-light font-semibold text-primary-dark'
                        : 'border-transparent bg-surface-muted text-ink hover:bg-hairline'"
                      class="inline-flex items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip transition-colors duration-150">
                <span x-show="form.required_fields.includes(option.value)" x-html="ICONS.check"
                      class="[&>svg]:h-[13px] [&>svg]:w-[13px] text-primary"></span>
                <span x-text="option.label"></span>
              </button>
            </template>
          </div>
        </div>
      </div>

      {{-- ── step 3: employer ─────────────────────────────────────────── --}}
      <div x-show="step === 2" x-cloak class="space-y-lg">
        <div>
          <span class="mb-[5px] block text-label text-ink-secondary">Posting for *</span>

          <template x-if="organisations.length">
            <div class="space-y-sm">
              <template x-for="org in organisations" :key="org.id">
                <button type="button" @click="form.organisation_id = org.id"
                        :class="form.organisation_id === org.id
                          ? 'border-primary bg-primary-light'
                          : 'border-hairline bg-surface hover:border-hairline-strong'"
                        class="flex w-full items-center gap-md rounded-card border-hair p-md text-left transition-colors duration-micro">
                  <span aria-hidden="true" x-text="initials(org.name)" style="font-size:11.6px"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-bodysm font-semibold text-ink" x-text="org.name"></span>
                    <span class="block truncate text-caption text-ink-muted" x-text="org.city || 'No city set'"></span>
                  </span>
                  {{-- Stated plainly: an unverified employer's postings do not
                       reach candidates even after approval. --}}
                  <span class="inline-flex shrink-0 items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                        :class="org.verified ? 'bg-success-bg text-success' : 'bg-warning-bg text-warning'"
                        x-text="org.verified ? 'Verified' : 'Not verified'"></span>
                </button>
              </template>
            </div>
          </template>

          <button type="button" @click="orgOpen = true"
                  class="mt-sm inline-flex h-[42px] items-center justify-center gap-sm rounded-button border-btn border-hairline bg-surface px-lg text-btnghost font-semibold text-ink
                         transition-colors duration-micro hover:border-hairline-strong hover:bg-surface-muted">
            <span x-html="ICONS.plus" class="[&>svg]:h-4 [&>svg]:w-4"></span>
            <span>Add an employer</span>
          </button>
        </div>

        <label class="block">
          <span class="mb-[5px] block text-label text-ink-secondary">A line about the workplace</span>
          <input x-model="form.organisation_note" maxlength="255"
                 placeholder="e.g. Multi-speciality hospital, 450 beds"
                 class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                        border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                        focus:border-focus focus:border-primary focus:shadow-glow">
        </label>

        <div class="rounded-card border-hair border-primary-line bg-primary-light p-lg">
          <p class="text-bodysm font-semibold text-primary-dark">What happens when you submit</p>
          <ul class="mt-sm space-y-xs">
            @foreach ([
                'An admin reviews the posting — usually the same day.',
                'You are notified the moment it is approved or turned away.',
                'Approved postings appear on the site and in the app together.',
            ] as $line)
              <li class="flex items-start gap-sm text-caption text-ink-secondary">
                <span class="mt-[5px] h-[5px] w-[5px] shrink-0 rounded-full bg-primary"></span>
                <span>{{ $line }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>

      {{-- ── footer ───────────────────────────────────────────────────── --}}
      <div class="mt-xl flex items-center gap-sm border-t border-hairline-divider pt-lg">
        <button x-show="step > 0" @click="previousStep()"
                class="inline-flex h-[50px] flex-1 items-center justify-center gap-sm rounded-button border-btn border-hairline bg-surface px-md text-btn font-semibold text-ink
                       transition-colors duration-micro hover:border-hairline-strong hover:bg-surface-muted">
          @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-[18px] w-[18px]'])
          <span>Back</span>
        </button>

        <button x-show="!isLastStep" @click="nextStep()"
                class="inline-flex h-[50px] flex-[2] items-center justify-center gap-sm rounded-button bg-primary px-md text-btn font-semibold text-ink-onPrimary shadow-button
                       transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
          <span>Continue</span>
          @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])
        </button>

        <button x-show="isLastStep" x-cloak @click="submitPost()" :disabled="posting"
                class="inline-flex h-[50px] flex-[2] items-center justify-center gap-sm rounded-button bg-primary px-md text-btn font-semibold text-ink-onPrimary shadow-button
                       transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]
                       disabled:cursor-not-allowed">
          <template x-if="posting">
            @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-[18px] w-[18px] animate-spin'])
          </template>
          <span x-show="!posting">Submit for approval</span>
        </button>
      </div>
    </div>
  </div>
</template>

@include('site.partials.employer-org-sheet')
