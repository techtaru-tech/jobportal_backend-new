{{-- The selected job, in the browse page's right-hand panel. Expects `job` and
     `kit` in Alpine scope (the API's job shape). --}}
<div class="space-y-md">
  <section class="rounded-card border-hair border-hairline bg-surface p-xl">
    <div class="flex items-start gap-lg">
      <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[16px] font-bold text-primary-dark" x-text="initials(job.organisation)"></span>
      <div class="min-w-0 flex-1">
        <h2 class="text-h2 text-ink" x-text="job.title"></h2>
        <p class="mt-xs flex flex-wrap items-center gap-xs text-body text-ink-secondary">
          <span x-text="job.organisation"></span>
          <span x-show="job.organisation_verified" class="inline-flex items-center gap-xs rounded-field bg-info-bg px-sm py-[2px] text-tag font-semibold text-info">
            <span x-html="ICONS.badgeCheck" class="[&>svg]:h-[13px] [&>svg]:w-[13px]"></span>Verified
          </span>
        </p>
        <p class="mt-xs flex items-center gap-xs text-bodysm text-ink-secondary">
          <span x-html="ICONS.mapPin" class="[&>svg]:h-4 [&>svg]:w-4"></span>
          <span x-text="[job.locality, job.city, job.state].filter(Boolean).join(', ')"></span>
        </p>
        <p class="mt-xs text-caption text-ink-muted" x-text="job.code + ' · Posted ' + timeAgo(job.posted_at)"></p>
      </div>
    </div>
    <div class="mt-lg">@include('site.jobs.apply-actions')</div>

    <template x-if="kit.canCall(job) || kit.canWhatsapp(job)">
      <div class="mt-md flex flex-wrap gap-sm border-t border-hairline-divider pt-md">
        <a x-show="kit.canCall(job)" :href="'tel:+91' + job.contact_phone" class="wa-btn-ghost-sm"><span x-html="ICONS.phone" class="[&>svg]:h-4 [&>svg]:w-4"></span>Call HR</a>
        <a x-show="kit.canWhatsapp(job)" :href="kit.whatsappLink(job)" target="_blank" rel="noopener" class="wa-btn-ghost-sm !border-success !text-success hover:!bg-success-bg"><span x-html="ICONS.whatsapp" class="[&>svg]:h-4 [&>svg]:w-4"></span>WhatsApp</a>
      </div>
    </template>
  </section>

  @include('site.jobs.match')

  <template x-if="job.charges_fee">
    <div class="flex items-start gap-md rounded-card border-hair border-warning/30 bg-warning-bg p-lg text-warning">
      <span x-html="ICONS.alert" class="mt-[1px] [&>svg]:h-5 [&>svg]:w-5"></span>
      <p class="text-bodysm font-medium">This employer charges candidates a fee for this job.</p>
    </div>
  </template>

  <section class="grid grid-cols-2 gap-lg rounded-card border-hair border-hairline bg-surface p-xl sm:grid-cols-4">
    <template x-for="row in [
      ['Salary', kit.salary(job)], ['Experience', kit.experience(job)], ['Job type', job.type], ['Shift', job.shift],
      ['Openings', job.openings], ['Education', job.min_education], ['Gender', job.preferred_gender], ['Locality', job.locality],
    ].filter(r => r[1])" :key="row[0]">
      <div class="min-w-0">
        <p class="text-kicker uppercase text-ink-muted" x-text="row[0]"></p>
        <p class="mt-[3px] break-words text-h5 text-ink" x-text="row[1]"></p>
      </div>
    </template>
  </section>

  <template x-for="sec in [
    ['Skills required', job.skills, 'chips'], ['About the job', job.about, 'text'], ['Responsibilities', job.duties, 'list'],
    ['Qualifications', job.qualifications, 'chips'], ['Benefits', job.benefits, 'chips'], ['About ' + job.organisation, job.organisation_note, 'text'],
  ].filter(s => Array.isArray(s[1]) ? s[1].length : s[1])" :key="sec[0]">
    <section class="rounded-card border-hair border-hairline bg-surface p-xl">
      <h3 class="text-h4 text-ink" x-text="sec[0]"></h3>
      <template x-if="sec[2] === 'text'"><p class="mt-sm whitespace-pre-line text-body text-ink-secondary" x-text="sec[1]"></p></template>
      <template x-if="sec[2] === 'chips'">
        <div class="mt-sm flex flex-wrap gap-xs"><template x-for="c in sec[1]" :key="c"><span class="wa-tag !text-ink" x-text="c"></span></template></div>
      </template>
      <template x-if="sec[2] === 'list'">
        <ul class="mt-sm space-y-sm"><template x-for="d in sec[1]" :key="d"><li class="flex items-start gap-md text-bodysm text-ink-secondary"><span class="mt-[6px] h-[6px] w-[6px] shrink-0 rounded-full bg-primary"></span><span x-text="d"></span></li></template></ul>
      </template>
    </section>
  </template>

  <a :href="kit.jobUrl(job)" class="group flex items-center justify-center gap-xs rounded-card border-hair border-dashed border-hairline-strong bg-surface p-lg text-btnghost font-semibold text-primary-dark hover:bg-primary-light">
    Open full page <span x-html="ICONS.externalLink" class="nudge [&>svg]:h-4 [&>svg]:w-4"></span>
  </a>
</div>
