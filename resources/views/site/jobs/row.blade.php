{{--
  One job as a compact row — the browse list, saved jobs, the home strip.
  Expects `job` in Alpine scope and `kit` (= Inthes.jobsKit) on the component.

    @include('site.jobs.row')                                     — a link to /jobs/{code}
    @include('site.jobs.row', ['select' => 'pick(job, $event)',   — the split view: the
                               'active' => 'selectedId === job.id'])  handler decides
  The whole row is the link; Save sits above it so it never navigates.
--}}
<article class="group relative rounded-card border-hair bg-surface p-lg transition-[border-color,box-shadow] duration-micro hover:shadow-raised {{ isset($active) ? '' : 'border-hairline' }}"
         @isset($active) :class="({{ $active }}) ? 'border-primary shadow-glow' : 'border-hairline'" @endisset>
  <a :href="kit.jobUrl(job)" @isset($select) @click="{{ $select }}" @endisset class="absolute inset-0 rounded-card" :aria-label="job.title"></a>

  <div class="flex items-start gap-md">
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[13px] font-bold text-primary-dark" x-text="initials(job.organisation)"></span>
    <div class="min-w-0 flex-1">
      <div class="flex items-start gap-sm">
        <h3 class="line-clamp-2 flex-1 text-h5 text-ink group-hover:text-primary-dark" x-text="job.title"></h3>
        <button type="button" x-show="!job.own_posting" @click.prevent.stop="$store.jobs.toggleSave(job)" :title="$store.jobs.isSaved(job) ? 'Saved — click to remove' : 'Save job'"
                :aria-label="$store.jobs.isSaved(job) ? 'Remove from saved jobs' : 'Save job'"
                :class="$store.jobs.isSaved(job) ? 'text-primary' : 'text-ink-muted hover:text-ink'"
                class="relative z-10 -mr-xs -mt-xs inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-field hover:bg-surface-muted">
          <span x-html="ICONS.bookmark" :class="$store.jobs.isSaved(job) && '[&>svg]:fill-current'" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        </button>
      </div>
      <p class="mt-[2px] flex min-w-0 items-center gap-xs text-bodysm text-ink-secondary">
        <span class="truncate" x-text="job.organisation"></span>
        <span x-show="job.organisation_verified" x-html="ICONS.badgeCheck" title="Verified employer" class="shrink-0 text-info [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
        <span class="shrink-0 text-ink-muted">·</span>
        <span class="shrink-0" x-text="job.city"></span>
      </p>
      <div class="mt-sm flex flex-wrap gap-xs">
        <span x-show="kit.salary(job)" class="wa-tag !bg-primary-light font-semibold !text-primary-dark" x-text="kit.salary(job)"></span>
        <span x-show="kit.experience(job)" class="wa-tag" x-text="kit.experience(job)"></span>
        <span x-show="job.type || job.shift" class="wa-tag" x-text="[job.type, job.shift].filter(Boolean).join(' · ')"></span>
      </div>
      <div class="mt-sm flex items-center gap-sm">
        <template x-if="$store.auth.signedIn && job.match_percent != null && !job.own_posting">
          <span class="wa-pill" :class="kit.matchTone(job.match_percent).cls" x-text="job.match_percent + '% match'"></span>
        </template>
        <template x-if="!($store.auth.signedIn && job.match_percent != null) && kit.isNew(job)">
          <span class="wa-pill bg-primary-light text-primary-dark">New</span>
        </template>
        <span x-show="$store.auth.signedIn && job.own_posting" class="wa-pill bg-info-bg text-info">Your posting</span>
        <span x-show="$store.auth.signedIn && !job.own_posting && $store.jobs.hasApplied(job)" class="wa-pill bg-success-bg text-success">Applied ✓</span>
        <span class="ml-auto text-caption text-ink-muted" x-text="timeAgo(job.posted_at)"></span>
      </div>
    </div>
  </div>
</article>
