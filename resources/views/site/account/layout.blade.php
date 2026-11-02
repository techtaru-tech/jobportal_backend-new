{{--
  The job seeker's account area: one site header, a left menu on desktop
  (a scrolling tab row on phones) and the page's content.

  Pages set `@section('account_key', 'applications')` to light their menu
  item, and put their markup in `@section('account')`. Signed-out visitors
  get a sign-in card instead of the content — every page here is about
  their own data. Page scripts go in `@push('page-scripts')`.

  Job seeker accounts only (one account, one job): an employer account gets a
  card offering the switch instead. A page that sets
  `@section('account_any_role', '1')` (notifications) is for both kinds and
  shows an employer account its own data with a link back to its dashboard.
--}}
@extends('site.layout')

@section('requires_auth', '1')
@section('body_class', 'bg-canvas-alt')
@section('hide_footer', '1')

@section('content')
@php
  $key = trim($__env->yieldContent('account_key', 'profile'));
  $anyRole = View::hasSection('account_any_role');
  $menu = [
    ['profile', 'My profile', '/account', 'user'],
    ['applications', 'Applications', '/account/applications', 'clipboard'],
    ['saved', 'Saved jobs', '/account/saved', 'bookmark'],
    ['alerts', 'Job alerts', '/account/alerts', 'bell'],
    ['messages', 'Messages', '/account/messages', 'message'],
    ['notifications', 'Notifications', '/account/notifications', 'inbox'],
    ['plan', 'My plan', '/account/plan', 'crown'],
  ];
@endphp
<div class="mx-auto w-full max-w-[1200px] px-page py-xl lg:px-xl" @if ($anyRole) data-any-role-page @endif>
  <div class="lg:grid lg:grid-cols-[220px_1fr] lg:gap-xxl">
    {{-- Menu --}}
    <aside class="mb-lg lg:mb-0">
      @if ($anyRole)
        <template x-if="$store.auth.isEmployer">
          <a href="{{ url('/employer') }}" class="flex items-center gap-md rounded-field px-md py-[9px] text-bodysm font-semibold text-primary-dark hover:bg-surface">
            @include('admin.partials.icon', ['name' => 'arrowLeft', 'class' => 'h-[17px] w-[17px]'])Employer dashboard
          </a>
        </template>
      @endif
      <nav x-show="!$store.auth.isEmployer" class="no-scrollbar -mx-page flex gap-xs overflow-x-auto px-page lg:sticky lg:top-[92px] lg:mx-0 lg:flex-col lg:gap-[2px] lg:overflow-visible lg:px-0">
        @foreach ($menu as [$k, $label, $href, $icon])
          <a href="{{ url($href) }}"
             class="flex shrink-0 items-center gap-md rounded-field px-md py-[9px] text-bodysm transition-colors
                    {{ $key === $k ? 'bg-surface font-semibold text-primary-dark shadow-card lg:bg-primary-light lg:shadow-none' : 'text-ink-secondary hover:bg-surface hover:text-ink' }}">
            @include('admin.partials.icon', ['name' => $icon, 'class' => 'h-[17px] w-[17px]'])
            {{ $label }}
          </a>
        @endforeach
        <div class="hidden lg:mt-lg lg:block lg:border-t lg:border-hairline-divider lg:pt-lg">
          <a href="{{ route('site.jobs') }}" class="flex items-center gap-md rounded-field px-md py-[9px] text-bodysm text-ink-secondary hover:bg-surface hover:text-ink">
            @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[17px] w-[17px]'])Find jobs
          </a>
          <a href="{{ url('/employer/jobs/new') }}" @click="Inthes.guardArea($event, 'recruiter')" class="flex items-center gap-md rounded-field px-md py-[9px] text-bodysm text-ink-secondary hover:bg-surface hover:text-ink">
            @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-[17px] w-[17px]'])Hiring? Post a job
          </a>
        </div>
      </nav>
    </aside>

    <section class="min-w-0">
      <template x-if="$store.auth.ready && !$store.auth.signedIn">
        <div class="wa-card mx-auto max-w-[480px] text-center">
          <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
            @include('admin.partials.icon', ['name' => 'lock', 'class' => 'h-6 w-6'])
          </span>
          <h1 class="mt-lg text-h2 text-ink">Sign in to continue</h1>
          <p class="mt-xs text-bodysm text-ink-secondary">Your profile, applications and messages are here once you sign in with your mobile number.</p>
          @if ($anyRole)
          <button type="button" @click="Inthes.signIn()" class="wa-btn mt-xl w-full">Sign in</button>
          @else
          <button type="button" @click="Inthes.signIn('', { role: 'candidate', lock: true })" class="wa-btn mt-xl w-full">Sign in</button>
          @endif
        </div>
      </template>
      <template x-if="!$store.auth.ready">
        @include('site.ui.spinner')
      </template>
      @unless ($anyRole)
        {{-- An employer account here: these pages need a job seeker account.
             The switch dialog opens on its own; this card stays behind it. --}}
        <template x-if="$store.auth.ready && $store.auth.signedIn && !$store.auth.is('candidate')">
          <div class="wa-card mx-auto max-w-[480px] text-center" data-wrong-account>
            <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
              @include('admin.partials.icon', ['name' => 'user', 'class' => 'h-6 w-6'])
            </span>
            <h1 class="mt-lg text-h2 text-ink">This page is for job seeker accounts</h1>
            <p class="mt-xs text-bodysm text-ink-secondary">You're signed in with an employer account. Sign in again with your mobile number as a job seeker — it's a separate account.</p>
            <button type="button" @click="Inthes.openSwitch('candidate', location.pathname + location.search)" class="wa-btn mt-xl w-full">Continue as job seeker</button>
            <a href="{{ url('/employer') }}" class="wa-btn-quiet mt-sm w-full">Back to my employer dashboard</a>
          </div>
        </template>
      @endunless
      @if ($anyRole)
      <div x-show="$store.auth.ready && $store.auth.signedIn" x-cloak>
      @else
      <div x-show="$store.auth.ready && $store.auth.is('candidate')" x-cloak>
      @endif
        @yield('account')
      </div>
    </section>
  </div>
</div>
@endsection
