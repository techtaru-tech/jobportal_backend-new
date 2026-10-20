{{--
  Quick Apply — a modal over whatever page the candidate is on. Driven by
  `$store.qa` and opened with `Inthes.quickApply(job)` (public/js/site/quick-apply.js).

  One screen: a confirm with the profile it will send, or — when the job asks
  for something the profile lacks — one short form with only those fields.
  Phones get it as a full-height sheet.
--}}
<div x-data x-show="$store.qa.open" x-cloak
     class="fixed inset-0 z-[90] flex items-end justify-center bg-ink/50 backdrop-blur-[2px] sm:items-center sm:px-page"
     x-transition.opacity.duration.200ms
     @keydown.escape.window="$store.qa.open && $store.qa.close()">
  <div @click.outside="!$store.auth.modal.open && $store.qa.close()" role="dialog" aria-modal="true" aria-labelledby="qa-title"
       class="relative flex max-h-[100dvh] w-full max-w-[560px] flex-col overflow-hidden rounded-t-sheet bg-surface shadow-raised sm:max-h-[90vh] sm:rounded-dialog animate-pop-in">

    {{-- Header: the job this is for --}}
    <div class="flex items-start gap-md border-b border-hairline-divider px-xl py-lg">
      <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[13px] font-bold text-primary-dark"
            x-text="initials($store.qa.job?.organisation)"></span>
      <div class="min-w-0 flex-1">
        <p class="text-caption text-ink-muted" x-text="$store.qa.stage === 'done' ? 'Application sent' : 'Quick apply'"></p>
        <h2 id="qa-title" class="truncate text-h4 text-ink" x-text="$store.qa.job?.title || 'Loading…'"></h2>
        <p class="truncate text-bodysm text-ink-secondary" x-text="[$store.qa.job?.organisation, $store.qa.job?.city].filter(Boolean).join(' · ')"></p>
      </div>
      <button type="button" @click="$store.qa.close()" aria-label="Close" :disabled="$store.qa.busy"
              class="-mr-sm inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted hover:text-ink">
        @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
      </button>
    </div>

    <div class="flex-1 overflow-y-auto px-xl py-lg">
      {{-- Loading / sending --}}
      <template x-if="$store.qa.stage === 'loading' || $store.qa.stage === 'sending'">
        <div class="flex flex-col items-center py-xxl text-center">
          @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-7 w-7 animate-spin text-primary'])
          <p class="mt-md text-bodysm text-ink-secondary" x-text="$store.qa.stage === 'sending' ? 'Sending your application…' : 'Checking your profile…'"></p>
        </div>
      </template>

      {{-- Error --}}
      <template x-if="$store.qa.stage === 'error'">
        <div class="flex flex-col items-center py-xl text-center">
          <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-danger-bg text-danger">
            @include('admin.partials.icon', ['name' => 'alert', 'class' => 'h-6 w-6'])
          </span>
          <p class="mt-md text-h5 text-ink" x-text="$store.qa.error || 'Something went wrong'"></p>
          <button type="button" @click="$store.qa.close()" class="wa-btn-ghost-sm mt-lg">Close</button>
        </div>
      </template>

      {{-- Confirm: everything is in the profile --}}
      <template x-if="$store.qa.stage === 'confirm'">
        <div>
          <p class="text-body text-ink">
            Apply to <span class="font-semibold" x-text="$store.qa.job?.title"></span> at
            <span class="font-semibold" x-text="$store.qa.job?.organisation"></span> with your profile.
          </p>
          <div class="mt-lg rounded-card border-hair border-hairline bg-surface-muted p-lg">
            <div class="flex items-center gap-md">
              <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-light text-[13px] font-semibold text-primary-dark">
                <template x-if="$store.auth.me?.photo_url"><img :src="$store.auth.me.photo_url" alt="" class="h-full w-full object-cover"></template>
                <template x-if="!$store.auth.me?.photo_url"><span x-text="initials($store.auth.name || $store.auth.phone)"></span></template>
              </span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-h5 text-ink" x-text="$store.auth.name"></p>
                <p class="text-caption text-ink-muted" x-text="'+91 ' + $store.auth.phone"></p>
              </div>
              <span class="wa-pill bg-primary-light text-primary-dark" x-text="($store.auth.me?.profile_strength ?? 0) + '% profile'"></span>
            </div>
            <dl class="mt-md grid grid-cols-2 gap-md text-bodysm">
              <template x-for="row in [
                ['Qualification', $store.auth.me?.qualification || ($store.auth.me?.educations || [])[0]?.qualification],
                ['Experience', $store.auth.me?.experience],
                ['Preferred cities', ($store.auth.me?.location || []).join(', ')],
                ['Resume', $store.auth.me?.resume ? 'Attached' : ''],
              ].filter(r => r[1])" :key="row[0]">
                <div class="min-w-0"><dt class="text-caption text-ink-muted" x-text="row[0]"></dt><dd class="truncate text-ink" x-text="row[1]"></dd></div>
              </template>
            </dl>
          </div>
          <p x-show="$store.qa.error" x-text="$store.qa.error" class="wa-error"></p>
          <p class="mt-lg text-caption text-ink-secondary">
            The employer sees this profile and your resume.
            <a href="{{ url('/account') }}" class="font-semibold text-primary-dark hover:underline">Add more to your profile to stand out</a>
          </p>
        </div>
      </template>

      {{-- Form: only what this job still needs --}}
      <template x-if="$store.qa.stage === 'form'">
        <form id="qa-form" @submit.prevent="$store.qa.saveAndApply()" class="space-y-lg" novalidate>
          <div class="rounded-field bg-primary-light px-md py-sm text-bodysm text-primary-dark">
            <span class="font-semibold">This job needs a few details.</span>
            We'll save them to your profile and send your application.
          </div>

          <template x-if="$store.qa.has('name')">
            @include('site.ui.input', ['label' => 'Full name', 'model' => '$store.qa.form.name', 'required' => true, 'placeholder' => 'As on your certificates', 'attrs' => 'maxlength=120 autocomplete=name', 'error' => '$store.qa.errors.name'])
          </template>
          <template x-if="$store.qa.has('gender')">
            @include('site.ui.chips', ['label' => 'Gender', 'model' => '$store.qa.form.gender', 'items' => "opt('genders')", 'required' => true, 'error' => '$store.qa.errors.gender'])
          </template>
          <template x-if="$store.qa.has('dob')">
            <label class="block">
              <span class="wa-label">Date of birth<span class="text-primary"> *</span></span>
              <input type="date" x-model="$store.qa.form.dob" :min="$store.qa.dobMin" :max="$store.qa.dobMax" class="wa-input">
              <p x-show="$store.qa.errors.dob" x-text="$store.qa.errors.dob" class="wa-error"></p>
            </label>
          </template>
          <template x-if="$store.qa.has('address')">
            <label class="block">
              <span class="wa-label">Address<span class="text-primary"> *</span></span>
              <textarea x-model="$store.qa.form.address" rows="2" maxlength="255" placeholder="Area, city" class="wa-textarea"></textarea>
              <p x-show="$store.qa.errors.address" x-text="$store.qa.errors.address" class="wa-error"></p>
            </label>
          </template>
          <template x-if="$store.qa.has('qualification')">
            @include('site.ui.select', ['label' => 'Highest qualification', 'model' => '$store.qa.form.qualification', 'items' => "opt('qualifications')", 'placeholder' => 'Select qualification', 'required' => true, 'error' => '$store.qa.errors.qualification'])
          </template>
          <template x-if="$store.qa.has('experience')">
            @include('site.ui.chips', ['label' => 'Total experience', 'model' => '$store.qa.form.experience', 'items' => "opt('experience_bands')", 'required' => true, 'error' => '$store.qa.errors.experience'])
          </template>
          <template x-if="$store.qa.has('location')">
            @include('site.ui.chips', ['label' => 'Preferred cities', 'hint' => 'Where do you want to work? Pick one or more.', 'model' => '$store.qa.form.location', 'items' => "opt('cities')", 'multi' => true, 'required' => true, 'error' => '$store.qa.errors.location'])
          </template>

          <template x-if="$store.qa.needsResume">
            <div class="flex items-start gap-md rounded-field border-hair border-hairline bg-surface-muted p-md">
              <span class="text-primary">@include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-5 w-5'])</span>
              <p class="text-bodysm text-ink-secondary"><span class="font-semibold text-ink">We'll create your resume from your profile.</span> You can download or replace it later from your account.</p>
            </div>
          </template>

          <p x-show="$store.qa.error" x-text="$store.qa.error" class="wa-error"></p>
          <p class="text-caption text-ink-secondary">
            <a href="{{ url('/account') }}" class="font-semibold text-primary-dark hover:underline">Add more to your profile to stand out</a>
            — photo, skills and work history help employers pick you.
          </p>
        </form>
      </template>

      {{-- Done --}}
      <template x-if="$store.qa.stage === 'done' && $store.qa.app">
        <div>
          <div class="flex flex-col items-center text-center">
            <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-success-bg text-success animate-pop-in">
              @include('admin.partials.icon', ['name' => 'checkCircle', 'class' => 'h-8 w-8'])
            </span>
            <h3 class="mt-md text-h2 text-ink">Application sent</h3>
            <p class="mt-xs text-bodysm text-ink-secondary">Good luck! We'll tell you the moment the employer replies.</p>
            <div class="mt-md rounded-field bg-surface-muted px-lg py-sm">
              <span class="text-caption text-ink-muted">Application ID</span>
              <p class="font-mono text-bodysm font-semibold text-ink" x-text="$store.qa.app.id"></p>
            </div>
          </div>
          <h4 class="wa-section-title mt-xl">What happens next</h4>
          <ol class="mt-md space-y-md">
            @foreach (['The employer reviews your profile and resume.', 'Shortlisted? They can message you right here.', 'You are notified the moment your status changes.'] as $i => $line)
              <li class="flex gap-md"><span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-light text-caption font-bold text-primary-dark">{{ $i + 1 }}</span><span class="text-bodysm text-ink">{{ $line }}</span></li>
            @endforeach
          </ol>
        </div>
      </template>
    </div>

    {{-- Footer actions --}}
    <div x-show="['confirm', 'form', 'done'].includes($store.qa.stage)" class="flex flex-col-reverse gap-sm border-t border-hairline-divider px-xl py-lg sm:flex-row sm:justify-end"
         style="padding-bottom: max(16px, env(safe-area-inset-bottom))">
      <template x-if="$store.qa.stage === 'done'">
        <div class="flex w-full flex-col-reverse gap-sm sm:flex-row sm:justify-end">
          <button type="button" @click="$store.qa.close()" class="wa-btn-ghost-sm">Keep browsing</button>
          <a :href="'/account/applications/' + encodeURIComponent($store.qa.app?.id || '')" class="wa-btn-sm">Track application</a>
        </div>
      </template>
      <template x-if="$store.qa.stage !== 'done'">
        <div class="flex w-full flex-col-reverse gap-sm sm:flex-row sm:justify-end">
          <button type="button" @click="$store.qa.close()" :disabled="$store.qa.busy" class="wa-btn-quiet">Cancel</button>
          <button type="button" x-show="$store.qa.stage === 'confirm'" @click="$store.qa.submit()" :disabled="$store.qa.busy" class="wa-btn-sm">Submit application</button>
          <button type="submit" form="qa-form" x-show="$store.qa.stage === 'form'" :disabled="$store.qa.busy" class="wa-btn-sm">
            <span x-show="$store.qa.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span>
            Save &amp; apply
          </button>
        </div>
      </template>
    </div>
  </div>
</div>
