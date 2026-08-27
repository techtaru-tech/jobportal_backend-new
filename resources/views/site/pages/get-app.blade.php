@extends('site.layout')

@section('title', 'Get the Inthes app — apply to healthcare jobs in seconds')
@section('description', 'Install Inthes on Android to build your profile once, apply with one tap, and track every application and employer reply in one place.')

@section('content')
<section class="hero-wash relative overflow-hidden border-b border-hairline-divider">
    <div class="dot-grid absolute inset-0 opacity-60" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-[1000px] px-page py-xxxl text-center lg:px-xl">
        <div class="stagger">
            @include('admin.partials.logo', ['size' => 56, 'class' => 'mx-auto'])

            <span class="mt-lg block text-kicker text-ink-secondary">THE INTHES APP</span>
            <h1 class="mt-xs text-[32px] font-bold leading-[1.15] tracking-[-0.4px] text-ink sm:text-[40px]">
                Everything after “apply”<br class="hidden sm:block">
                happens <span class="text-primary">here</span>
            </h1>
            <p class="mx-auto mt-lg max-w-[540px] text-body text-ink-secondary">
                Browsing works fine on the web. Applying needs your profile, your resume and a
                place for the employer to reply — so that part lives in the app.
            </p>

            <div class="mt-xl flex flex-col items-center gap-lg">
                {{-- Rendered server-side, so it is in the HTML on first paint —
                     this page exists to be scanned, and a QR that pops in after
                     a JS round trip is a QR somebody has already given up on. --}}
                <div class="w-[228px] rounded-card border-hair border-hairline bg-canvas p-lg shadow-card [&>svg]:h-full [&>svg]:w-full">
                    {!! App\Support\StoreQr::svg() !!}
                </div>

                <p class="text-caption text-ink-muted">Point your phone camera at the code</p>

                <a href="{{ App\Support\StoreQr::storeUrl() }}" target="_blank" rel="noopener"
                   class="inline-flex h-[52px] items-center justify-center gap-sm rounded-button bg-primary px-xxl text-btn font-semibold text-ink-onPrimary shadow-button
                          transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
                    Get it on Google Play
                </a>

                {{-- Stated rather than left to be discovered on the store page.
                     iOS is genuinely not shipped yet, and someone on an iPhone
                     should find that out before they scan. --}}
                <p class="text-caption text-ink-muted">Android · Free · iOS coming soon</p>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-[1200px] px-page py-xxl lg:px-xl">
    @include('site.partials.section-heading', [
        'kicker' => 'What you get',
        'title' => 'Built for the way you actually job-hunt',
    ])

    <div class="stagger grid grid-cols-1 gap-md md:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['icon' => 'userCheck', 'title' => 'One profile, reused', 'body' => 'Fill it in once. Every application afterwards sends a frozen copy, so an employer always sees what you actually submitted.'],
            ['icon' => 'clipboard', 'title' => 'Smart Apply', 'body' => 'Each posting asks only for the fields that employer cares about, instead of a form that demands everything from everybody.'],
            ['icon' => 'briefcase', 'title' => 'Track every stage', 'body' => 'Applied, shortlisted, selected — with the dates, so you know whether silence means no or means not yet.'],
            ['icon' => 'bell', 'title' => 'Told, not left guessing', 'body' => 'A notification the moment an employer moves your application or schedules an interview.'],
            ['icon' => 'badgeCheck', 'title' => 'Verified employers only', 'body' => 'Every hiring organisation passes a document check before any of its postings reach you.'],
            ['icon' => 'fileText', 'title' => 'Resume and intro video', 'body' => 'Attach a document, or record a one-minute introduction — some employers watch it before they read anything.'],
        ] as $feature)
            <div class="rounded-card border-hair border-hairline bg-surface p-lg shadow-card
                        transition-[box-shadow,border-color,transform] duration-micro ease-out
                        hover:-translate-y-[2px] hover:border-hairline-strong hover:shadow-raised">
                <span class="flex h-10 w-10 items-center justify-center rounded-field bg-primary-light text-primary">
                    @include('admin.partials.icon', ['name' => $feature['icon'], 'class' => 'h-5 w-5'])
                </span>
                <h3 class="mt-md text-h4 text-ink">{{ $feature['title'] }}</h3>
                <p class="mt-xs text-bodysm text-ink-secondary">{{ $feature['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

<section class="border-t border-hairline-divider bg-canvas-alt">
    <div class="mx-auto flex max-w-[1200px] flex-col items-center justify-between gap-lg px-page py-xxl text-center lg:flex-row lg:px-xl lg:text-left">
        <div>
            <h2 class="text-h2 text-ink">Hiring instead?</h2>
            <p class="mt-xs text-bodysm text-ink-secondary">
                Employers post and manage openings on the web — no app needed.
            </p>
        </div>
        <a href="{{ route('site.post-job') }}"
           class="inline-flex h-[50px] shrink-0 items-center justify-center rounded-button border-btn border-primary bg-surface px-xl text-btn font-semibold text-primary
                  transition-colors duration-micro hover:bg-primary-light">
            Post a job
        </a>
    </div>
</section>
@endsection
