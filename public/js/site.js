/**
 * The public site's Alpine component.
 *
 * Deliberately small. These pages are server-rendered — the markup arrives
 * finished, which is what makes them indexable — so this only handles the
 * things HTML cannot do on its own: the mobile drawer, the apply dialog, and
 * the filter form's submit-on-change.
 *
 * Contrast with `admin.js`, which *is* the admin panel. Nothing here fetches.
 */
(function () {
  function siteApp() {
    return {
      ICONS: window.ICONS || {},

      /** Mobile nav drawer. */
      menuOpen: false,

      /** The apply dialog, and which posting opened it. */
      applyOpen: false,
      applyJob: '',

      /**
       * Opens the "apply in the app" dialog.
       *
       * Takes the job title so the dialog can name what the visitor was
       * reading. A dialog that says only "download the app" reads like an
       * advert; one that says "applying for Staff Nurse happens in the app"
       * reads like an answer to what they just tried to do.
       */
      openApply(title) {
        this.applyJob = title || ''
        this.applyOpen = true
        // The drawer and the dialog would otherwise stack on a phone.
        this.menuOpen = false
      },

      /**
       * Submits the filter form.
       *
       * Filters live in the query string, not in component state, so a
       * filtered list can be linked, shared and indexed. That means changing a
       * select has to navigate — and doing it on `change` rather than behind an
       * "Apply filters" button is one interaction instead of two, on a form
       * where every field is a single choice.
       */
      submitFilters(event) {
        const form = event.target.closest('form')
        if (form) form.submit()
      },

      /**
       * Clears one filter without touching the others.
       *
       * Reads the current URL rather than the form, so removing a chip cannot
       * accidentally also submit a half-changed select the visitor was still
       * thinking about.
       */
      clearFilter(name) {
        const url = new URL(window.location.href)
        url.searchParams.delete(name)
        // Dropping a filter changes what page 1 even means, so paging resets.
        url.searchParams.delete('page')
        window.location.href = url.toString()
      },
    }
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('siteApp', siteApp)
  })
})()
