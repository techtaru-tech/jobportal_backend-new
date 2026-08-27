<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon-inthes.svg') }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
{{-- Light-only, like the app and the panel. Saying so keeps a dark-mode OS
     from tinting native controls and overscroll gutters. --}}
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#EB0401">

<title>@yield('title', 'Inthes — Paramedical & healthcare jobs')</title>
<meta name="description" content="@yield('description', 'Nursing, lab, pharmacy and allied healthcare jobs across India. Browse verified employers and apply from the Inthes app.')">

{{-- Canonical on every page. A job board collects query-string variants of the
     same list (?sort=, ?page=), and without this each one competes with the
     others for the same ranking. --}}
<link rel="canonical" href="@yield('canonical', url()->current())">

{{-- Open Graph, because these links get pasted into WhatsApp far more than
     they get typed. Job pages override all three. --}}
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="@yield('og_title', 'Inthes — Paramedical & healthcare jobs')">
<meta property="og:description" content="@yield('og_description', 'Nursing, lab, pharmacy and allied healthcare jobs across India.')">
<meta property="og:image" content="{{ asset('brand/inthes-mark.png') }}">
<meta name="twitter:card" content="summary">

@include('partials.design-system')

<style>
/* Site-only flourishes. The design system holds everything shared with the
   admin panel; these are the two effects only a marketing page needs. */

/* The hero's ground: a red bloom from the top-left, fading out well before the
   content starts so nothing sits on a gradient it has to fight. */
.hero-wash{background-image:radial-gradient(120% 120% at 8% 0%,rgba(235,4,1,0.10) 0%,rgba(235,4,1,0.04) 38%,transparent 68%)}

/* A dotted grid, barely there, so a large empty hero reads as designed rather
   than unfinished. */
.dot-grid{background-image:radial-gradient(rgba(30,30,30,0.07) 1px,transparent 1px);background-size:22px 22px}

/* Entrances are staggered by depth down the page, so a section arrives as one
   movement instead of eight things starting at once. */
.stagger > *{animation:enter-up 420ms cubic-bezier(0.22,1,0.36,1) both}
.stagger > *:nth-child(1){animation-delay:40ms}
.stagger > *:nth-child(2){animation-delay:90ms}
.stagger > *:nth-child(3){animation-delay:140ms}
.stagger > *:nth-child(4){animation-delay:190ms}
.stagger > *:nth-child(5){animation-delay:240ms}
.stagger > *:nth-child(6){animation-delay:290ms}
@keyframes enter-up{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* Honour the OS setting — every animation here is decoration over content that
   is already present. */
@media (prefers-reduced-motion: reduce){
  .stagger > *{animation:none!important}
}
</style>

@stack('head')
</head>
<body class="bg-canvas text-ink" x-data="siteApp()">

@include('site.partials.header')

<main>
    @yield('content')
</main>

@include('site.partials.footer')
@include('site.partials.apply-dialog')

{{-- Registers `siteApp`, then Alpine — order matters, see partials/alpine. --}}
<script defer src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}"></script>
@include('partials.alpine')

@stack('scripts')
</body>
</html>
