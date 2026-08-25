@extends('deeplink.layout')

@section('title', 'This job has closed')

@section('meta')
    <meta name="robots" content="noindex">
    <meta property="og:title" content="This job is no longer open">
    <meta property="og:description" content="The posting has closed, but there are others.">
@endsection

@section('body')
    {{--
        Shown for both "no such code" and "withdrawn since". They are told
        apart deliberately *not* at all: a link forwarded for a week and a
        mistyped one look the same to the person holding it, and neither
        deserves a stack trace.
    --}}
    <div class="card">
        <span class="kicker">No longer available</span>
        <h1>This job has closed</h1>
        <p class="org">The employer has stopped accepting applications, or the link has expired.</p>
        <a class="btn btn-primary" href="{{ $storeUrl }}">Browse other jobs in the app</a>
    </div>
@endsection
