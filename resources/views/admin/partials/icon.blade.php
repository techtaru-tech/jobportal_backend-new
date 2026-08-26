{{--
  One lucide glyph. `name` keys into resources/views/admin/icons.php, which is
  generated from the lucide-react package itself — see tool/gen-icons.mjs.

  The wrapper carries the attributes every lucide icon shares, so a call site
  only ever passes the name and the sizing classes.
--}}
@php
    $iconSet ??= require resource_path('views/admin/icons.php');
    $markup = $iconSet[$name] ?? null;
@endphp
@if ($markup)
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         class="{{ $class ?? 'h-[18px] w-[18px]' }}" aria-hidden="true">{!! $markup !!}</svg>
@endif
