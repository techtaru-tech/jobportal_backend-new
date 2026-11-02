{{-- "Your post is under review" / "Your post is live" (post_job submitted view),
     after paying or posting on a plan credit. Binds to `submitted` (the job);
     the component has `postAnother()` and spreads Emp.submittedDoc() in.
     The business document is asked for here, after payment — never before
     (BusinessDocumentCard): picked and uploaded in place, then the stage
     is read again. --}}
<div class="wa-card mx-auto max-w-[620px] px-xl py-xxl text-center">
  <span class="mx-auto flex h-[76px] w-[76px] items-center justify-center rounded-full [&>svg]:h-10 [&>svg]:w-10"
        :class="submitted.hiring_stage === 'live' ? 'bg-success-bg text-success' : 'bg-warning-bg text-warning'"
        x-html="submitted.hiring_stage === 'live' ? ICONS.checkCircle : ICONS.clock"></span>
  <h1 class="mt-lg text-h1 text-ink" x-text="submitted.hiring_stage === 'live' ? 'Your post is live' : 'Your post is under review'"></h1>
  <div class="mt-sm flex flex-wrap items-center justify-center gap-xs">
    <span class="wa-pill" :class="submitted.hiring_stage === 'live' ? 'bg-success-bg text-success' : 'bg-warning-bg text-warning'"
          x-text="submitted.hiring_stage === 'live' ? 'Live' : (submitted.hiring_stage_label || 'Under review')"></span>
  </div>
  <p class="mx-auto mt-md max-w-[440px] text-body text-ink-secondary"
     x-text="submitted.hiring_stage === 'live' ? (submitted.title || 'Your job') + ' is now visible to candidates.'
            : (docMissing(submitted) ? 'Payment received. Upload your business document — the job goes live once it is verified.'
            : 'We\'re verifying your details. You\'ll be notified as soon as it goes live.')"></p>

  <div class="mx-auto mt-lg max-w-[420px] rounded-field border-hair border-hairline p-md text-left">
    <p class="text-h5 text-ink" x-text="submitted.title"></p>
    <p class="text-caption text-ink-secondary" x-text="[submitted.organisation, submitted.city, submitted.code].filter(Boolean).join(' · ')"></p>
  </div>

  <template x-if="docMissing(submitted)">
    <div class="mx-auto mt-lg max-w-[420px] rounded-field border-hair border-warning/30 bg-warning-bg p-md text-left">
      <p class="flex items-center gap-sm text-h5 text-ink">
        <span class="text-warning">@include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-[18px] w-[18px]'])</span>
        Business document
      </p>
      <p class="mt-xs text-caption text-ink-secondary">GST certificate, shop licence or company registration. PDF, JPG or PNG · up to 10 MB.</p>
      @include('site.employer.partials.doc-upload', ['change' => 'submittedUpload($event)', 'busy' => 'docBusy', 'disabled' => 'docBusy', 'text' => "'Upload business document'", 'class' => 'wa-btn mt-md w-full', 'icon' => true])
    </div>
  </template>

  <div class="mx-auto mt-xl flex max-w-[420px] flex-col gap-sm">
    <div class="grid gap-sm sm:grid-cols-2">
      <a href="{{ url('/employer/jobs') }}" :class="docMissing(submitted) ? 'wa-btn-ghost' : 'wa-btn'" class="w-full">Go to jobs</a>
      <button type="button" @click="postAnother()" class="wa-btn-ghost w-full">Post another job</button>
    </div>
  </div>
</div>
