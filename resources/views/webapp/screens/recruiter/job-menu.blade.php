{{-- The job-card overflow menu ("⋮"), shared by My Posted Jobs and Job Management
     (recruiter/widgets/job_actions_sheet.dart).
     @include('webapp.screens.recruiter.job-menu', ['job' => 'job', 'view' => "go('/r/job/' + job.id)"]) --}}
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
  <button type="button" @click="open = !open" aria-label="Job actions"
          class="inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted hover:text-ink">
    @include('admin.partials.icon', ['name' => 'moreVertical', 'class' => 'h-5 w-5'])
  </button>
  <div x-show="open" x-cloak x-transition.opacity.duration.150ms
       class="absolute right-0 top-10 z-[45] w-[210px] overflow-hidden rounded-card border-hair border-hairline bg-surface py-xs shadow-raised">
    @php
      $item = 'flex w-full items-center gap-md px-lg py-[11px] text-left text-bodysm font-semibold text-ink hover:bg-surface-muted';
      $ico = 'text-ink-secondary [&>svg]:h-[18px] [&>svg]:w-[18px]';
    @endphp
    <button type="button" class="{{ $item }}" @click="open = false; {{ $view }}"><span x-html="ICONS.eye" class="{{ $ico }}"></span>View Job</button>
    <button type="button" class="{{ $item }}" @click="open = false; go('/r/applicants/' + encodeURIComponent({{ $job }}.id))"><span x-html="ICONS.users" class="{{ $ico }}"></span>View Applicants</button>
    <button type="button" class="{{ $item }}" @click="open = false; rShareJob({{ $job }})"><span x-html="ICONS.share" class="{{ $ico }}"></span>Share Job</button>
    <button type="button" class="{{ $item }}" @click="open = false; rEditJob({{ $job }})"><span x-html="ICONS.pencil" class="{{ $ico }}"></span>Edit Job</button>
    <button type="button" class="{{ $item }}" @click="open = false; rToggleJob({{ $job }})">
      <span x-html="{{ $job }}.posting_status === 'active' ? ICONS.pause : ICONS.play" class="{{ $ico }}"></span>
      <span x-text="{{ $job }}.posting_status === 'active' ? 'Pause Job' : 'Resume Job'"></span>
    </button>
    <button type="button" class="{{ $item }} text-danger" @click="open = false; rCloseJob({{ $job }})"><span x-html="ICONS.xCircle" class="text-danger [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>Close Job</button>
  </div>
</div>
