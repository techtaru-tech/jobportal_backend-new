{{--
  One posting, as it appears in any list on the site.

  The whole card is a link to the detail page, and the Apply button inside it
  opens the dialog instead — so `@click.prevent.stop` on that button, or tapping
  Apply would navigate and open the dialog at once.

  `verified` is shown rather than assumed: `publiclyVisible()` already means the
  employer passed review, but saying so is the reassurance a candidate is
  looking for on a job board they have never used before.
--}}
@php
    /** @var \App\Models\JobPosting $job */
    $salary = App\Support\Display::salary($job->salary_min, $job->salary_max);
@endphp

<article class="group relative flex h-full flex-col rounded-card border-hair border-hairline bg-surface p-lg shadow-card
                transition-[box-shadow,border-color,transform] duration-micro ease-out
                hover:-translate-y-[2px] hover:border-hairline-strong hover:shadow-raised">

    <a href="{{ route('site.job', $job->code) }}" class="absolute inset-0 rounded-card" aria-label="{{ $job->title }}"></a>

    <div class="flex items-start gap-md">
        <span aria-hidden="true"
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-logo bg-primary-light text-h5 font-semibold text-primary-dark">
            {{ App\Support\Display::initials($job->organisation) }}
        </span>
        <div class="min-w-0 flex-1">
            <h3 class="truncate text-h4 text-ink">{{ $job->title }}</h3>
            <p class="mt-[2px] flex items-center gap-xs truncate text-bodysm text-ink-secondary">
                <span class="truncate">{{ $job->organisation }}</span>
                @if ($job->organisationRecord?->verified)
                    <span class="shrink-0 text-primary" title="Verified employer">
                        @include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-[14px] w-[14px]'])
                    </span>
                @endif
            </p>
        </div>
    </div>

    <div class="mt-md flex flex-wrap gap-xs">
        <span class="inline-flex items-center rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary">{{ $job->city }}</span>
        @if ($job->type)
            <span class="inline-flex items-center rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary">{{ $job->type }}</span>
        @endif
        @if ($job->shift)
            <span class="inline-flex items-center rounded-field bg-surface-muted px-md py-[5px] text-tag text-ink-secondary">{{ $job->shift }}</span>
        @endif
        @if ($salary)
            {{-- The accent tag is rationed: salary is the one piece of metadata
                 a candidate scans for, so it gets the brand tint and the others
                 stay neutral. --}}
            <span class="inline-flex items-center rounded-field bg-primary-light px-md py-[5px] text-tag font-semibold text-primary-dark">{{ $salary }}</span>
        @endif
    </div>

    @if ($job->experience)
        <p class="mt-md text-bodysm text-ink-secondary">Experience: {{ $job->experience }}</p>
    @endif

    <div class="mt-auto flex items-center justify-between gap-md pt-lg">
        <span class="text-caption text-ink-muted">{{ $job->posted_at->diffForHumans() }}</span>

        <button type="button" @click.prevent.stop="openApply(@js($job->title))"
                class="relative z-10 inline-flex h-9 items-center justify-center rounded-button border-btn border-primary bg-surface px-lg text-btnghost font-semibold text-primary
                       transition-colors duration-micro hover:bg-primary-light">
            Apply
        </button>
    </div>
</article>
