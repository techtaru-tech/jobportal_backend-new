<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon-inthes.svg') }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#EB0401">

<title>Employer — Inthes</title>
{{-- Behind a sign-in: nothing here for a crawler to index, and nothing it
     should try to. --}}
<meta name="robots" content="noindex, nofollow">

@include('partials.design-system')

<style>
/* Borrowed from the site layout — the sign-in screen is the one page in this
   area a stranger sees, and it should look like the front door it is. */
.hero-wash{background-image:radial-gradient(120% 120% at 8% 0%,rgba(235,4,1,0.10) 0%,rgba(235,4,1,0.04) 38%,transparent 68%)}
.dot-grid{background-image:radial-gradient(rgba(30,30,30,0.07) 1px,transparent 1px);background-size:22px 22px}
</style>

{{-- Registers `employerApp`, then Alpine — order matters, see partials/alpine. --}}
<script defer src="{{ asset('js/employer.js') }}?v={{ filemtime(public_path('js/employer.js')) }}"></script>
@include('partials.alpine')
</head>
<body class="bg-canvas-alt text-ink" x-data="employerApp()" x-init="init()" x-cloak>

{{--
  The employer area.

  Client-rendered from `/api/v1/recruiter/*`, unlike the public site: nothing
  here needs indexing, and those endpoints already own every rule that matters —
  ownership, the plan's active-posting ceiling, and the approval queue every new
  or edited posting has to pass through. This is a UI over them, not a second
  implementation of them.

  Sign-in is phone + OTP, the same credential the app uses. One account, one
  identity, whichever surface it is used from.
--}}

@include('site.partials.employer-signin')

<template x-if="user">
  <div class="min-h-screen">
    @include('site.partials.employer-header')

    <main class="mx-auto max-w-[1200px] px-page py-xl lg:px-xl">
      @include('site.partials.employer-jobs')
      @include('site.partials.employer-post')
      @include('site.partials.employer-applicants')
    </main>
  </div>
</template>

{{-- Toast, same shape as the admin panel's. --}}
<div x-show="toast" x-cloak
     x-transition:enter="transition duration-component ease-out"
     x-transition:enter-start="opacity-0 translate-y-[6px]"
     x-transition:leave="transition duration-micro" x-transition:leave-end="opacity-0"
     class="fixed bottom-xl right-xl z-[60] max-w-[380px]" role="status" aria-live="polite">
  <div :class="toastError ? 'border-danger/30 bg-danger-bg text-danger' : 'border-success/30 bg-success-bg text-success'"
       class="flex items-start gap-md rounded-card border-hair px-lg py-md shadow-raised">
    <span x-html="toastError ? ICONS.alert : ICONS.check" class="[&>svg]:h-[18px] [&>svg]:w-[18px] mt-[1px] shrink-0"></span>
    <p class="text-bodysm font-medium" x-text="toast"></p>
  </div>
</div>

</body>
</html>
