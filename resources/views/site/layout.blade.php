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
/*
 * Site-only surfaces and motion. The design system holds everything shared with
 * the admin panel; this is what a marketing page needs on top of it.
 *
 * One rule governs all of it: content is visible first and animated second.
 * `.reveal` is added by script (see site.js) and never written into the HTML, so
 * the hidden state can only exist on a page that is able to un-hide it.
 */

/* ── surfaces ──────────────────────────────────────────────────────────── */

/* The hero's ground: a red bloom from the top-left, fading out before the
   content starts so nothing sits on a gradient it has to fight. */
.hero-wash{background-image:radial-gradient(120% 120% at 8% 0%,rgba(235,4,1,0.10) 0%,rgba(235,4,1,0.04) 38%,transparent 68%)}

/* A second bloom from the opposite corner, so a wide hero has depth on both
   sides instead of one lit edge and one dead one. */
.hero-wash-2{background-image:radial-gradient(90% 90% at 100% 0%,rgba(235,4,1,0.07) 0%,transparent 60%)}

/* A dotted grid, barely there, so a large empty hero reads as designed. */
.dot-grid{background-image:radial-gradient(rgba(30,30,30,0.07) 1px,transparent 1px);background-size:22px 22px}

/* A soft red orb for the hero to layer over — the one purely decorative shape
   on the site, and the only place a blur this large is affordable. */
.orb{border-radius:9999px;filter:blur(72px);background:radial-gradient(circle,rgba(235,4,1,0.20) 0%,rgba(235,4,1,0.05) 55%,transparent 72%)}

/* Section rule that fades out of its heading rather than dividing the page. */
.red-rule{background-image:linear-gradient(90deg,#EB0401 0%,rgba(235,4,1,0.14) 55%,transparent 100%)}

/* The gradient text used once, on the hero's emphasis word. Used twice it stops
   being emphasis. */
.text-gradient{background-image:linear-gradient(96deg,#EB0401 0%,#FF5A4E 46%,#B80200 100%);-webkit-background-clip:text;background-clip:text;color:transparent}

/* A hairline gradient border, for the cards that should read as raised without
   a heavier shadow. Two backgrounds, clipped differently — the standard trick,
   and the only one that keeps the radius. */
.ring-gradient{background:linear-gradient(#fff,#fff) padding-box,linear-gradient(140deg,rgba(235,4,1,0.35),rgba(235,4,1,0.06) 45%,rgba(231,231,234,1)) border-box;border:1px solid transparent}

/* ── header ────────────────────────────────────────────────────────────── */

/* A sticky bar that looks identical at rest and mid-scroll gives no cue that
   anything is beneath it. */
[data-site-header]{transition:box-shadow 280ms cubic-bezier(0.22,1,0.36,1),background-color 280ms}
[data-site-header].is-scrolled{box-shadow:0 6px 24px -12px rgba(30,30,30,0.18)}

/* ── scroll reveal ─────────────────────────────────────────────────────── */

.reveal{opacity:0;transform:translateY(16px);will-change:opacity,transform}
.reveal.revealed{opacity:1;transform:none;transition:opacity 520ms cubic-bezier(0.22,1,0.36,1),transform 520ms cubic-bezier(0.22,1,0.36,1)}

/* Variants, for sections where a vertical rise is the wrong direction. */
.reveal[data-reveal="left"]{transform:translateX(-22px)}
.reveal[data-reveal="right"]{transform:translateX(22px)}
.reveal[data-reveal="scale"]{transform:scale(0.965)}

/* ── first paint ───────────────────────────────────────────────────────── */

/* Above-the-fold content animates on load rather than on scroll: it is already
   in view, so an observer would fire immediately anyway and the delay would
   just look like jank. */
.enter > *{animation:enter-up 560ms cubic-bezier(0.22,1,0.36,1) both}
.enter > *:nth-child(1){animation-delay:60ms}
.enter > *:nth-child(2){animation-delay:130ms}
.enter > *:nth-child(3){animation-delay:200ms}
.enter > *:nth-child(4){animation-delay:270ms}
.enter > *:nth-child(5){animation-delay:340ms}
.enter > *:nth-child(6){animation-delay:410ms}
@keyframes enter-up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

/* ── micro-interactions ────────────────────────────────────────────────── */

/* Card lift. Shadow and border move together — a card that lifts without its
   shadow following looks pasted on. */
.lift{transition:transform 240ms cubic-bezier(0.22,1,0.36,1),box-shadow 240ms,border-color 240ms}
.lift:hover{transform:translateY(-3px)}

/* A sheen that crosses a button once on hover. Pointer-events off, so it never
   swallows the click it decorates. */
.sheen{position:relative;overflow:hidden}
.sheen::after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(105deg,transparent 30%,rgba(255,255,255,0.28) 48%,transparent 66%);transform:translateX(-120%)}
.sheen:hover::after{transform:translateX(120%);transition:transform 720ms cubic-bezier(0.22,1,0.36,1)}

/* An arrow that leans forward when its row is hovered. */
.group:hover .nudge{transform:translateX(3px)}
.nudge{transition:transform 180ms cubic-bezier(0.22,1,0.36,1)}

/* A slow drift for the hero orbs, so a static hero is never completely still.
   20s and 6px — visible if you look, invisible if you don't. */
@keyframes float-slow{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-6px,0)}}
.float-slow{animation:float-slow 20s ease-in-out infinite}

/* ── reduced motion ────────────────────────────────────────────────────── */

/* Everything above decorates content that is already present, so all of it can
   go. `.reveal` is neutralised rather than merely un-transitioned: without the
   opacity reset an armed element would stay invisible forever. */
@media (prefers-reduced-motion: reduce){
  .enter > *{animation:none!important}
  .reveal,.reveal.revealed{opacity:1!important;transform:none!important;transition:none!important}
  .float-slow{animation:none!important}
  .sheen::after{display:none}
  .lift:hover{transform:none}
}

/* Print has no scrolling and therefore no intersections, so an armed element
   would come out as a blank block on paper. Everything is forced visible, and
   the decorative layers are dropped — nobody wants a page of red orbs. */
@media print{
  .reveal,.reveal.revealed{opacity:1!important;transform:none!important}
  .enter > *{animation:none!important}
  .orb,.dot-grid,.hero-wash,.hero-wash-2{display:none!important}
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
