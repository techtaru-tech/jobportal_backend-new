{{-- "Preview" and "View Candidates" on a posting's row — direct buttons, as
     on the app's My Jobs card ("View Job" / "Applicants"), rather than items
     hidden in the ⋮ menu. Edit, Share, Pause / Resume and Close stay there.
     @include('site.employer.partials.job-buttons', ['job' => 'job', 'full' => false]) --}}
<div class="flex items-center gap-xs {{ !empty($full) ? 'w-full [&>a]:flex-1' : '' }}" @click.stop>
  <a :href="previewUrl({{ $job }})" class="inline-flex h-9 items-center justify-center gap-xs whitespace-nowrap rounded-button border-btn border-primary bg-surface px-md text-caption font-semibold text-primary transition-colors hover:bg-primary-light">
    @include('admin.partials.icon', ['name' => 'eye', 'class' => 'h-[15px] w-[15px]']) Preview
  </a>
  <a :href="jobUrl({{ $job }})" class="inline-flex h-9 items-center justify-center gap-xs whitespace-nowrap rounded-button bg-primary px-md text-caption font-semibold text-ink-onPrimary transition-colors hover:bg-primary-dark">
    @include('admin.partials.icon', ['name' => 'users', 'class' => 'h-[15px] w-[15px]']) View Candidates
  </a>
</div>
