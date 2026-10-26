{{--
  Not found — in the site's own chrome, so somebody following a taken-down
  job link or a mistyped URL still has the header, search and a way back
  instead of the framework's bare page. API 404s are JSON and never get here;
  the admin panel is a catch-all route, so it never 404s either.
--}}
@extends('site.layout')

@section('title', 'Page not found — Inthes')

@section('content')
@php $message = isset($exception) && $exception->getMessage() !== '' ? $exception->getMessage() : 'That page does not exist or has moved.'; @endphp
<div class="mx-auto flex max-w-[560px] flex-col items-center px-page py-xxxl text-center">
    <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-light text-primary">
        @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-7 w-7'])
    </span>
    <p class="mt-lg text-kicker text-ink-secondary">404</p>
    <h1 class="mt-xs text-h1 text-ink">Not found</h1>
    <p class="mt-sm text-body text-ink-secondary">{{ $message }}</p>
    <div class="mt-xl flex flex-wrap justify-center gap-sm">
        <a href="{{ route('site.jobs') }}" class="wa-btn">Browse jobs</a>
        <a href="{{ route('site.home') }}" class="wa-btn-ghost">Go home</a>
    </div>
</div>
@endsection
