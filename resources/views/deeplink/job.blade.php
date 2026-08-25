@extends('deeplink.layout')

@php
    $facts = collect([
        $job->city,
        $job->salaryDisplay(),
        $job->experience,
        $job->type,
        $job->shift,
    ])->filter()->values();

    $summary = trim((string) $job->about) !== ''
        ? \Illuminate\Support\Str::limit(strip_tags($job->about), 160)
        : trim(collect([$job->title, $job->organisation, $job->city])->filter()->implode(' · '));
@endphp

@section('title', $job->title.' at '.$job->organisation)

@section('meta')
    {{--
        These are the whole reason this page is server-rendered. WhatsApp,
        Facebook, X and iMessage fetch the URL with a crawler that runs no
        JavaScript — whatever is here is the preview card a recipient sees
        before deciding whether to tap. Without them the link is a bare URL and
        nobody taps it.
    --}}
    <meta name="description" content="{{ $summary }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $job->title }} at {{ $job->organisation }}">
    <meta property="og:description" content="{{ $summary }}">
    <meta property="og:url" content="{{ $shareUrl }}">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $job->title }} at {{ $job->organisation }}">
    <meta name="twitter:description" content="{{ $summary }}">

    {{-- Search engines have no business indexing a link meant for one person. --}}
    <meta name="robots" content="noindex">
@endsection

@section('body')
    <div class="card">
        <span class="kicker">{{ $job->role }} · {{ $job->code }}</span>
        <h1>{{ $job->title }}</h1>
        <p class="org">
            {{ $job->organisation }}@if ($job->organisationRecord?->verified) · Verified @endif
        </p>

        @if ($facts->isNotEmpty())
            <ul class="facts">
                @foreach ($facts as $fact)
                    <li>{{ $fact }}</li>
                @endforeach
            </ul>
        @endif

        @if (trim((string) $job->about) !== '')
            <h2>About the role</h2>
            <p>{{ $job->about }}</p>
        @endif

        @if (! empty($job->duties))
            <h2>What you'll do</h2>
            <ul>
                @foreach (array_slice($job->duties, 0, 5) as $duty)
                    <li>{{ $duty }}</li>
                @endforeach
            </ul>
        @endif

        {{--
            Ordered deliberately. Anyone who has the app already had it opened
            for them by the OS and never sees this page, so the store is the
            likelier action for whoever is actually reading it. "Open in the
            app" stays second for the case that verification silently failed.
        --}}
        <a class="btn btn-primary" href="{{ $storeUrl }}">Get the app to apply</a>
        <a class="btn btn-secondary" id="open-app" href="{{ $appUrl }}">Open in the app</a>
    </div>
@endsection

@section('scripts')
    <script>
        // A single tap-to-open, not an automatic redirect.
        //
        // Auto-firing the custom scheme on load shows "Cannot open page" to
        // every visitor without the app — which, on this page, is most of
        // them. It also breaks the back button. So the scheme is only ever
        // tried because somebody asked for it.
        document.getElementById('open-app').addEventListener('click', function (event) {
            event.preventDefault();

            var appUrl = this.getAttribute('href');
            var storeUrl = @json($storeUrl);
            var returned = false;

            // If the app takes over, this tab is backgrounded and the timer
            // never runs. If nothing handles the scheme we are still here, so
            // fall through to the store rather than leaving a dead tap.
            function cancel() { returned = true; }
            document.addEventListener('visibilitychange', cancel, { once: true });
            window.addEventListener('pagehide', cancel, { once: true });

            window.location.href = appUrl;

            setTimeout(function () {
                if (!returned && !document.hidden) {
                    window.location.href = storeUrl;
                }
            }, 1200);
        });
    </script>
@endsection
