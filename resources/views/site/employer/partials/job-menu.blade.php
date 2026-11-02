{{-- A posting's "⋮" actions (recruiter/widgets/job_actions_sheet.dart), shown only
     where they apply: Edit (sends a live post back for approval), Share,
     Pause / Resume and Close — plus Choose plan / Upload document when the
     posting is waiting on one. "View Candidates" and "Preview" are buttons
     on the row itself (site/employer/partials/job-buttons), not in here.
     Fixed-positioned so a scrolling table can't clip it.
     The page component spreads Emp.jobActions() and Emp.helpers in.
     @include('site.employer.partials.job-menu', ['job' => 'job']) --}}
@php
  $item = 'flex w-full items-center gap-md px-lg py-[10px] text-left text-bodysm font-medium text-ink hover:bg-surface-muted';
  $ico = 'text-ink-secondary [&>svg]:h-[17px] [&>svg]:w-[17px]';
@endphp
<div x-data="{ open: false, top: 0, left: 0,
               toggle() {
                 if (this.open) { this.open = false; return }
                 const r = this.$refs.btn.getBoundingClientRect()
                 this.left = Math.max(8, Math.min(r.right - 228, innerWidth - 236)); this.top = r.bottom + 4; this.open = true
                 this.$nextTick(() => { const h = this.$refs.menu.offsetHeight; if (r.bottom + 4 + h > innerHeight - 8) this.top = Math.max(8, r.top - h - 4) })
               } }"
     class="relative" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false" @click.stop>
  <button type="button" x-ref="btn" @click="toggle()" aria-label="Job actions" :aria-expanded="open"
          class="inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted hover:text-ink">
    @include('admin.partials.icon', ['name' => 'moreVertical', 'class' => 'h-5 w-5'])
  </button>
  <template x-teleport="body">
    <div x-show="open" x-cloak x-ref="menu" @click.outside="open = false" :style="`top:${top}px;left:${left}px`"
         class="fixed z-[60] w-[228px] overflow-hidden rounded-card border-hair border-hairline bg-surface py-xs shadow-raised animate-pop-in">
      <template x-if="awaitingPayment({{ $job }})">
        <a :href="noticeHref({{ $job }}, 'plan')" class="{{ $item }} text-primary-dark"><span x-html="ICONS.wallet" class="text-primary [&>svg]:h-[17px] [&>svg]:w-[17px]"></span>Choose plan</a>
      </template>
      <template x-if="docMissing({{ $job }})">
        <label class="{{ $item }} cursor-pointer text-primary-dark" @click.stop>
          <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" :disabled="!!jobBusy" @change="open = false; jobUploadDoc({{ $job }}, $event)">
          <span x-html="jobBusy === {{ $job }}.id ? ICONS.loader : ICONS.upload" :class="jobBusy === {{ $job }}.id && 'animate-spin'" class="text-primary [&>svg]:h-[17px] [&>svg]:w-[17px]"></span>
          <span x-text="jobBusy === {{ $job }}.id ? 'Uploading…' : 'Upload document'"></span>
        </label>
      </template>
      <template x-if="{{ $job }}.posting_status === 'active' && {{ $job }}.organisation_verified">
        <a :href="publicUrl({{ $job }})" target="_blank" rel="noopener" class="{{ $item }}"><span x-html="ICONS.externalLink" class="{{ $ico }}"></span>View public page</a>
      </template>
      <template x-if="canEdit({{ $job }})">
        <a :href="jobUrl({{ $job }}) + '/edit'" class="{{ $item }}"><span x-html="ICONS.pencil" class="{{ $ico }}"></span>Edit job</a>
      </template>
      <template x-if="{{ $job }}.posting_status === 'active'">
        <button type="button" class="{{ $item }}" @click="open = false; jobShare({{ $job }})"><span x-html="ICONS.share" class="{{ $ico }}"></span>Share job</button>
      </template>
      <template x-if="canToggle({{ $job }})">
        <button type="button" class="{{ $item }}" @click="open = false; jobToggle({{ $job }})">
          <span x-html="{{ $job }}.posting_status === 'active' ? ICONS.pause : ICONS.play" class="{{ $ico }}"></span>
          <span x-text="{{ $job }}.posting_status === 'active' ? 'Pause job' : 'Resume job'"></span>
        </button>
      </template>
      <template x-if="canClose({{ $job }})">
        <button type="button" class="{{ $item }} border-t border-hairline-divider text-danger" @click="open = false; jobClose({{ $job }})"><span x-html="ICONS.xCircle" class="text-danger [&>svg]:h-[17px] [&>svg]:w-[17px]"></span>Close job</button>
      </template>
    </div>
  </template>
</div>
