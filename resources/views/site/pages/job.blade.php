@extends('site.layout')

@php
    $salary = App\Support\Display::salary($job->salary_min, $job->salary_max);
    $summary = Str::limit(strip_tags((string) $job->about) ?: "{$job->title} at {$job->organisation} in {$job->city}.", 155);
@endphp

@section('title', "{$job->title} at {$job->organisation}, {$job->city} — Inthes")
@section('description', $summary)
@section('og_type', 'article')
@section('og_title', "{$job->title} — {$job->organisation}")
@section('og_description', $summary)
@section('canonical', route('site.job', $job->code))

@push('head')
{{--
  Google for Jobs structured data.

  The single highest-value thing on this page for a job board: it is what puts
  a posting into the jobs carousel rather than leaving it as one blue link.
  Every field here is required or strongly recommended by Google's spec, so
  they are emitted from real columns and omitted entirely when empty rather
  than filled with placeholders — a wrong `baseSalary` is worse than none.

  `validThrough` comes from `expires_at`, which only an admin sets. Absent
  means "no stated end date", which is a legitimate answer to the spec.
--}}
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'JobPosting',
    'title' => $job->title,
    'description' => $job->about ?: $job->title.' at '.$job->organisation,
    'identifier' => [
        '@type' => 'PropertyValue',
        'name' => 'Inthes',
        'value' => $job->code,
    ],
    'datePosted' => $job->posted_at->toIso8601String(),
    'validThrough' => $job->expires_at?->toIso8601String(),
    'employmentType' => $job->type ? strtoupper(str_replace(' ', '_', $job->type)) : null,
    'hiringOrganization' => [
        '@type' => 'Organization',
        'name' => $job->organisation,
    ],
    'jobLocation' => [
        '@type' => 'Place',
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'addressLocality' => $job->city,
            'postalCode' => $job->pincode ?: null,
            'addressCountry' => 'IN',
        ]),
    ],
    'baseSalary' => $job->salary_min ? [
        '@type' => 'MonetaryAmount',
        'currency' => 'INR',
        'value' => array_filter([
            '@type' => 'QuantitativeValue',
            'minValue' => $job->salary_min,
            'maxValue' => $job->salary_max,
            'unitText' => 'MONTH',
        ]),
    ] : null,
], fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<div class="mx-auto max-w-[1200px] px-page py-xl lg:px-xl">

    <nav aria-label="Breadcrumb" class="mb-lg flex flex-wrap items-center gap-xs text-caption text-ink-muted">
        <a href="{{ route('site.home') }}" class="transition-colors hover:text-ink">Home</a>
        @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-3 w-3'])
        <a href="{{ route('site.jobs') }}" class="transition-colors hover:text-ink">Jobs</a>
        @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-3 w-3'])
        <span class="truncate text-ink-secondary">{{ $job->title }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-md lg:grid-cols-[1fr,340px]">

        {{-- ── the posting ──────────────────────────────────────────────── --}}
        <div class="space-y-md">
            <article class="rounded-card border-hair border-hairline bg-surface p-xl shadow-card">
                <div class="flex items-start gap-lg">
                    <span aria-hidden="true"
                          class="flex h-14 w-14 shrink-0 items-center justify-center rounded-logo bg-primary-light text-h3 font-semibold text-primary-dark">
                        {{ App\Support\Display::initials($job->organisation) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-h1 text-ink">{{ $job->title }}</h1>
                        <p class="mt-xs flex flex-wrap items-center gap-xs text-body text-ink-secondary">
                            <span>{{ $job->organisation }}</span>
                            @if ($job->organisationRecord?->verified)
                                <span class="inline-flex items-center gap-xs rounded-field bg-primary-light px-md py-[4px] text-tag font-semibold text-primary-dark">
                                    @include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-[13px] w-[13px]'])
                                    Verified
                                </span>
                            @endif
                        </p>
                        <p class="mt-xs text-caption text-ink-muted">
                            {{ $job->code }} · Posted {{ $job->posted_at->diffForHumans() }}
                        </p>
                    </div>
                </div>

                <dl class="mt-xl grid grid-cols-2 gap-lg sm:grid-cols-4">
                    @foreach ([
                        ['label' => 'Location', 'value' => $job->city],
                        ['label' => 'Salary', 'value' => $salary],
                        ['label' => 'Experience', 'value' => $job->experience],
                        ['label' => 'Shift', 'value' => $job->shift],
                    ] as $fact)
                        <div class="min-w-0">
                            <dt class="text-kicker text-ink-secondary">{{ strtoupper($fact['label']) }}</dt>
                            <dd class="mt-[3px] break-words text-bodysm font-semibold text-ink">
                                {{ $fact['value'] ?: '—' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            @if ($job->about)
                <section class="rounded-card border-hair border-hairline bg-surface p-xl shadow-card">
                    <h2 class="text-h3 text-ink">About this role</h2>
                    <p class="mt-md whitespace-pre-line text-body text-ink-secondary">{{ $job->about }}</p>
                </section>
            @endif

            @foreach ([
                ['title' => 'What you will do', 'items' => $job->duties ?? []],
                ['title' => 'What we are looking for', 'items' => $job->qualifications ?? []],
                ['title' => 'Skills', 'items' => $job->skills ?? []],
                ['title' => 'Benefits', 'items' => $job->benefits ?? []],
            ] as $block)
                @if (!empty($block['items']))
                    <section class="rounded-card border-hair border-hairline bg-surface p-xl shadow-card">
                        <h2 class="text-h3 text-ink">{{ $block['title'] }}</h2>
                        <ul class="mt-md space-y-sm">
                            @foreach ($block['items'] as $item)
                                <li class="flex items-start gap-md text-bodysm text-ink-secondary">
                                    <span class="mt-[6px] h-[6px] w-[6px] shrink-0 rounded-full bg-primary"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach

            @if ($job->organisation_note)
                <section class="rounded-card border-hair border-hairline bg-surface p-xl shadow-card">
                    <h2 class="text-h3 text-ink">About {{ $job->organisation }}</h2>
                    <p class="mt-md whitespace-pre-line text-body text-ink-secondary">{{ $job->organisation_note }}</p>
                </section>
            @endif
        </div>

        {{-- ── apply rail ───────────────────────────────────────────────── --}}
        <aside class="space-y-md lg:sticky lg:top-[88px] lg:self-start">
            <div class="rounded-card border-hair border-primary-line bg-primary-light p-xl">
                <span class="block text-kicker text-primary-dark">APPLY</span>
                <h2 class="mt-xs text-h3 text-ink">Apply from the app</h2>
                <p class="mt-sm text-bodysm text-ink-secondary">
                    Your profile and resume live in the app, so applying takes one tap — and you
                    can track the employer's reply there too.
                </p>

                <button type="button" @click="openApply(@js($job->title))"
                        class="mt-lg flex h-[50px] w-full items-center justify-center gap-sm rounded-button bg-primary text-btn font-semibold text-ink-onPrimary shadow-button
                               transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
                    Apply for this job
                </button>

                {{-- The QR is on the page as well as in the dialog: a visitor who
                     has already decided should not need a second click to reach
                     the thing that gets them there. --}}
                <div class="mt-lg flex items-center gap-lg">
                    <div class="w-[92px] shrink-0 rounded-field border-hair border-hairline bg-canvas p-sm [&>svg]:h-full [&>svg]:w-full">
                        {!! App\Support\StoreQr::svg() !!}
                    </div>
                    <p class="text-caption text-ink-secondary">
                        Scan with your phone camera to install Inthes from Google Play.
                    </p>
                </div>
            </div>

            <div class="rounded-card border-hair border-hairline bg-surface p-xl shadow-card">
                <h2 class="text-h4 text-ink">Share this job</h2>
                <p class="mt-xs text-caption text-ink-muted">Anyone with the link can read the posting.</p>
                <div class="mt-md flex flex-wrap gap-sm">
                    <a href="https://wa.me/?text={{ urlencode($job->title.' at '.$job->organisation.' — '.route('site.job', $job->code)) }}"
                       target="_blank" rel="noopener"
                       class="inline-flex h-10 items-center justify-center rounded-button border-btn border-hairline bg-surface px-lg text-btnghost font-semibold text-ink
                              transition-colors hover:border-hairline-strong hover:bg-surface-muted">
                        WhatsApp
                    </a>
                    {{-- `x-ref`-free: reads its own href, so it keeps working if
                         the URL is ever rendered differently. --}}
                    <button type="button"
                            @click="navigator.clipboard?.writeText(@js(route('site.job', $job->code))); $el.textContent = 'Link copied'"
                            class="inline-flex h-10 items-center justify-center rounded-button border-btn border-hairline bg-surface px-lg text-btnghost font-semibold text-ink
                                   transition-colors hover:border-hairline-strong hover:bg-surface-muted">
                        Copy link
                    </button>
                </div>
            </div>
        </aside>
    </div>

    {{-- ── similar ──────────────────────────────────────────────────────── --}}
    @if ($similar->isNotEmpty())
        <section class="mt-xxl">
            @include('site.partials.section-heading', [
                'kicker' => 'You might also like',
                'title' => 'Similar openings',
                'action' => ['label' => 'See all jobs', 'href' => route('site.jobs')],
            ])
            <div class="stagger grid grid-cols-1 gap-md md:grid-cols-2 lg:grid-cols-4">
                @foreach ($similar as $other)
                    @include('site.partials.job-card', ['job' => $other])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
