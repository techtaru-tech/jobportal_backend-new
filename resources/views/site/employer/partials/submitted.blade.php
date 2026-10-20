{{-- "Your post is under review" / "Your post is live" (post_job submitted view),
     after paying or posting on a plan credit. Binds to `submitted` (the job);
     the component has `postAnother()`. --}}
<div class="wa-card mx-auto max-w-[620px] px-xl py-xxl text-center">
  <span class="mx-auto flex h-[76px] w-[76px] items-center justify-center rounded-full [&>svg]:h-10 [&>svg]:w-10"
        :class="submitted.hiring_stage === 'live' ? 'bg-success-bg text-success' : 'bg-warning-bg text-warning'"
        x-html="submitted.hiring_stage === 'live' ? ICONS.checkCircle : ICONS.clock"></span>
  <h1 class="mt-lg text-h1 text-ink" x-text="submitted.hiring_stage === 'live' ? 'Your post is live' : 'Your post is under review'"></h1>
  <p class="mx-auto mt-sm max-w-[440px] text-body text-ink-secondary"
     x-text="submitted.hiring_stage === 'live' ? (submitted.title || 'Your job') + ' is now visible to candidates.'
            : (docMissing(submitted) ? 'Upload your company document so we can verify your company and make the post live.'
            : 'We\'re verifying your details. You\'ll be notified as soon as it goes live.')"></p>
  <span x-show="submitted.hiring_stage_label" class="mt-md inline-flex rounded-chip bg-surface-muted px-md py-[6px] text-caption text-ink-secondary" x-text="submitted.hiring_stage_label"></span>

  <div class="mx-auto mt-lg max-w-[420px] rounded-field border-hair border-hairline p-md text-left">
    <p class="text-h5 text-ink" x-text="submitted.title"></p>
    <p class="text-caption text-ink-secondary" x-text="[submitted.organisation, submitted.city, submitted.code].filter(Boolean).join(' · ')"></p>
  </div>

  <div class="mx-auto mt-xl flex max-w-[420px] flex-col gap-sm">
    <template x-if="docMissing(submitted)">
      <a :href="noticeHref(submitted, 'doc')" class="wa-btn w-full">
        @include('admin.partials.icon', ['name' => 'upload', 'class' => 'h-5 w-5'])
        Upload company document
      </a>
    </template>
    <div class="grid gap-sm sm:grid-cols-2">
      <a href="{{ url('/employer/jobs') }}" :class="docMissing(submitted) ? 'wa-btn-ghost' : 'wa-btn'" class="w-full">Go to jobs</a>
      <button type="button" @click="postAnother()" class="wa-btn-ghost w-full">Post another job</button>
    </div>
  </div>
</div>
