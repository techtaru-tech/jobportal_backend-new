{{-- "How you match" — signed in, the job's match_percent / match_reasons / missing_skills.
     Expects `job` and `kit` in Alpine scope. --}}
<template x-if="$store.auth.signedIn && job && job.match_percent != null && !job.own_posting">
  <section class="rounded-card border-hair border-hairline bg-surface p-xl">
    <div class="flex items-center justify-between gap-md">
      <h2 class="text-h4 text-ink">How you match</h2>
      <span class="wa-pill" :class="kit.matchTone(job.match_percent).cls" x-text="job.match_percent + '% match'"></span>
    </div>
    <p class="mt-xs text-bodysm text-ink-secondary" x-text="kit.matchTone(job.match_percent).label + ' for your profile'"></p>
    <div class="mt-md flex flex-wrap gap-xs" x-show="(job.match_reasons || []).length">
      <template x-for="r in job.match_reasons || []" :key="r">
        <span class="wa-tag !bg-success-bg !text-success"><span x-html="ICONS.check" class="[&>svg]:h-3 [&>svg]:w-3"></span><span x-text="r"></span></span>
      </template>
    </div>
    <template x-if="(job.missing_skills || []).length">
      <div class="mt-md rounded-field bg-warning-bg p-md">
        <p class="text-bodysm text-warning" x-text="'Skills this job asks for that your profile does not list: ' + job.missing_skills.join(', ')"></p>
        <a href="{{ url('/account') }}" class="mt-sm inline-block text-btnghost font-semibold text-primary-dark hover:underline">Add skills to your profile</a>
      </div>
    </template>
  </section>
</template>
