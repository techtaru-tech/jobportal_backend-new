<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon-inthes.svg') }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#EB0401">

<title>Inthes — Healthcare jobs</title>
{{-- Behind a sign-in for most of it, and the public pages already carry the
     indexable copy of every job. --}}
<meta name="robots" content="noindex, nofollow">

@include('partials.design-system')

<style>
[x-cloak]{display:none!important}
.hero-wash{background-image:radial-gradient(120% 120% at 8% 0%,rgba(235,4,1,0.10) 0%,rgba(235,4,1,0.04) 38%,transparent 68%)}
.dot-grid{background-image:radial-gradient(rgba(30,30,30,0.07) 1px,transparent 1px);background-size:22px 22px}
.no-scrollbar::-webkit-scrollbar{display:none}.no-scrollbar{scrollbar-width:none}
.pb-safe{padding-bottom:calc(env(safe-area-inset-bottom) + 72px)}
@media (min-width:1024px){.pb-safe{padding-bottom:48px}}
</style>

{{-- Shared component classes for every screen. Tailwind's play build reads
     `text/tailwindcss` blocks, so these resolve against the same tokens. --}}
<style type="text/tailwindcss">
@layer components {
  .wa-page { @apply mx-auto w-full max-w-[1200px] px-page py-xl lg:px-xl; }
  .wa-narrow { @apply mx-auto w-full max-w-[720px] px-page py-xl; }
  .wa-card { @apply rounded-card border-hair border-hairline bg-surface p-xl shadow-card; }
  .wa-btn { @apply inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-primary px-xl text-btn font-semibold text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-hairline disabled:text-ink-muted disabled:shadow-none; }
  .wa-btn-sm { @apply inline-flex h-10 items-center justify-center gap-sm rounded-button bg-primary px-lg text-btnghost font-semibold text-ink-onPrimary shadow-button transition-colors duration-micro hover:bg-primary-dark disabled:bg-hairline disabled:shadow-none; }
  .wa-btn-ghost { @apply inline-flex h-[50px] items-center justify-center gap-sm rounded-button border-btn border-primary bg-surface px-xl text-btn font-semibold text-primary transition-colors duration-micro hover:bg-primary-light disabled:border-hairline disabled:text-ink-muted; }
  .wa-btn-ghost-sm { @apply inline-flex h-10 items-center justify-center gap-sm rounded-button border-btn border-primary bg-surface px-lg text-btnghost font-semibold text-primary transition-colors duration-micro hover:bg-primary-light disabled:border-hairline disabled:text-ink-muted; }
  .wa-btn-quiet { @apply inline-flex h-10 items-center justify-center gap-sm rounded-button px-md text-btnghost font-semibold text-ink-secondary transition-colors duration-micro hover:bg-surface-muted hover:text-ink; }
  .wa-label { @apply mb-[5px] block text-label text-ink-secondary; }
  .wa-input { @apply h-[50px] w-full rounded-field border-hair border-hairline bg-surface-muted px-md text-input text-ink outline-none transition-[border-color,box-shadow] duration-micro placeholder:text-ink-muted focus:border-primary focus:shadow-glow; }
  .wa-textarea { @apply w-full rounded-field border-hair border-hairline bg-surface-muted px-md py-md text-input text-ink outline-none transition-[border-color,box-shadow] duration-micro placeholder:text-ink-muted focus:border-primary focus:shadow-glow; }
  .wa-chip { @apply inline-flex items-center gap-xs rounded-chip border-chip border-transparent bg-surface-muted px-lg py-sm text-chip text-ink transition-colors duration-150 hover:bg-hairline; }
  .wa-chip-on { @apply border-primary bg-primary-light font-semibold text-primary-dark hover:bg-primary-light; }
  .wa-tag { @apply inline-flex items-center gap-xs rounded-field bg-surface-muted px-sm py-[3px] text-tag text-ink-secondary; }
  .wa-pill { @apply inline-flex items-center rounded-full px-sm py-[2px] text-[11.5px] font-semibold; }
  .wa-error { @apply mt-xs text-caption text-danger; }
  .wa-section-title { @apply text-kicker uppercase text-ink-secondary; }
}
</style>

<script>
window.WEBAPP_CONFIG = {
  storeUrl: @json(\App\Support\StoreQr::storeUrl()),
  siteUrl: @json(route('site.home')),
};
</script>

{{-- core, then each feature part, then boot (registers the component), then
     Alpine. Deferred scripts run in document order. --}}
@foreach (['core', 'jobs', 'profile', 'recruiter', 'extras', 'boot'] as $script)
<script defer src="{{ asset("js/webapp/{$script}.js") }}?v={{ filemtime(public_path("js/webapp/{$script}.js")) }}"></script>
@endforeach
@include('partials.alpine')
</head>
<body class="bg-canvas-alt text-ink antialiased" x-data="webApp()" x-cloak>

{{-- Restoring a session: a spinner, since there is no shape to mimic yet. --}}
<template x-if="booting">
  <div class="flex min-h-screen flex-col items-center justify-center gap-md">
    @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-6 w-6 animate-spin text-primary'])
  </div>
</template>

<template x-if="!booting">
  <div class="min-h-screen">
    @include('webapp.partials.header')

    <main :class="showChrome ? 'pb-safe' : ''">
      @include('webapp.screens.auth')
      @include('webapp.screens.jobs')
      @include('webapp.screens.applications')
      @include('webapp.screens.profile')
      @include('webapp.screens.onboarding')
      @include('webapp.screens.recruiter')
      @include('webapp.screens.extras')
    </main>

    @include('webapp.partials.bottom-nav')
  </div>
</template>

@include('webapp.partials.overlays')

</body>
</html>
