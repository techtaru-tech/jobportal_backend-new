{{--
  The employer dashboard: the site header, a left sidebar on desktop (a
  scrolling tab row on phones) and a wide content area for tables and forms.

  Pages set `@section('employer_key', 'jobs')` and put their markup in
  `@section('employer')`. Signed-out visitors see `@section('employer_guest')`
  if the page defines one (the dashboard uses it for the "hire with Inthes"
  pitch), otherwise a sign-in card. Page scripts go in `@push('page-scripts')`.
--}}
@extends('site.layout')

@section('requires_auth', '1')
@section('body_class', 'bg-canvas-alt')
@section('hide_footer', '1')

@section('content')
@php
  $key = trim($__env->yieldContent('employer_key', 'dashboard'));
  $menu = [
    ['dashboard', 'Dashboard', '/employer', 'dashboard'],
    ['jobs', 'Jobs', '/employer/jobs', 'briefcase'],
    ['post', 'Post a job', '/employer/jobs/new', 'plus'],
    ['companies', 'Companies', '/employer/companies', 'building'],
    ['messages', 'Messages', '/employer/messages', 'message'],
    ['plan', 'Plan & billing', '/employer/plan', 'creditCard'],
    ['settings', 'Contact details', '/employer/settings', 'sliders'],
  ];
@endphp
<div class="mx-auto w-full max-w-[1320px] px-page py-xl lg:px-xl">
  <template x-if="$store.auth.ready && !$store.auth.signedIn">
    <div>
      @hasSection('employer_guest')
        @yield('employer_guest')
      @else
        <div class="wa-card mx-auto mt-xxl max-w-[480px] text-center">
          <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
            @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-6 w-6'])
          </span>
          <h1 class="mt-lg text-h2 text-ink">Sign in to hire</h1>
          <p class="mt-xs text-bodysm text-ink-secondary">Post jobs and manage applicants with your mobile number — the same account works in the Inthes app.</p>
          <button type="button" @click="signIn('to post jobs and manage applicants')" class="wa-btn mt-xl w-full">Sign in</button>
        </div>
      @endif
    </div>
  </template>
  <template x-if="!$store.auth.ready">
    @include('site.ui.spinner')
  </template>

  <div x-show="$store.auth.ready && $store.auth.signedIn" x-cloak class="lg:grid lg:grid-cols-[232px_1fr] lg:gap-xxl">
    <aside class="mb-lg lg:mb-0">
      <div class="lg:sticky lg:top-[92px]">
        <p class="mb-sm hidden px-md text-kicker text-ink-secondary lg:block">EMPLOYER</p>
        <nav class="no-scrollbar -mx-page flex gap-xs overflow-x-auto px-page lg:mx-0 lg:flex-col lg:gap-[2px] lg:overflow-visible lg:px-0">
          @foreach ($menu as [$k, $label, $href, $icon])
            <a href="{{ url($href) }}"
               class="flex shrink-0 items-center gap-md rounded-field px-md py-[9px] text-bodysm transition-colors
                      {{ $key === $k ? 'bg-surface font-semibold text-primary-dark shadow-card lg:bg-primary-light lg:shadow-none' : 'text-ink-secondary hover:bg-surface hover:text-ink' }}">
              @include('admin.partials.icon', ['name' => $icon, 'class' => 'h-[17px] w-[17px]'])
              {{ $label }}
            </a>
          @endforeach
        </nav>
        <div class="mt-xl hidden rounded-card border-hair border-hairline bg-surface p-lg lg:block">
          <p class="text-h5 text-ink">Hire on the go</p>
          <p class="mt-xs text-caption text-ink-secondary">Get applicant alerts on your phone with the Inthes app.</p>
          <a :href="storeUrl()" class="wa-btn-ghost-sm mt-md w-full">Get the app</a>
        </div>
      </div>
    </aside>

    <section class="min-w-0">
      @yield('employer')
    </section>
  </div>
</div>
@endsection
