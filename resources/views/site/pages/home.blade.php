@extends('site.layout')

@section('title', 'Inthes — Nursing, lab & pharmacy jobs across India')
@section('description', 'Browse verified healthcare employers hiring nurses, lab technicians, pharmacists and allied staff. Apply free from the Inthes app.')

@section('content')

{{-- ── hero ─────────────────────────────────────────────────────────────── --}}
<section class="hero-wash hero-wash-2 relative overflow-hidden border-b border-hairline-divider">
    {{-- Decorative layers, in order of depth. `data-parallax` moves them at
         different rates so the hero has a floor and the content sits above it;
         the rates are small because a marketing hero that slides is a hero
         nobody can read. --}}
    <div class="dot-grid absolute inset-0 opacity-60" aria-hidden="true" data-parallax="0.06"></div>
    <div class="orb float-slow absolute -right-[120px] -top-[100px] h-[420px] w-[420px]" aria-hidden="true" data-parallax="0.10"></div>
    <div class="orb float-slow absolute -bottom-[180px] left-[8%] h-[320px] w-[320px] opacity-70" aria-hidden="true" data-parallax="0.04"></div>

    <div class="relative mx-auto max-w-[1200px] px-page py-xxxl lg:px-xl lg:py-[84px]">
        <div class="grid grid-cols-1 items-center gap-xxl lg:grid-cols-[1.15fr,1fr]">

            {{-- `.enter` rather than a scroll reveal: this is already in view on
                 load, so an observer would fire immediately and the wait would
                 read as jank. --}}
            <div class="enter min-w-0">
                <span class="inline-flex items-center gap-sm rounded-chip border-chip border-primary-line bg-primary-light px-lg py-sm text-caption font-semibold text-primary-dark">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inset-0 animate-pulse-ring rounded-full bg-primary"></span>
                        <span class="relative h-2 w-2 rounded-full bg-primary"></span>
                    </span>
                    <span class="tabular-nums" data-count-to="{{ $stats['live_jobs'] }}">{{ number_format($stats['live_jobs']) }}</span>
                    live openings right now
                </span>

                <h1 class="mt-lg text-[36px] font-bold leading-[1.1] tracking-[-0.8px] text-ink sm:text-[52px]">
                    Healthcare jobs that
                    <span class="text-gradient">actually reply</span>
                </h1>

                <p class="mt-lg max-w-[520px] text-body text-ink-secondary">
                    Nursing, lab, pharmacy and allied roles from employers we verify before their
                    postings go live. Browse here, apply in seconds from the app.
                </p>

                {{-- A real GET form to the browse page, so it works before any
                     JavaScript runs and its results are a shareable URL. --}}
                <form action="{{ route('site.jobs') }}" method="GET"
                      class="ring-gradient mt-xl flex flex-col gap-sm rounded-card p-md shadow-card sm:flex-row">
                    <div class="relative flex-1">
                        <span aria-hidden="true" class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">
                            @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[18px] w-[18px]'])
                        </span>
                        <input type="search" name="q" placeholder="Nurse, lab technician, pharmacist…"
                               class="h-[50px] w-full rounded-field bg-surface-muted pl-[42px] pr-md text-input text-ink placeholder:text-ink-muted
                                      border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                                      focus:border-focus focus:border-primary focus:shadow-glow">
                    </div>
                    <div class="relative sm:w-[180px]">
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
                            class="sheen inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-primary px-xl text-btn font-semibold text-ink-onPrimary shadow-button
                                   transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
                        Search jobs
                    </button>
                </form>

                <div class="mt-xl flex flex-wrap items-center gap-x-xxl gap-y-lg">
                    @foreach ([
                        ['value' => $stats['live_jobs'], 'label' => 'Live openings'],
                        ['value' => $stats['employers'], 'label' => 'Verified employers'],
                        ['value' => $stats['candidates'], 'label' => 'Registered candidates'],
                    ] as $stat)
                        <div>
                            {{-- The real figure is in the HTML; the counter animates
                                 up to it, so a reader without JS sees the number. --}}
                            <span class="block text-stat tabular-nums text-primary"
                                  data-count-to="{{ $stat['value'] }}">{{ number_format($stat['value']) }}</span>
                            <span class="block text-caption text-ink-muted">{{ $stat['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- The app, shown rather than described. Hidden below `lg`: on a
                 phone the visitor already has the device this is a picture
                 of, and the CTA below serves them better. --}}
            <div class="enter relative hidden lg:block" aria-hidden="true">
                <div class="relative mx-auto w-[300px]">
                    <div class="orb absolute -inset-8 opacity-80"></div>

                    {{-- A phone frame drawn in CSS, not an image asset: it stays
                         sharp at any density and costs no download. --}}
                    <div class="relative rounded-[38px] border-[10px] border-surface-dark bg-canvas shadow-raised">
                        <div class="absolute left-1/2 top-[10px] z-10 h-[5px] w-[80px] -translate-x-1/2 rounded-full bg-surface-dark/80"></div>

                        <div class="overflow-hidden rounded-[28px]">
                            <div class="bg-primary px-lg pb-xl pt-xxl">
                                <span class="block text-kicker text-white/70">GOOD MORNING</span>
                                <span class="mt-xs block text-h3 text-white">Find your next role</span>
                                <div class="mt-md flex items-center gap-sm rounded-field bg-white/95 px-md py-sm">
                                    <span class="text-ink-muted">
                                        @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-4 w-4'])
                                    </span>
                                    <span class="text-bodysm text-ink-muted">Search jobs…</span>
                                </div>
                            </div>

                            <div class="space-y-sm bg-canvas-alt p-md">
                                @foreach ($latest->take(3) as $preview)
                                    <div class="rounded-card border-hair border-hairline bg-surface p-md shadow-card">
                                        <div class="flex items-center gap-sm">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-logo bg-primary-light text-caption font-semibold text-primary-dark">
                                                {{ App\Support\Display::initials($preview->organisation) }}
                                            </span>
                                            <div class="min-w-0">
                                                <span class="block truncate text-caption font-semibold text-ink">{{ $preview->title }}</span>
                                                <span class="block truncate text-[10px] text-ink-muted">{{ $preview->city }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── trust strip ──────────────────────────────────────────────────────── --}}
<section class="border-b border-hairline-divider bg-canvas-alt">
    <div class="mx-auto max-w-[1200px] px-page py-lg lg:px-xl">
        <div class="grid grid-cols-2 gap-lg sm:grid-cols-4" data-reveal-group="60">
            @foreach ([
                ['icon' => 'badgeCheck', 'title' => 'Verified employers', 'body' => 'Document-checked before going live'],
                ['icon' => 'clipboard', 'title' => 'Smart Apply', 'body' => 'Only the fields that posting needs'],
                ['icon' => 'bell', 'title' => 'Real replies', 'body' => 'Notified when your status moves'],
                ['icon' => 'shieldCheck', 'title' => 'Free for candidates', 'body' => 'No fees, ever'],
            ] as $item)
                <div class="flex items-start gap-md" data-reveal>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-field bg-primary-light text-primary">
                        @include('admin.partials.icon', ['name' => $item['icon'], 'class' => 'h-[18px] w-[18px]'])
                    </span>
                    <div class="min-w-0">
                        <span class="block text-bodysm font-semibold text-ink">{{ $item['title'] }}</span>
                        <span class="block text-caption text-ink-muted">{{ $item['body'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── categories ───────────────────────────────────────────────────────── --}}
@if ($categories)
<section class="mx-auto max-w-[1200px] px-page py-xxl lg:px-xl">
    <div data-reveal>
        @include('site.partials.section-heading', [
            'kicker' => 'Browse by role',
            'title' => 'What are you trained for?',
            'hint' => 'Only roles with openings right now are listed.',
        ])
    </div>

    <div class="grid grid-cols-2 gap-md sm:grid-cols-3 lg:grid-cols-4" data-reveal-group="60">
        @foreach ($categories as $category)
            <a href="{{ route('site.jobs', ['role' => $category['role']]) }}" data-reveal
               class="lift group rounded-card border-hair border-hairline bg-surface p-lg shadow-card
                      hover:border-primary-line hover:shadow-raised">
                <span class="flex h-10 w-10 items-center justify-center rounded-field bg-primary-light text-primary
                             transition-transform duration-micro group-hover:scale-110">
                    @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-5 w-5'])
                </span>
                <h3 class="mt-md truncate text-h5 text-ink">{{ $category['role'] }}</h3>
                <p class="mt-[2px] flex items-center gap-xs text-caption text-ink-muted">
                    <span>{{ $category['count'] }} {{ Str::plural('opening', $category['count']) }}</span>
                    <span class="nudge text-primary opacity-0 transition-opacity group-hover:opacity-100">
                        @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-3 w-3'])
                    </span>
                </p>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ── latest jobs ──────────────────────────────────────────────────────── --}}
@if ($latest->isNotEmpty())
<section class="mx-auto max-w-[1200px] px-page pb-xxl lg:px-xl">
    <div data-reveal>
        @include('site.partials.section-heading', [
            'kicker' => 'Fresh today',
            'title' => 'Latest openings',
            'action' => ['label' => 'See all jobs', 'href' => route('site.jobs')],
        ])
    </div>

    <div class="grid grid-cols-1 gap-md md:grid-cols-2 lg:grid-cols-3" data-reveal-group="70">
        @foreach ($latest as $job)
            <div data-reveal>
                @include('site.partials.job-card', ['job' => $job])
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ── how it works ─────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden border-y border-hairline-divider bg-canvas-alt">
    <div class="dot-grid absolute inset-0 opacity-40" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-[1200px] px-page py-xxl lg:px-xl">
        <div data-reveal>
            @include('site.partials.section-heading', [
                'kicker' => 'How it works',
                'title' => 'Three steps, no paperwork',
            ])
        </div>

        <div class="grid grid-cols-1 gap-md md:grid-cols-3" data-reveal-group="90">
            @foreach ([
                ['n' => '01', 'title' => 'Find the role', 'body' => 'Filter by city, shift and salary. Every employer here has passed a document check before their jobs went live.'],
                ['n' => '02', 'title' => 'Get the app', 'body' => 'Scan the code, sign in with your phone number. Your profile is built once and reused for every application.'],
                ['n' => '03', 'title' => 'Apply in seconds', 'body' => 'Smart Apply sends only what the employer asked for, and you can track every application in one place.'],
            ] as $i => $step)
                <div class="lift relative rounded-card border-hair border-hairline bg-surface p-lg shadow-card" data-reveal>
                    {{-- The connector between steps, on wide screens only —
                         stacked cards do not read as a left-to-right sequence. --}}
                    @if ($i < 2)
                        <span class="red-rule absolute right-[-12px] top-[38px] hidden h-[1px] w-[24px] opacity-50 md:block" aria-hidden="true"></span>
                    @endif
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

        <div class="red-wash relative overflow-hidden rounded-card border-hair border-primary-line p-xl" data-reveal="left">
            <div class="orb absolute -right-[60px] -top-[80px] h-[240px] w-[240px] opacity-70" aria-hidden="true"></div>

            <div class="relative flex flex-col items-start gap-lg sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <span class="block text-kicker text-primary-dark">FOR CANDIDATES</span>
                    <h2 class="mt-xs text-h2 text-ink">Applying happens in the app</h2>
                    <p class="mt-sm max-w-[420px] text-bodysm text-ink-secondary">
                        Your profile, resume and every application you have sent, in one place —
                        and employers reply to you there too.
                    </p>
                    @include('site.partials.store-buttons')
                </div>

                @include('site.partials.qr-card', ['size' => 'w-[148px]', 'caption' => null])
            </div>
        </div>

        <div class="relative flex flex-col justify-between overflow-hidden rounded-card border-hair border-hairline bg-surface-dark p-xl" data-reveal="right">
            <div class="orb absolute -bottom-[100px] -left-[60px] h-[240px] w-[240px] opacity-40" aria-hidden="true"></div>

            <div class="relative">
                <span class="block text-kicker text-white/60">FOR EMPLOYERS</span>
                <h2 class="mt-xs text-h2 text-white">Hiring? Post in minutes</h2>
                <p class="mt-sm text-bodysm text-white/70">
                    Reach trained paramedical staff directly. Your first posting is free, and every
                    applicant arrives with a complete profile.
                </p>
            </div>
            <a href="{{ route('site.post-job') }}"
               class="sheen relative mt-lg inline-flex h-[50px] items-center justify-center rounded-button bg-white px-xl text-btn font-semibold text-ink
                      transition-transform duration-micro ease-out hover:bg-white/90 active:scale-[0.97]">
                Post a job
            </a>
        </div>
    </div>
</section>

@endsection
