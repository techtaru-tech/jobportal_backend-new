{{--
  Who applied to one posting.

  Read-only here, and that is a decision rather than an omission. Moving an
  applicant notifies the candidate and writes the timeline their Track screen
  reads — a call that cannot be taken back and that wants the full profile,
  resume and intro video in front of you. That screen is in the app, so the web
  shows who applied and sends the recruiter there to decide.
--}}
<template x-if="view === 'applicants'">
  <div class="animate-enter-up">
    <button @click="go('jobs')"
            class="mb-lg inline-flex items-center gap-xs -ml-sm rounded-button px-sm py-xs text-btnghost text-ink-secondary
                   transition-colors duration-micro hover:bg-surface-muted hover:text-ink">
      @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-4 w-4'])
      <span>Back to my jobs</span>
    </button>

    <div class="mb-lg">
      <span class="block text-kicker text-ink-secondary">APPLICANTS</span>
      <h1 class="mt-[2px] text-h1 text-ink" x-text="applicants.job?.title || 'Applicants'"></h1>
      <p class="mt-xs text-bodysm text-ink-secondary">
        <span x-text="applicants.job?.organisation"></span> · <span x-text="applicants.job?.city"></span>
      </p>
    </div>

    <div class="mb-md flex items-start gap-md rounded-card border-hair border-primary-line bg-primary-light p-lg">
      <span x-html="ICONS.badgeCheck" class="[&>svg]:h-5 [&>svg]:w-5 mt-[1px] shrink-0 text-primary"></span>
      <div class="min-w-0">
        <p class="text-bodysm font-semibold text-primary-dark">Decisions happen in the app</p>
        <p class="mt-[2px] text-caption text-ink-secondary" x-text="applicantNote()"></p>
      </div>
    </div>

    <template x-if="applicants.busy && !applicants.data.length">
      <div class="flex flex-col items-center justify-center gap-md p-xxxl">
        @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-6 w-6 animate-spin text-primary'])
        <span class="text-bodysm text-ink-secondary">Loading applicants…</span>
      </div>
    </template>

    <template x-if="!applicants.busy && !applicants.data.length">
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
        <div class="flex flex-col items-center justify-center p-xxxl text-center">
          <div class="red-wash flex h-[76px] w-[76px] items-center justify-center rounded-card border-hair border-hairline text-ink-muted">
            @include('admin.partials.icon', ['name' => 'inbox', 'class' => 'h-[28px] w-[28px]'])
          </div>
          <h2 class="mt-lg text-h4 text-ink">Nobody has applied yet</h2>
          <p class="mt-xs max-w-sm text-bodysm text-ink-secondary">
            Share the posting to reach more candidates — it is live on the site as soon as it is approved.
          </p>
        </div>
      </div>
    </template>

    <template x-if="applicants.data.length">
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card">
        <ul>
          <template x-for="row in applicants.data" :key="row.id">
            <li class="flex flex-wrap items-center gap-md border-b border-hairline-divider px-lg py-md last:border-0">
              <span aria-hidden="true" x-text="initials(row.name)" style="font-size:11.6px"
                    class="inline-flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>

              <div class="min-w-0 flex-1">
                <p class="truncate text-bodysm font-semibold text-ink" x-text="row.name || 'Unnamed candidate'"></p>
                <p class="truncate text-caption text-ink-muted">
                  <span x-text="row.qualification || '—'"></span>
                  <template x-if="row.experience"><span> · <span x-text="row.experience"></span></span></template>
                  <span> · applied <span x-text="timeAgo(row.applied_at)"></span></span>
                </p>
              </div>

              {{-- The figure the recruiter sorts on, frozen at submit time. --}}
              <div class="shrink-0 text-right">
                <span class="block text-bodysm font-semibold tabular-nums text-ink"
                      x-text="(row.profile_strength ?? 0) + '%'"></span>
                <span class="block text-caption text-ink-muted">profile</span>
              </div>

              <span class="inline-flex shrink-0 items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                    :class="applicationTone(row.status)"
                    x-text="applicationLabel(row.status)"></span>
            </li>
          </template>
        </ul>
      </div>
    </template>
  </div>
</template>
