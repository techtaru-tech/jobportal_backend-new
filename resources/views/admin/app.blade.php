<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon-inthes.svg') }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
{{-- Light-only, like the app. Saying so keeps a dark-mode OS from tinting
     native controls and overscroll gutters. --}}
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#FFFFFF">
<title>Inthes Admin</title>

@include('partials.design-system')

{{-- Registers `adminApp`, then Alpine — see partials/alpine.blade.php. --}}
<script defer src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
@include('partials.alpine')
</head>
<body x-data="adminApp()" x-init="init()" x-cloak>

{{--
  The blank-page guard. Everything else here is inside a `<template>` and so
  invisible until Alpine boots, which made a boot failure look identical to a
  working page that happened to be empty. `admin.js` clears the timer once it
  starts; if it never does, this says so instead of nothing.
--}}
<div id="boot-error">
  <strong>The admin panel could not start.</strong>
  <p style="margin-top:.5rem">The page loaded but its JavaScript did not run, so nothing below could render.</p>
  <p style="margin-top:.5rem">Open the browser console (F12) for the underlying error, then check that
    <code>/js/admin.js</code>, <code>/js/alpine.min.js</code> and <code>/js/tailwind.js</code> all return 200.</p>
</div>
<script>
  // Plain script, not deferred: armed before the deferred bundles run so it
  // can still fire when one of them fails to.
  window.__adminBootTimer = setTimeout(function () {
    var el = document.getElementById('boot-error');
    if (el) { el.style.display = 'block'; document.body.removeAttribute('x-cloak'); }
  }, 5000);
</script>

@include('admin.partials.login')

<template x-if="admin">
  <div class="min-h-screen bg-canvas-alt">
    @include('admin.partials.sidebar')

    <div :class="railed ? 'lg:pl-[72px]' : 'lg:pl-[248px]'" class="transition-[padding] duration-component ease-out">
      @include('admin.partials.topbar')

      <main>
        {{-- min-w-0 so a wide table's scroller is what scrolls, not the page. --}}
        <div class="mx-auto min-w-0 max-w-[1440px] p-page lg:px-xl lg:py-lg">
          @include('admin.pages.dashboard')
          @include('admin.pages.users')
          @include('admin.pages.jobs')
          @include('admin.pages.applications')
          @include('admin.pages.organisations')
          @include('admin.pages.subscriptions')
          @include('admin.pages.option-lists')
          @include('admin.pages.content')
          @include('admin.pages.notifications')
        </div>
      </main>
    </div>

    @include('admin.partials.toast')
  </div>
</template>

</body>
</html>
