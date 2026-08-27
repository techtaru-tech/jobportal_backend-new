@extends('site.layout')

@section('title', 'Inthes — Nursing, lab & pharmacy jobs across India')
@section('description', 'Browse verified healthcare employers hiring nurses, lab technicians, pharmacists and allied staff. Apply free from the Inthes app.')

@section('content')

{{-- ── hero ─────────────────────────────────────────────────────────────── --}}
<section class="hero-wash relative overflow-hidden border-b border-hairline-divider">
    <div class="dot-grid absolute inset-0 opacity-60" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-[1200px] px-page py-xxxl lg:px-xl lg:py-[72px]">
        <div class="stagger max-w-[720px]">
            <span class="inline-flex items-center gap-sm rounded-chip border-chip border-primary-line bg-primary-light px-lg py-sm text-caption font-semibold text-primary-dark">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inset-0 animate-pulse-ring rounded-full bg-primary"></span>
                    <span class="relative h-2 w-2 rounded-full bg-primary"></span>
                </span>
                {{ number_format($stats['live_jobs']) }} live openings right now
            </span>

            <h1 class="mt-lg text-[34px] font-bold leading-[1.15] tracking-[-0.5px] text-ink sm:text-[44px]">
                Healthcare jobs that
                <span class="text-primary">actually reply</span>
            </h1>

            <p class="mt-lg max-w-[560px] text-body text-ink-secondary">
                Nursing, lab, pharmacy and allied roles from employers we verify before their
                postings go live. Browse here, apply in seconds from the app.
            </p>

            {{-- The search box is a real GET form to the browse page, so it
                 works before any JavaScript runs and its results are a URL. --}}
            <form action="{{ route('site.jobs') }}" method="GET"
                  class="mt-xl flex flex-col gap-sm rounded-card border-hair border-hairline bg-surface p-md shadow-card sm:flex-row">
                <div class="relative flex-1">
                    <span aria-hidden="true" class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">
                        @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[18px] w-[18px]'])
                    </span>
                    <input type="search" name="q" placeholder="Nurse, lab technician, pharmacist…"
                           class="h-[50px] w-full rounded-field bg-surface-muted pl-[42px] pr-md text-input text-ink placeholder:text-ink-muted
                                  border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                                  focus:border-focus focus:border-primary focus:shadow-glow">
                </div>
                <div class="relative sm:w-[190px]">
                    <select name="city"
                            class="h-[50px] w-full cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink
                                   border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro
                                   focus:border-focus focus:border-primary focus:shadow-glow">
                        <option value="">Any city</option>
                        @foreach ($cities as $row)
                            <option value="{{ $row['city'] }}">{{ $row['city'] }}</option>
                        @endforeach
                    </select>
                    <span aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">
                        @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])
                    </span>
                </div>
                <button type="submit"
                        class="inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-primary px-xl text-btn font-semibold text-ink-onPrimary shadow-button
                               transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
                    Search jobs
                </button>
            </form>

            <div class="mt-xl flex flex-wrap items-center gap-xl">
                @foreach ([
                    ['value' => $stats['live_jobs'], 'label' => 'Live openings'],
                    ['value' => $stats['employers'], 'label' => 'Verified employers'],
                    ['value' => $stats['candidates'], 'label' => 'Registered candidates'],
                ] as $stat)
                    <div>
                        <span class="block text-stat tabular-nums text-primary">{{ number_format($stat['value']) }}</span>
                        <span class="block text-caption text-ink-muted">{{ $stat['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ── categories ───────────────────────────────────────────────────────── --}}
@if ($categories)
<section class="mx-auto max-w-[1200px] px-page py-xxl lg:px-xl">
    @include('site.partials.section-heading', [
        'kicker' => 'Browse by role',
        'title' => 'What are you trained for?',
        'hint' => 'Only roles with openings right now are listed.',
    ])

    <div class="stagger grid grid-cols-2 gap-md sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($categories as $category)
            <a href="{{ route('site.jobs', ['role' => $category['role']]) }}"
               class="group rounded-card border-hair border-hairline bg-surface p-lg shadow-card
                      transition-[box-shadow,border-color,transform] duration-micro ease-out
                      hover:-translate-y-[2px] hover:border-primary-line hover:shadow-raised">
                <span class="flex h-10 w-10 items-center justify-center rounded-field bg-primary-light text-primary
                             transition-transform duration-micro group-hover:scale-105">
                    @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-5 w-5'])
                </span>
                <h3 class="mt-md truncate text-h5 text-ink">{{ $category['role'] }}</h3>
                <p class="mt-[2px] text-caption text-ink-muted">
                    {{ $category['count'] }} {{ Str::plural('opening', $category['count']) }}
                </p>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ── latest jobs ──────────────────────────────────────────────────────── --}}
@if ($latest->isNotEmpty())
<section class="mx-auto max-w-[1200px] px-page pb-xxl lg:px-xl">
    @include('site.partials.section-heading', [
        'kicker' => 'Fresh today',
        'title' => 'Latest openings',
        'action' => ['label' => 'See all jobs', 'href' => route('site.jobs')],
    ])

    <div class="stagger grid grid-cols-1 gap-md md:grid-cols-2 lg:grid-cols-3">
        @foreach ($latest as $job)
            @include('site.partials.job-card', ['job' => $job])
        @endforeach
    </div>
</section>
@endif

{{-- ── how it works ─────────────────────────────────────────────────────── --}}
<section class="border-y border-hairline-divider bg-canvas-alt">
    <div class="mx-auto max-w-[1200px] px-page py-xxl lg:px-xl">
        @include('site.partials.section-heading', [
            'kicker' => 'How it works',
            'title' => 'Three steps, no paperwork',
        ])

        <div class="stagger grid grid-cols-1 gap-md md:grid-cols-3">
            @foreach ([
                ['n' => '01', 'title' => 'Find the role', 'body' => 'Filter by city, shift and salary. Every employer here has passed a document check before their jobs went live.'],
                ['n' => '02', 'title' => 'Get the app', 'body' => 'Scan the code, sign in with your phone number. Your profile is built once and reused for every application.'],
                ['n' => '03', 'title' => 'Apply in seconds', 'body' => 'Smart Apply sends only what the employer asked for, and you can track every application in one place.'],
            ] as $step)
                <div class="rounded-card border-hair border-hairline bg-surface p-lg shadow-card">
                    <span class="text-h2 text-primary/25">{{ $step['n'] }}</span>
                    <h3 class="mt-sm text-h4 text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-xs text-bodysm text-ink-secondary">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── app + employer CTAs ──────────────────────────────────────────────── --}}
<section class="mx-auto max-w-[1200px] px-page py-xxl lg:px-xl">
    <div class="grid grid-cols-1 gap-md lg:grid-cols-[1.4fr,1fr]">

        <div class="red-wash relative overflow-hidden rounded-card border-hair border-primary-line p-xl">
            <div class="flex flex-col items-start gap-lg sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <span class="block text-kicker text-primary-dark">FOR CANDIDATES</span>
                    <h2 class="mt-xs text-h2 text-ink">Applying happens in the app</h2>
                    <p class="mt-sm max-w-[420px] text-bodysm text-ink-secondary">
                        Your profile, resume and every application you have sent, in one place —
                        and employers reply to you there too.
                    </p>
                    <a href="{{ App\Support\StoreQr::storeUrl() }}" target="_blank" rel="noopener"
                       class="mt-lg inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-primary px-xl text-btn font-semibold text-ink-onPrimary shadow-button
                              transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
                        Get it on Google Play
                    </a>
                </div>

                {{-- Server-rendered SVG, so it is in the HTML on first paint. --}}
                <div class="w-[148px] shrink-0 rounded-card border-hair border-hairline bg-canvas p-md shadow-card [&>svg]:h-full [&>svg]:w-full">
                    {!! App\Support\StoreQr::svg() !!}
                </div>
            </div>
        </div>

        <div class="flex flex-col justify-between rounded-card border-hair border-hairline bg-surface-dark p-xl">
            <div>
                <span class="block text-kicker text-white/60">FOR EMPLOYERS</span>
                <h2 class="mt-xs text-h2 text-white">Hiring? Post in minutes</h2>
                <p class="mt-sm text-bodysm text-white/70">
                    Reach trained paramedical staff directly. Your first posting is free, and every
                    applicant arrives with a complete profile.
                </p>
            </div>
            <a href="{{ route('site.post-job') }}"
               class="mt-lg inline-flex h-[50px] items-center justify-center rounded-button bg-white px-xl text-btn font-semibold text-ink
                      transition-transform duration-micro ease-out hover:bg-white/90 active:scale-[0.97]">
                Post a job
            </a>
        </div>
    </div>
</section>

@endsection
