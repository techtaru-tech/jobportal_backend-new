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

<script src="{{ asset('js/tailwind.js') }}"></script>
<script>
/*
 * The design tokens, lifted from admin_panel/tailwind.config.js so this panel
 * and the React one are visibly the same product. Those in turn come from the
 * Flutter app:
 *   app_colors.dart      -> colors
 *   app_dimens.dart      -> spacing / borderRadius / boxShadow
 *   app_text_styles.dart -> fontSize
 *
 * Light-only on purpose: the app pins `themeMode: ThemeMode.light` and defines
 * no dark palette, so there is nothing to mirror and `dark:` is never used.
 */
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#EB0401',
          dark: '#B80200',
          light: '#FDEBEA',
          /* Border for a `primary-light` fill, where a full-strength red edge would shout. */
          line: '#F7CFCD',
        },
        canvas: { DEFAULT: '#FFFFFF', alt: '#F7F7F8' },
        surface: { DEFAULT: '#FFFFFF', muted: '#F3F3F4', dark: '#1C1C1E' },
        ink: {
          DEFAULT: '#1E1E1E',
          secondary: '#6B6B70',
          muted: '#9A9A9E',
          onPrimary: '#FFFFFF',
        },
        hairline: { DEFAULT: '#E7E7EA', strong: '#D8D8DC', divider: '#EDEDEF' },
        /* Status colours, each with a tint pale enough to fill a banner or a
           pill without competing with the text on top of it. */
        success: { DEFAULT: '#1E9E5A', bg: '#E7F8EF' },
        warning: { DEFAULT: '#B8790A', bg: '#FDF4E3' },
        info: { DEFAULT: '#2563A6', bg: '#EAF2FA' },
        danger: { DEFAULT: '#C81E1E', bg: '#FDECEC' },
        shimmer: { base: '#ECECEE', highlight: '#F7F7F8' },
      },

      /* Gap.* — 8px based, plus the odd literals the app uses in components. */
      spacing: {
        xs: '4px', sm: '8px', md: '12px', lg: '16px',
        xl: '20px', xxl: '24px', xxxl: '32px', page: '16px',
      },

      /* Radii.* — nothing in this system has a square corner. */
      borderRadius: {
        button: '12px', field: '12px', card: '14px', image: '14px',
        logo: '9.8px', dialog: '18px', sheet: '22px', chip: '22px',
      },

      borderWidth: { hair: '1px', chip: '1.2px', btn: '1.4px', focus: '1.6px' },

      boxShadow: {
        card: '0 2px 10px rgba(30,30,30,0.06)',
        button: '0 6px 16px rgba(235,4,1,0.24)',
        raised: '0 12px 32px -8px rgba(30,30,30,0.18), 0 2px 8px rgba(30,30,30,0.06)',
        glow: '0 0 0 3px rgba(235,4,1,0.12)',
      },

      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
      },

      /* AppTextStyles — [size, { lineHeight, letterSpacing, fontWeight }].
         Only 400/500/600/700 exist as real font files; never synthesize others. */
      fontSize: {
        h1: ['26px', { lineHeight: '1.2', letterSpacing: '-0.3px', fontWeight: '700' }],
        h2: ['22px', { lineHeight: '1.25', letterSpacing: '-0.2px', fontWeight: '700' }],
        h3: ['18px', { lineHeight: '1.3', fontWeight: '600' }],
        h4: ['16px', { lineHeight: '1.3', fontWeight: '600' }],
        h5: ['15px', { lineHeight: '1.3', fontWeight: '600' }],
        kicker: ['11px', { letterSpacing: '0.6px', fontWeight: '600' }],
        body: ['14.5px', { lineHeight: '1.5', fontWeight: '400' }],
        bodysm: ['13px', { lineHeight: '1.45', fontWeight: '400' }],
        caption: ['11.5px', { fontWeight: '500' }],
        btn: ['15px', { lineHeight: '1', fontWeight: '600' }],
        btnghost: ['13.5px', { lineHeight: '1', fontWeight: '600' }],
        label: ['13px', { fontWeight: '500' }],
        input: ['14.5px', { fontWeight: '400' }],
        tag: ['12px', { fontWeight: '500' }],
        chip: ['13px', { fontWeight: '500' }],
        /* Tabular figures for stat cards — digits must not shift width as they tick. */
        stat: ['26px', { lineHeight: '1.1', letterSpacing: '-0.4px', fontWeight: '700' }],
      },

      /* The more of the screen a thing moves, the longer it may take.
         Micro 150–250ms, components 250–400ms, pages 300–500ms. */
      transitionDuration: { micro: '180ms', component: '280ms', page: '360ms' },
      transitionTimingFunction: { out: 'cubic-bezier(0.22, 1, 0.36, 1)' },

      keyframes: {
        shimmer: { '0%': { backgroundPosition: '200% 0' }, '100%': { backgroundPosition: '-200% 0' } },
        'fade-in': { from: { opacity: '0' }, to: { opacity: '1' } },
        'slide-up': { from: { opacity: '0', transform: 'translateY(6px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
        'enter-up': { from: { opacity: '0', transform: 'translateY(10px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
        'pop-in': { from: { opacity: '0', transform: 'translateY(-4px) scale(0.98)' }, to: { opacity: '1', transform: 'translateY(0) scale(1)' } },
        'pulse-ring': {
          '0%': { boxShadow: '0 0 0 0 rgba(235,4,1,0.45)' },
          '70%': { boxShadow: '0 0 0 6px rgba(235,4,1,0)' },
          '100%': { boxShadow: '0 0 0 0 rgba(235,4,1,0)' },
        },
        'grow-x': { from: { transform: 'scaleX(0)' }, to: { transform: 'scaleX(1)' } },
      },
      animation: {
        shimmer: 'shimmer 1.2s linear infinite',
        'fade-in': 'fade-in 150ms ease-out',
        'slide-up': 'slide-up 180ms ease-out',
        'enter-up': 'enter-up 360ms cubic-bezier(0.22, 1, 0.36, 1)',
        'pop-in': 'pop-in 180ms cubic-bezier(0.22, 1, 0.36, 1)',
        'pulse-ring': 'pulse-ring 2s ease-out infinite',
        'grow-x': 'grow-x 520ms cubic-bezier(0.22, 1, 0.36, 1)',
      },
    },
  },
}
</script>

{{--
  Script order matters and is why these are not swapped: `admin.js` registers
  the Alpine component on `alpine:init`, so it must execute before Alpine does.
  Deferred scripts run in document order, so Alpine coming second is what makes
  the registration land in time. Alpine first meant `adminApp is not defined`,
  a failed `x-data`, and — since every screen lives inside a `<template>`,
  which a browser renders as nothing on its own — a completely blank page.

  All three are served locally: without them there is no page at all, so it is
  not something to leave depending on a CDN.
--}}
{{--
  The lucide glyph set, shared with the Blade partials so both draw from one
  generated source rather than two drifting copies.

  Each entry is a **complete** `<svg>` element, not the bare inner markup the
  generated file holds. `x-html` assigns through `innerHTML` on an HTML parent,
  where a lone `<rect>` or `<path>` is parsed into the HTML namespace as an
  unknown element and draws nothing at all — the wrapper is what puts the
  children in the SVG namespace. Sizing still comes from the call site, which
  targets the child with `[&>svg]:h-…`.
--}}
@php
    $iconSvgs = collect(require resource_path('views/admin/icons.php'))
        ->map(fn (string $inner) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"'
            .' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
            .' stroke-linejoin="round" aria-hidden="true">'.$inner.'</svg>');
@endphp
<script>window.ICONS = @json($iconSvgs);</script>
<script defer src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
<script defer src="{{ asset('js/alpine.min.js') }}"></script>

<style>
/* The app's own font files, copied from new_job_portal/assets/fonts so the
   panel renders in the same typeface with no network dependency. Only these
   four weights exist — 300/800/900 would be synthesized and look wrong. */
@font-face{font-family:'Plus Jakarta Sans';src:url('{{ asset('fonts/PlusJakartaSans-Regular.ttf') }}') format('truetype');font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:'Plus Jakarta Sans';src:url('{{ asset('fonts/PlusJakartaSans-Medium.ttf') }}') format('truetype');font-weight:500;font-style:normal;font-display:swap}
@font-face{font-family:'Plus Jakarta Sans';src:url('{{ asset('fonts/PlusJakartaSans-SemiBold.ttf') }}') format('truetype');font-weight:600;font-style:normal;font-display:swap}
@font-face{font-family:'Plus Jakarta Sans';src:url('{{ asset('fonts/PlusJakartaSans-Bold.ttf') }}') format('truetype');font-weight:700;font-style:normal;font-display:swap}

:root{color-scheme:light}
html{-webkit-text-size-adjust:100%}
body{background:#FFFFFF;color:#1E1E1E;font-family:'Plus Jakarta Sans',system-ui,-apple-system,'Segoe UI',sans-serif;font-size:14.5px;line-height:1.5;-webkit-font-smoothing:antialiased}
[x-cloak]{display:none!important}

*:focus-visible{outline:none;box-shadow:0 0 0 2px #FFFFFF,0 0 0 4px rgba(235,4,1,0.4)}

/* Chrome/Edge number inputs: the spinners break the field's 12px radius. */
input[type='number']::-webkit-outer-spin-button,
input[type='number']::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}

/* Chrome paints autofilled fields a pale yellow of its own choosing, which is
   the one colour in the browser this palette can't override any other way —
   an inset shadow the size of the field is the only cover, and the absurd
   delay keeps it from being repainted over. */
input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus{
  -webkit-text-fill-color:#1E1E1E;-webkit-box-shadow:0 0 0 1000px #F3F3F4 inset;
  transition:background-color 9999s ease-out 0s}

::selection{background-color:#FDEBEA;color:#B80200}

/* Shimmer.dart's gradient sweep — pair with `animate-shimmer`. */
.shimmer-fill{background-image:linear-gradient(90deg,#ECECEE 35%,#F7F7F8 50%,#ECECEE 65%);background-size:200% 100%}

/* Horizontal scrollers (chip rows, wide tables) without a visible bar. */
.no-scrollbar{scrollbar-width:none;-ms-overflow-style:none}
.no-scrollbar::-webkit-scrollbar{display:none}

/* Thin scrollbar for the sidebar and long panels. */
.thin-scrollbar{scrollbar-width:thin;scrollbar-color:#D8D8DC transparent}
.thin-scrollbar::-webkit-scrollbar{width:8px;height:8px}
.thin-scrollbar::-webkit-scrollbar-thumb{background-color:#D8D8DC;border-radius:999px}
.thin-scrollbar::-webkit-scrollbar-thumb:hover{background-color:#9A9A9E}
.thin-scrollbar::-webkit-scrollbar-track{background:transparent}

/* The system's only gradient, and it is barely one: a whisper of the brand
   tint bleeding from a corner. Used behind an empty state's glyph, where
   there is nothing else on screen to compete with it. */
.red-wash{background-image:radial-gradient(120% 100% at 0% 0%,rgba(235,4,1,0.07) 0%,rgba(235,4,1,0.02) 45%,transparent 72%)}

/* A 1px red rule that fades out — sits beside a section heading, where a
   full-width border would divide the page instead of labelling it. */
.red-rule{background-image:linear-gradient(90deg,#EB0401 0%,rgba(235,4,1,0.14) 55%,transparent 100%)}

/* Vertical counterpart to `grow-x`, for bars that rise from the axis. */
@keyframes grow-y{from{transform:scaleY(0)}to{transform:scaleY(1)}}

#boot-error{display:none;max-width:34rem;margin:4rem auto;padding:1.5rem;border:1px solid #F7CFCD;background:#FDECEC;border-radius:14px;color:#B80200;font-size:13px;line-height:1.6}

/* Honour the OS setting. Every animation in this theme is decoration over a
   state change that has already happened, so removing them costs nothing. */
@media (prefers-reduced-motion: reduce){
  *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
}
</style>
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
          @include('admin.pages.alerts')
        </div>
      </main>
    </div>

    @include('admin.partials.toast')
  </div>
</template>

</body>
</html>
