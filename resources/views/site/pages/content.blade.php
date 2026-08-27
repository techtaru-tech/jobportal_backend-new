@extends('site.layout')

@section('title', $title.' — Inthes')
@section('description', 'Inthes '.strtolower($title).'.')

@section('content')
<div class="mx-auto max-w-[820px] px-page py-xl lg:px-xl">
    <nav aria-label="Breadcrumb" class="mb-lg flex items-center gap-xs text-caption text-ink-muted">
        <a href="{{ route('site.home') }}" class="transition-colors hover:text-ink">Home</a>
        @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-3 w-3'])
        <span class="text-ink-secondary">{{ $title }}</span>
    </nav>

    <span class="block text-kicker text-ink-secondary">{{ strtoupper($slug) }}</span>
    <h1 class="mt-[2px] text-h1 text-ink">{{ $title }}</h1>

    @if ($page?->updated_at)
        <p class="mt-sm text-caption text-ink-muted">Last updated {{ $page->updated_at->format('j M Y') }}</p>
    @endif

    <article class="mt-xl rounded-card border-hair border-hairline bg-surface p-xl shadow-card">
        @if (filled($page?->body))
            {{-- `whitespace-pre-line`, not a Markdown renderer: an admin types
                 this into a plain textarea in the panel, so paragraphs are the
                 only structure it actually carries. Escaped, because that copy
                 is user input and must never become markup. --}}
            <div class="whitespace-pre-line text-body text-ink-secondary">{{ $page->body }}</div>
        @else
            <div class="flex flex-col items-center justify-center py-xxl text-center">
                <div class="red-wash flex h-[76px] w-[76px] items-center justify-center rounded-card border-hair border-hairline text-ink-muted">
                    @include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-[28px] w-[28px]'])
                </div>
                <h2 class="mt-lg text-h4 text-ink">Nothing here yet</h2>
                <p class="mt-xs max-w-sm text-bodysm text-ink-secondary">
                    This page has not been written yet. It is edited from the admin panel, so it
                    will appear here as soon as it is.
                </p>
            </div>
        @endif
    </article>

    @if ($slug === 'contact')
        <div class="mt-md rounded-card border-hair border-primary-line bg-primary-light p-xl">
            <h2 class="text-h4 text-ink">Hiring, or need help with an application?</h2>
            <p class="mt-xs text-bodysm text-ink-secondary">
                Employers can post and manage jobs on the web. Candidates are best served in the
                app, where their applications and messages already live.
            </p>
            <div class="mt-lg flex flex-wrap gap-sm">
                <a href="{{ route('site.post-job') }}"
                   class="inline-flex h-[46px] items-center justify-center rounded-button bg-primary px-lg text-btn font-semibold text-ink-onPrimary shadow-button
                          transition-[background-color,transform] duration-micro hover:bg-primary-dark active:scale-[0.97]">
                    Post a job
                </a>
                <a href="{{ route('site.get-app') }}"
                   class="inline-flex h-[46px] items-center justify-center rounded-button border-btn border-primary bg-surface px-lg text-btn font-semibold text-primary
                          transition-colors hover:bg-primary-light">
                    Get the app
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
