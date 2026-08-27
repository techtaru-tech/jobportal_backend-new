{{--
  Alpine, loaded after whatever registered this page's component.

  Order is the whole point and has bitten this codebase once already: both are
  deferred, deferred scripts run in document order, and Alpine fires
  `alpine:init` as it executes. A component script placed *after* Alpine
  therefore registers too late — `x-data` resolves to nothing, and because the
  admin panel keeps every screen inside a `<template>`, that rendered a
  completely blank page with no error.

  So: include the component script, then include this.
--}}
<script defer src="{{ asset('js/alpine.min.js') }}"></script>
