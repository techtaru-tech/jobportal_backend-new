/**
 * "Inthes isn't in your area yet."
 *
 * A visitor opening the portal (or a shared link to it) from a state with no
 * jobs is told so, once — and can still browse and apply to the states that
 * do have them (relocation is a reason people look). No list of launched
 * states to maintain: a state is open while it has live jobs
 * (GET /jobs/coverage).
 *
 * Where they are comes from the browser's location, asked once a little
 * after the page settles. The answer is remembered for a week, a refusal for
 * a month, and a dismissed message stays dismissed for that state — so the
 * permission prompt and the message are each seen once, not on every page.
 *
 * Not for an employer account: hiring is not limited to where they sit.
 */
;(() => {
  const KEY = 'inthes.coverage'
  const DAY = 24 * 60 * 60 * 1000
  const STATE_TTL = 7 * DAY
  const DENIED_TTL = 30 * DAY

  const read = () => { try { return JSON.parse(window.Inthes.store.get(KEY) || '{}') } catch (e) { return {} } }
  const write = (v) => window.Inthes.store.set(KEY, JSON.stringify(v))
  // "Jammu & Kashmir" / "Jammu and Kashmir", any case.
  const norm = (s) => String(s || '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z]/g, '')

  document.addEventListener('alpine:init', () => {
    Alpine.store('coverage', {
      show: false,
      state: '',
      open: [],
      dismiss() {
        this.show = false
        write({ ...read(), dismissed: norm(this.state) })
      },
      /** "Rajasthan and Telangana" / "Rajasthan, Telangana and 2 more". */
      get openList() {
        const names = this.open.map((s) => s.state)
        if (names.length <= 3) return names.length > 1 ? names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1] : (names[0] || '')
        return names.slice(0, 2).join(', ') + ` and ${names.length - 2} more states`
      },
    })
  })

  async function whereAmI() {
    const saved = read()
    const now = Date.now()
    if (saved.state && now - (saved.at || 0) < STATE_TTL) return saved.state
    if (saved.denied && now - saved.denied < DENIED_TTL) return ''
    try {
      // Already refused at the browser: asking again does nothing.
      const perm = navigator.permissions && (await navigator.permissions.query({ name: 'geolocation' }))
      if (perm && perm.state === 'denied') { write({ ...saved, denied: now }); return '' }
    } catch (e) { /* Safari without the Permissions API: just ask */ }
    try {
      const pos = await window.Inthes.currentPosition()
      const place = await window.Inthes.reverseGeocode(pos.coords.latitude, pos.coords.longitude)
      const state = place?.state || ''
      if (state) write({ ...read(), state, at: Date.now() })
      return state
    } catch (e) {
      // 1 = refused; a timeout or no fix is worth trying again next visit.
      if (e && e.code === 1) write({ ...read(), denied: Date.now() })
      return ''
    }
  }

  async function check() {
    if (!navigator.geolocation || !window.Inthes) return
    await window.Inthes.whenReady()
    if (Alpine.store('auth').isEmployer) return

    let open
    try {
      open = (await window.Inthes.api('/jobs/coverage')).data?.states || []
    } catch (e) { return }
    // No live jobs anywhere is not "not in your area" — say nothing.
    if (!open.length) return

    const state = await whereAmI()
    if (!state) return
    if (open.some((s) => norm(s.state) === norm(state))) return
    if (read().dismissed === norm(state)) return

    Object.assign(Alpine.store('coverage'), { state, open, show: true })
  }

  // After the page has settled, so the permission prompt is not the first
  // thing a visitor sees.
  window.addEventListener('load', () => setTimeout(check, 2500))
})()
