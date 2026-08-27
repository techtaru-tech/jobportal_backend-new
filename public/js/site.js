/**
 * The public site's behaviour: the mobile drawer, the apply dialog, and the
 * motion system.
 *
 * These pages are server-rendered — the markup arrives finished, which is what
 * makes them indexable — so nothing here fetches. Contrast with `admin.js`,
 * which *is* the admin panel.
 *
 * The motion is deliberate about one thing above all: content is visible
 * first and animated second. Every revealed element is in the DOM and readable
 * with JavaScript off or broken; `.reveal` only becomes hidden once this script
 * has confirmed it can un-hide it again (see `armReveals`). A page that hides
 * its own content behind an observer that never fires is a blank page, and that
 * is a worse outcome than no animation at all.
 */
(function () {
  /**
   * Respect the OS setting, read once.
   *
   * Everything here decorates a state change that has already happened, so
   * removing it costs nothing — and vestibular disorders are not an edge case.
   */
  const reduceMotion =
    window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  /**
   * Reveals elements as they scroll into view.
   *
   * The class is added by *script*, not written into the HTML, so the hidden
   * state can only ever exist on a page where something is able to remove it.
   */
  function armReveals() {
    const targets = Array.from(document.querySelectorAll('[data-reveal]'))
    if (targets.length === 0) return

    if (reduceMotion || !('IntersectionObserver' in window)) {
      // Nothing to arm: the elements are already visible and stay that way.
      return
    }

    // Only hide what is actually below the fold. Anything already on screen has
    // nothing to reveal *into* — hiding it would mean a visitor who never
    // scrolls (or whose observer misbehaves) stares at gaps where the content
    // they came for should be.
    const viewportBottom = window.innerHeight
    const armed = targets.filter((el) => {
      const top = el.getBoundingClientRect().top
      if (top < viewportBottom * 0.92) return false
      el.classList.add('reveal')
      return true
    })

    if (armed.length === 0) return

    const reveal = (el) => {
      // Children of a [data-reveal-group] arrive in sequence rather than
      // together, so a row of cards reads as one movement.
      const delay = Number(el.dataset.revealDelay || 0)
      setTimeout(() => el.classList.add('revealed'), delay)
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return
          reveal(entry.target)
          // One-shot. Re-animating on every scroll past is what makes scroll
          // animation feel cheap.
          observer.unobserve(entry.target)
        })
      },
      // Fires a little before the element's top edge arrives, so the movement
      // finishes about when the reader's eye does.
      { rootMargin: '0px 0px -12% 0px', threshold: 0.01 },
    )

    armed.forEach((el) => observer.observe(el))

    /*
     * The failsafe, and the reason this is not just an observer.
     *
     * Hidden-until-observed content is content that can get stuck hidden: a
     * mis-sized frame, a zero-height container, an observer that never fires
     * for reasons peculiar to one browser. Any of those turn a section into a
     * blank space with no error anywhere. After a few seconds, anything still
     * waiting is shown regardless — a missed animation is a far smaller cost
     * than missing content.
     */
    setTimeout(() => {
      armed.forEach((el) => {
        if (!el.classList.contains('revealed')) el.classList.add('revealed')
      })
      observer.disconnect()
    }, 4000)
  }

  /** Staggers the children of each [data-reveal-group]. */
  function assignStaggerDelays() {
    document.querySelectorAll('[data-reveal-group]').forEach((group) => {
      const step = Number(group.dataset.revealGroup) || 70
      Array.from(group.children).forEach((child, i) => {
        if (child.hasAttribute('data-reveal')) {
          // Capped: past about half a second a late card reads as a bug.
          child.dataset.revealDelay = String(Math.min(i * step, 420))
        }
      })
    })
  }

  /**
   * Counts a number up when it first appears.
   *
   * The final value is what is already in the HTML, so a reader with no
   * JavaScript sees the real figure rather than a zero that never moves.
   */
  function armCounters() {
    const counters = Array.from(document.querySelectorAll('[data-count-to]'))
    if (counters.length === 0) return

    if (reduceMotion || !('IntersectionObserver' in window)) return

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return
          observer.unobserve(entry.target)
          runCount(entry.target)
        })
      },
      { threshold: 0.4 },
    )

    counters.forEach((el) => observer.observe(el))
  }

  function runCount(el) {
    const target = Number(el.dataset.countTo)
    if (!Number.isFinite(target) || target <= 0) return

    const duration = 900
    const start = performance.now()
    // Cheap thousands separator — matches the server's en-IN formatting for
    // the sizes these counters actually reach.
    const format = (n) => n.toLocaleString('en-IN')

    function frame(now) {
      const progress = Math.min((now - start) / duration, 1)
      // Decelerating, so it settles rather than stopping dead.
      const eased = 1 - Math.pow(1 - progress, 3)
      el.textContent = format(Math.round(target * eased))
      if (progress < 1) requestAnimationFrame(frame)
    }

    requestAnimationFrame(frame)
  }

  /**
   * Parallax on elements marked [data-parallax], driven by scroll position.
   *
   * Transform only — never `top` or `background-position` — so it stays on the
   * compositor and does not force layout on every frame. Reads are batched into
   * a rAF for the same reason.
   */
  function armParallax() {
    const layers = Array.from(document.querySelectorAll('[data-parallax]'))
    if (layers.length === 0 || reduceMotion) return

    let queued = false

    function apply() {
      queued = false
      const y = window.scrollY
      layers.forEach((el) => {
        const rate = Number(el.dataset.parallax) || 0.12
        el.style.transform = `translate3d(0, ${(y * rate).toFixed(1)}px, 0)`
      })
    }

    window.addEventListener(
      'scroll',
      () => {
        if (queued) return
        queued = true
        requestAnimationFrame(apply)
      },
      { passive: true },
    )

    apply()
  }

  /**
   * Adds a shadow to the header once the page has moved.
   *
   * A sticky bar that looks identical at the top and mid-scroll gives no cue
   * that anything is beneath it.
   */
  function armHeaderShadow() {
    const header = document.querySelector('[data-site-header]')
    if (!header) return

    let queued = false
    function apply() {
      queued = false
      header.classList.toggle('is-scrolled', window.scrollY > 8)
    }

    window.addEventListener(
      'scroll',
      () => {
        if (queued) return
        queued = true
        requestAnimationFrame(apply)
      },
      { passive: true },
    )

    apply()
  }

  function boot() {
    assignStaggerDelays()
    armReveals()
    armCounters()
    armParallax()
    armHeaderShadow()
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot)
  } else {
    boot()
  }

  // ── Alpine component ──────────────────────────────────────────────────
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
        this.menuOpen = false
        // The page behind a modal must not scroll under it.
        document.body.style.overflow = 'hidden'
      },

      closeApply() {
        this.applyOpen = false
        document.body.style.overflow = ''
      },

      /**
       * Submits the filter form.
       *
       * Filters live in the query string, not in component state, so a filtered
       * list can be linked, shared and indexed. That means changing a select
       * has to navigate — and doing it on `change` is one interaction instead
       * of two on a form where every field is a single choice.
       */
      submitFilters(event) {
        const form = event.target.closest('form')
        if (form) form.submit()
      },

      /**
       * Clears one filter without touching the others.
       *
       * Reads the URL rather than the form, so removing a chip cannot also
       * submit a half-changed select the visitor was still thinking about.
       */
      clearFilter(name) {
        const url = new URL(window.location.href)
        url.searchParams.delete(name)
        // Dropping a filter changes what page 1 means, so paging resets.
        url.searchParams.delete('page')
        window.location.href = url.toString()
      },

      /** Copy-link buttons report success in place, with no toast machinery. */
      async copyLink(url, event) {
        const button = event.currentTarget
        const original = button.dataset.label || button.textContent.trim()
        button.dataset.label = original
        try {
          await navigator.clipboard.writeText(url)
          button.textContent = 'Link copied'
        } catch (e) {
          button.textContent = 'Press Ctrl+C'
        }
        setTimeout(() => { button.textContent = original }, 1800)
      },
    }
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('siteApp', siteApp)
  })
})()
