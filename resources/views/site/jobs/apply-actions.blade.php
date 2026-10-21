{{-- The Apply block: Apply / Applied ✓ + Track / View applicants, Save, Share, and
     the readiness line. Expects `job` and `kit` in Alpine scope.
     'full' => true stretches the buttons to the container (the side rail). --}}
@php $full = !empty($full); @endphp
<div>
  <div class="flex flex-wrap items-center gap-sm">
    <template x-if="$store.auth.signedIn && job.own_posting">
      <a :href="'/employer/jobs/' + encodeURIComponent(job.id)" class="wa-btn {{ $full ? 'w-full' : 'min-w-[180px] flex-1 sm:flex-none' }}">View applicants</a>
    </template>
    <template x-if="!($store.auth.signedIn && job.own_posting) && $store.auth.signedIn && $store.jobs.hasApplied(job)">
      <div class="flex {{ $full ? 'w-full flex-col' : 'flex-1 flex-wrap sm:flex-none' }} gap-sm">
        <span class="inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-success-bg px-xl text-btn font-semibold text-success {{ $full ? 'w-full' : '' }}">
          <span x-html="ICONS.checkCircle" class="[&>svg]:h-5 [&>svg]:w-5"></span>
          <span x-text="$store.jobs.appFor(job) ? 'Applied on ' + fmtDate($store.jobs.appFor(job).applied_at) : 'Applied'"></span>
        </span>
        <a x-show="$store.jobs.appFor(job)" :href="'/account/applications/' + encodeURIComponent($store.jobs.appFor(job)?.id || '')"
           class="wa-btn-ghost {{ $full ? 'w-full' : '' }}">Track application</a>
      </div>
    </template>
    <template x-if="!($store.auth.signedIn && (job.own_posting || $store.jobs.hasApplied(job)))">
      <button type="button" @click="Inthes.quickApply(job)" class="wa-btn {{ $full ? 'w-full' : 'min-w-[180px] flex-1 sm:flex-none' }}">Apply now</button>
    </template>

    @if (!$full)
      <button type="button" x-show="!job.own_posting" @click="$store.jobs.toggleSave(job)" :aria-label="$store.jobs.isSaved(job) ? 'Remove from saved jobs' : 'Save job'"
              :class="$store.jobs.isSaved(job) ? '!border-primary !text-primary' : ''"
              class="inline-flex h-[50px] items-center justify-center gap-sm rounded-button border-btn border-hairline-strong bg-surface px-lg text-btnghost font-semibold text-ink transition-colors hover:bg-surface-muted">
        <span x-html="ICONS.bookmark" :class="$store.jobs.isSaved(job) && '[&>svg]:fill-current'" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        <span x-text="$store.jobs.isSaved(job) ? 'Saved' : 'Save'"></span>
      </button>
      <button type="button" @click="kit.share(job)" aria-label="Share"
              class="inline-flex h-[50px] w-[50px] items-center justify-center rounded-button border-btn border-hairline-strong bg-surface text-ink transition-colors hover:bg-surface-muted">
        <span x-html="ICONS.share" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      </button>
    @else
      <div class="grid w-full grid-cols-2 gap-sm">
        <button type="button" x-show="!job.own_posting" @click="$store.jobs.toggleSave(job)"
                :class="$store.jobs.isSaved(job) ? '!border-primary !text-primary' : ''"
                class="inline-flex h-10 items-center justify-center gap-sm rounded-button border-btn border-hairline-strong bg-surface text-btnghost font-semibold text-ink transition-colors hover:bg-surface-muted">
          <span x-html="ICONS.bookmark" :class="$store.jobs.isSaved(job) && '[&>svg]:fill-current'" class="[&>svg]:h-4 [&>svg]:w-4"></span>
          <span x-text="$store.jobs.isSaved(job) ? 'Saved' : 'Save job'"></span>
        </button>
        <button type="button" @click="kit.share(job)"
                class="inline-flex h-10 items-center justify-center gap-sm rounded-button border-btn border-hairline-strong bg-surface text-btnghost font-semibold text-ink transition-colors hover:bg-surface-muted">
          <span x-html="ICONS.share" class="[&>svg]:h-4 [&>svg]:w-4"></span>Share
        </button>
      </div>
    @endif
  </div>
  <p x-show="!($store.auth.signedIn && $store.jobs.hasApplied(job))" class="mt-sm flex items-center gap-xs text-caption text-ink-secondary">
    <span x-html="ICONS.info" class="shrink-0 text-ink-muted [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
    <span x-text="kit.readiness(job)"></span>
  </p>
</div>
