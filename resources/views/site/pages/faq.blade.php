@extends('site.layout')

@section('title', 'Help & support — Inthes')
@section('description', 'Answers to the questions candidates and employers ask most about Inthes.')

@section('content')
<div class="mx-auto max-w-[820px] px-page py-xl lg:px-xl">
    <nav aria-label="Breadcrumb" class="mb-lg flex items-center gap-xs text-caption text-ink-muted">
        <a href="{{ route('site.home') }}" class="transition-colors hover:text-ink">Home</a>
        @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-3 w-3'])
        <span class="text-ink-secondary">Help</span>
    </nav>

    <span class="block text-kicker text-ink-secondary">HELP &amp; SUPPORT</span>
    <h1 class="mt-[2px] text-h1 text-ink">Questions people actually ask</h1>

    @if ($faqs->isEmpty())
        <div class="mt-xl overflow-hidden rounded-card border-hair border-hairline bg-surface">
            <div class="flex flex-col items-center justify-center p-xxxl text-center">
                <div class="red-wash flex h-[76px] w-[76px] items-center justify-center rounded-card border-hair border-hairline text-ink-muted">
                    @include('admin.partials.icon', ['name' => 'inbox', 'class' => 'h-[28px] w-[28px]'])
                </div>
                <h2 class="mt-lg text-h4 text-ink">No answers published yet</h2>
                <p class="mt-xs max-w-sm text-bodysm text-ink-secondary">
                    These are written in the admin panel and will show up here as soon as they are.
                </p>
            </div>
        </div>
    @else
        {{-- Native `<details>`, not an Alpine accordion. It is keyboard
             accessible and expandable by the browser's own find-in-page for
             free, and the content is in the DOM either way — which matters,
             because these answers are worth indexing. --}}
        <div class="stagger mt-xl space-y-sm">
            @foreach ($faqs as $faq)
                <details class="group rounded-card border-hair border-hairline bg-surface shadow-card">
                    <summary class="flex cursor-pointer items-center justify-between gap-md p-lg text-h5 text-ink marker:content-none [&::-webkit-details-marker]:hidden">
                        <span class="min-w-0">{{ $faq->question }}</span>
                        <span class="shrink-0 text-ink-muted transition-transform duration-micro group-open:rotate-180">
                            @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])
                        </span>
                    </summary>
                    <div class="border-t border-hairline-divider px-lg py-lg">
                        <p class="whitespace-pre-line text-bodysm text-ink-secondary">{{ $faq->answer }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    <div class="mt-xl rounded-card border-hair border-hairline bg-canvas-alt p-xl text-center">
        <h2 class="text-h4 text-ink">Still stuck?</h2>
        <p class="mt-xs text-bodysm text-ink-secondary">Our contact details are on the contact page.</p>
        <a href="{{ route('site.page', 'contact') }}"
           class="mt-lg inline-flex h-[46px] items-center justify-center rounded-button border-btn border-primary bg-surface px-lg text-btn font-semibold text-primary
                  transition-colors hover:bg-primary-light">
            Contact us
        </a>
    </div>
</div>
@endsection
