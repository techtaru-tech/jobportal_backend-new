/**
 * Registers the merged `webApp` component. Loaded after core.js and every
 * feature part, and before Alpine — see partials/alpine.blade.php for why the
 * order matters.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('webApp', () => window.WebApp.build())
})
