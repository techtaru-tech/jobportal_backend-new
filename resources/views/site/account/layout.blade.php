{{--
  The job seeker's account area: one site header, a left menu on desktop
  (a scrolling tab row on phones) and the page's content.

  Pages set `@section('account_key', 'applications')` to light their menu
  item, and put their markup in `@section('account')`. Signed-out visitors
  get a sign-in card instead of the content — every page here is about
  their own data. Page scripts go in `@push('page-scripts')`.
--}}
@extends('site.layout')

@section('requires_auth', '1')
@section('body_class', 'bg-canvas-alt')
@section('hide_footer', '1')

@section('content')
@php
  $key = trim($__env->yieldContent('account_key', 'profile'));
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
<div class="mx-auto w-full max-w-[1200px] px-page py-xl lg:px-xl">
  <div class="lg:grid lg:grid-cols-[220px_1fr] lg:gap-xxl">
    {{-- Menu --}}
    <aside class="mb-lg lg:mb-0">
      <nav class="no-scrollbar -mx-page flex gap-xs overflow-x-auto px-page lg:sticky lg:top-[92px] lg:mx-0 lg:flex-col lg:gap-[2px] lg:overflow-visible lg:px-0">
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
          <a href="{{ url('/employer') }}" class="flex items-center gap-md rounded-field px-md py-[9px] text-bodysm text-ink-secondary hover:bg-surface hover:text-ink">
            @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-[17px] w-[17px]'])Hiring? Employer dashboard
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
          <button type="button" @click="signIn()" class="wa-btn mt-xl w-full">Sign in</button>
        </div>
      </template>
      <template x-if="!$store.auth.ready">
        @include('site.ui.spinner')
      </template>
      <div x-show="$store.auth.ready && $store.auth.signedIn" x-cloak>
        @yield('account')
      </div>
    </section>
  </div>
</div>
@endsection
