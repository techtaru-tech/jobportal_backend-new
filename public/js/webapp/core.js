/**
 * The Inthes web app — the mobile app's screens and flows, on the web.
 *
 * One Alpine component (`webApp`) assembled from parts. This file owns the
 * shell: session, routing, the API wrapper, reference data, toasts, the
 * confirm dialog and the payment-method sheet. Each feature file
 * (`jobs.js`, `profile.js`, `recruiter.js`, `extras.js`) registers a part with
 * `WebApp.part({...})`, and `boot.js` merges them into one component.
 *
 * Contract for a part
 * -------------------
 *  - Property names are shared across every part, so prefix feature state
 *    (`rPost`, `prof…`, `chat…`) — a collision silently overwrites.
 *  - A route named `foo` calls `onFoo(params, query)` on entry if a part
 *    defines it. That is where a screen loads its data.
 *  - A method whose name starts with `_boot` runs once from `init()`.
 *  - Getters survive the merge (descriptors are copied, not values).
 *
 * Every rule that matters lives in the API — the same endpoints the app calls
 * — so this is a UI over them, never a second implementation. Field names,
 * option lists and validation messages follow the Flutter app.
 */
;(function () {
  const API = '/api/v1'
  const TOKEN_KEY = 'inthes.token'
  const USER_KEY = 'inthes.user'
  const MODE_KEY = 'inthes.mode'
  const PENDING_KEY = 'inthes.pending'

  /**
   * Every screen, as `[path, name, access]`.
   * access: 'guest' = signed-out only, 'public' = anyone, 'auth' = signed in.
   */
  const ROUTES = [
    ['/welcome', 'welcome', 'guest'],
    ['/auth', 'auth', 'guest'],
    ['/otp', 'otp', 'guest'],

    // Job seeker
    ['/home', 'home', 'public'],
    ['/jobs', 'results', 'public'],
    ['/search', 'search', 'public'],
    ['/job/:id', 'jobDetail', 'public'],
    ['/apply/:id', 'apply', 'auth'],
    ['/success/:id', 'success', 'auth'],
    ['/saved', 'saved', 'auth'],
    ['/applied', 'applied', 'auth'],
    ['/track/:id', 'track', 'auth'],
    ['/profile', 'profile', 'auth'],
    ['/profile/:section', 'profileEdit', 'auth'],
    ['/onboarding', 'onboarding', 'auth'],
    ['/resume', 'resume', 'auth'],
    ['/alerts', 'alerts', 'auth'],
    ['/subscription', 'subscription', 'auth'],

    // Shared
    ['/notifications', 'notifications', 'auth'],
    ['/chats', 'conversations', 'auth'],
    ['/chat/:id', 'chat', 'auth'],
    ['/page/:slug', 'page', 'public'],

    // Recruiter
    ['/r/home', 'rHome', 'auth'],
    ['/r/jobs', 'rJobs', 'auth'],
    ['/r/post', 'rPost', 'auth'],
    ['/r/edit/:id', 'rPost', 'auth'],
    ['/r/job/:id', 'rJob', 'auth'],
    ['/r/applicants/:id', 'rApplicants', 'auth'],
    ['/r/applicant/:job/:app', 'rApplicant', 'auth'],
    ['/r/orgs', 'rOrgs', 'auth'],
    ['/r/contact', 'rContact', 'auth'],
    ['/r/profile', 'rProfile', 'auth'],
  ].map(([path, name, access]) => {
    const keys = []
    const re = new RegExp('^' + path.replace(/:([a-zA-Z]+)/g, (_, k) => { keys.push(k); return '([^/]+)' }) + '/?$')
    return { path, name, access, keys, re }
  })

  /** The bottom / top navigation, per side — the app's own tabs. */
  const TABS = {
    jobSeeker: [
      { name: 'home', path: '/home', label: 'Home', icon: 'home' },
      { name: 'results', path: '/jobs', label: 'Jobs', icon: 'briefcase' },
      { name: 'saved', path: '/saved', label: 'Saved', icon: 'bookmark' },
      { name: 'applied', path: '/applied', label: 'Applied', icon: 'clipboard' },
      { name: 'profile', path: '/profile', label: 'Profile', icon: 'user' },
    ],
    recruiter: [
      { name: 'rHome', path: '/r/home', label: 'Home', icon: 'home' },
      { name: 'rJobs', path: '/r/jobs', label: 'My Jobs', icon: 'briefcase' },
      { name: 'rPost', path: '/r/post', label: 'Post', icon: 'plus' },
      { name: 'rProfile', path: '/r/profile', label: 'Profile', icon: 'user' },
    ],
  }

  /** Which tab a non-tab screen belongs under, so the nav stays lit. */
  const TAB_OF = {
    jobDetail: null, search: 'results', apply: null, success: 'applied', track: 'applied',
    profileEdit: 'profile', onboarding: 'profile', resume: 'profile', alerts: 'saved', subscription: 'profile',
    rJob: 'rJobs', rApplicants: 'rJobs', rApplicant: 'rJobs', rOrgs: 'rProfile', rContact: 'rProfile',
  }

  const parts = []

  // ── shared formatting ───────────────────────────────────────────────
  const fmtNumber = (v) => Number(v ?? 0).toLocaleString('en-IN')

  /** `₹1,899` from paise — every price on the wire is in paise. */
  const rupees = (paise) => '₹' + Math.round(Number(paise || 0) / 100).toLocaleString('en-IN')

  function timeAgo(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    const s = Math.floor((Date.now() - d.getTime()) / 1000)
    if (s < 60) return 'just now'
    const m = Math.floor(s / 60)
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}h ago`
    const days = Math.floor(h / 24)
    if (days === 1) return 'yesterday'
    if (days < 30) return `${days}d ago`
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
  }

  function fmtDate(iso, withTime = false) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return iso
    const opts = { day: 'numeric', month: 'short', year: 'numeric' }
    if (withTime) Object.assign(opts, { hour: 'numeric', minute: '2-digit' })
    return d.toLocaleString('en-IN', opts)
  }

  function initials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean)
    if (parts.length === 0) return '?'
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }

  const clone = (v) => JSON.parse(JSON.stringify(v ?? null))

  const store = {
    get(k) { try { return localStorage.getItem(k) } catch (e) { return null } },
    set(k, v) { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v) } catch (e) { /* private mode */ } },
  }

  // ── the core part ───────────────────────────────────────────────────
  const core = {
    ICONS: window.ICONS || {},
    TABS,

    // session
    token: '',
    user: null,
    /** 'jobSeeker' | 'recruiter' — a UI setting, never a permission. */
    mode: 'jobSeeker',
    /** The candidate profile — the signed-in person's name, photo, strength. */
    me: null,
    booting: true,

    // auth screens
    authPhone: '',
    authOtp: '',
    authBusy: false,
    authError: '',
    verificationId: '',
    resendIn: 0,
    _resendTimer: null,

    // routing
    route: { name: '', params: {}, query: {}, path: '' },
    _history: 0,

    // reference data from GET /config/options
    options: {},
    optionsReady: false,

    // shell
    toast: '',
    toastError: false,
    _toastTimer: null,
    confirmBox: { open: false, title: '', message: '', ok: 'OK', cancel: 'Cancel', danger: false, _resolve: null },
    paySheet: { open: false, amount: '', methods: [], method: '', _resolve: null },
    userMenu: false,

    // ── lifecycle ─────────────────────────────────────────────────────
    async init() {
      this.token = store.get(TOKEN_KEY) || ''
      // One-time carry-over from the old employer area, so somebody already
      // signed in there is not asked again.
      if (!this.token && store.get('inthes.employer.token')) {
        this.token = store.get('inthes.employer.token')
        store.set(TOKEN_KEY, this.token)
        store.set('inthes.employer.token', null)
        store.set('inthes.employer.user', null)
        store.set(MODE_KEY, 'recruiter')
      }
      try { this.user = JSON.parse(store.get(USER_KEY) || 'null') } catch (e) { this.user = null }
      this.mode = store.get(MODE_KEY) === 'recruiter' ? 'recruiter' : 'jobSeeker'

      this.loadOptions()
      this._runHooks('_boot')

      if (this.token) {
        try { await this.loadMe() } catch (e) { /* a 401 already signed out */ }
      }
      this.booting = false

      window.addEventListener('hashchange', () => this.resolve())
      this.resolve()
    },

    /**
     * Run every part's hook with this prefix. The names are captured at build
     * time (`_hookNames`): inside `init()` `this` is Alpine's scope proxy,
     * which can enumerate as empty, so `Object.keys(this)` is not reliable.
     */
    _runHooks(prefix) {
      for (const key of this._hookNames || []) {
        if (!key.startsWith(prefix) || typeof this[key] !== 'function') continue
        try { this[key]() } catch (e) { console.error(key, e) }
      }
    },
    // ── API ───────────────────────────────────────────────────────────
    /**
     * fetch over `/api/v1`. JSON in and out, `FormData` passed through.
     * A 401 gets one token refresh and a retry, as the app does, before the
     * session is dropped.
     */
    async api(path, opts = {}, _retried = false) {
      const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {})
      let body = opts.body
      if (body !== undefined && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json'
        body = JSON.stringify(body)
      }
      if (this.token) headers.Authorization = 'Bearer ' + this.token

      let res
      try {
        res = await fetch(API + path, { method: opts.method || 'GET', headers, body })
      } catch (e) {
        throw new Error('No internet connection. Check your network and try again.')
      }

      let json = null
      try { json = await res.json() } catch (e) { /* empty body */ }

      if (res.status === 401 && this.token && !path.startsWith('/auth/')) {
        if (!_retried && await this._refreshToken()) return this.api(path, opts, true)
        this.signOut({ expired: true })
        throw new Error('Your session expired — please sign in again.')
      }

      if (!res.ok) {
        const err = new Error(
          (json && json.message) ||
          (json && json.errors ? Object.values(json.errors).flat().join(' ') : null) ||
          'Something went wrong. Please try again.',
        )
        err.status = res.status
        err.errors = (json && json.errors) || {}
        throw err
      }
      return json || {}
    },

    async _refreshToken() {
      try {
        const res = await fetch(API + '/auth/refresh', {
          method: 'POST',
          headers: { Accept: 'application/json', Authorization: 'Bearer ' + this.token },
        })
        if (!res.ok) return false
        const json = await res.json()
        if (!json?.data?.token) return false
        this.token = json.data.token
        store.set(TOKEN_KEY, this.token)
        return true
      } catch (e) { return false }
    },

    /** Multipart upload with the field name the API expects (`file`). */
    upload(path, file, extra = {}) {
      const fd = new FormData()
      fd.append('file', file)
      for (const [k, v] of Object.entries(extra)) fd.append(k, v)
      return this.api(path, { method: 'POST', body: fd })
    },

    /** A query string with PHP-style arrays: `shift[]=Day&shift[]=Night`. */
    qs(params) {
      const out = []
      for (const [k, v] of Object.entries(params || {})) {
        if (v === null || v === undefined || v === '') continue
        if (Array.isArray(v)) v.forEach((x) => out.push(encodeURIComponent(k + '[]') + '=' + encodeURIComponent(x)))
        else out.push(encodeURIComponent(k) + '=' + encodeURIComponent(v))
      }
      return out.length ? '?' + out.join('&') : ''
    },

    loadOptions() {
      return this.api('/config/options')
        .then((res) => { this.options = res.data || {}; this.optionsReady = true })
        .catch(() => { /* pickers fall back to empty lists */ })
    },

    /** An option list by key, always an array. */
    opt(key) {
      const v = this.options?.[key]
      return Array.isArray(v) ? v : []
    },

    citiesOf(state) {
      return (this.options?.state_cities || {})[state] || []
    },

    async loadMe() {
      const res = await this.api('/candidate/profile')
      this.me = res.data || null
      return this.me
    },

    // ── routing ───────────────────────────────────────────────────────
    get defaultPath() {
      if (!this.token) return '/welcome'
      return this.mode === 'recruiter' ? '/r/home' : '/home'
    },

    /** Navigate. `replace` swaps the current history entry. */
    go(path, replace = false) {
      const target = '#' + path
      if (location.hash === target) { this.resolve(); return }
      if (replace) location.replace(target)
      else { this._history += 1; location.hash = path }
    },

    /** Back within the app, or to a sensible parent when there is none. */
    back(fallback) {
      if (this._history > 0) { this._history -= 1; history.back(); return }
      this.go(fallback || this.defaultPath, true)
    },

    resolve() {
      const raw = location.hash.replace(/^#/, '') || this.defaultPath
      const [pathPart, queryPart] = raw.split('?')
      const query = Object.fromEntries(new URLSearchParams(queryPart || ''))

      let match = null
      let params = {}
      for (const r of ROUTES) {
        const m = pathPart.match(r.re)
        if (m) {
          match = r
          params = Object.fromEntries(r.keys.map((k, i) => [k, decodeURIComponent(m[i + 1])]))
          break
        }
      }
      if (!match) { this.go(this.defaultPath, true); return }

      if (match.access === 'auth' && !this.token) {
        // Remember where they were going; sign-in brings them back.
        store.set(PENDING_KEY, raw)
        if (pathPart.startsWith('/r/')) this.setMode('recruiter')
        this.go('/auth', true)
        return
      }
      if (match.access === 'guest' && this.token) { this.go(this.defaultPath, true); return }

      // The screen decides the side: recruiter screens are the recruiter tabs.
      if (pathPart.startsWith('/r/')) this.setMode('recruiter')
      else if (TABS.jobSeeker.some((t) => t.name === match.name) || ['apply', 'onboarding', 'track', 'success'].includes(match.name)) {
        this.setMode('jobSeeker')
      }

      this.route = { name: match.name, params, query, path: raw }
      this.userMenu = false
      window.scrollTo({ top: 0 })

      const hook = 'on' + match.name[0].toUpperCase() + match.name.slice(1)
      if (typeof this[hook] === 'function') {
        try {
          const p = this[hook](params, query)
          if (p && p.catch) p.catch((e) => this.showToast(e.message, true))
        } catch (e) { this.showToast(e.message, true) }
      }
    },

    is(...names) { return names.includes(this.route.name) },

    get activeTab() {
      const n = this.route.name
      return n in TAB_OF ? TAB_OF[n] : n
    },

    get tabs() { return TABS[this.mode] },

    /** Screens that draw the app chrome (header + bottom nav). */
    get showChrome() { return !!this.route.name && !this.is('welcome', 'auth', 'otp', 'onboarding') },

    setMode(mode) {
      if (this.mode === mode) return
      this.mode = mode
      store.set(MODE_KEY, mode)
    },

    switchMode() {
      this.setMode(this.mode === 'recruiter' ? 'jobSeeker' : 'recruiter')
      this.go(this.defaultPath)
    },

    // ── welcome + sign in ─────────────────────────────────────────────
    chooseSide(mode) {
      this.setMode(mode)
      this.go('/auth')
    },

    get phoneValid() { return /^[6-9]\d{9}$/.test(this.authPhone.trim()) },

    get phoneError() {
      const v = this.authPhone.trim()
      if (!v) return 'Mobile number is required'
      if (!this.phoneValid) return 'Enter a valid 10-digit number'
      return ''
    },

    async sendOtp() {
      if (this.authBusy) return
      if (!this.phoneValid) { this.authError = this.phoneError; return }
      this.authBusy = true
      this.authError = ''
      try {
        const res = await this.api('/auth/otp/send', {
          method: 'POST',
          body: { phone: this.authPhone.trim(), role: this.mode === 'recruiter' ? 'recruiter' : 'candidate' },
        })
        this.verificationId = res.data.verification_id
        // Local/dev servers echo the code back (OTP_EXPOSE_CODE) — prefill it
        // there, exactly as a tester would type it.
        this.authOtp = res.data.code ? String(res.data.code) : ''
        this._startResendCountdown()
        if (!this.is('otp')) this.go('/otp')
      } catch (e) {
        this.authError = e.message
      }
      this.authBusy = false
    },

    async verifyOtp() {
      const otp = this.authOtp.replace(/\D/g, '')
      if (otp.length !== 6 || this.authBusy) return
      this.authBusy = true
      this.authError = ''
      try {
        const res = await this.api('/auth/otp/verify', {
          method: 'POST',
          body: {
            phone: this.authPhone.trim(),
            otp,
            verification_id: this.verificationId,
            role: this.mode === 'recruiter' ? 'recruiter' : 'candidate',
          },
        })
        this.token = res.data.token
        this.user = res.data.user
        store.set(TOKEN_KEY, this.token)
        store.set(USER_KEY, JSON.stringify(this.user))
        this.authOtp = ''
        this.verificationId = ''
        clearInterval(this._resendTimer)
        await this.afterSignIn(res.data.user?.is_new_user)
      } catch (e) {
        this.authError = e.message
        this.authOtp = ''
      }
      this.authBusy = false
    },

    /** Where the app goes after OTP: the pending action, then onboarding, then home. */
    async afterSignIn(isNew) {
      try { await this.loadMe() } catch (e) { /* shown on the next screen */ }
      this._runHooks('_afterSignIn')
      const pending = store.get(PENDING_KEY)
      store.set(PENDING_KEY, null)
      if (pending) { this.go(pending, true); return }
      if (this.mode === 'recruiter') { this.go('/r/post', true); return }
      if (isNew && !(this.me?.profile_strength > 0)) { this.go('/onboarding', true); return }
      this.go('/home', true)
    },

    _startResendCountdown() {
      this.resendIn = 24
      clearInterval(this._resendTimer)
      this._resendTimer = setInterval(() => {
        this.resendIn -= 1
        if (this.resendIn <= 0) clearInterval(this._resendTimer)
      }, 1000)
    },

    onOtp() {
      if (!this.verificationId) this.go('/auth', true)
    },

    signOut({ expired = false } = {}) {
      if (this.token && !expired) this.api('/auth/logout', { method: 'POST' }).catch(() => {})
      this.token = ''
      this.user = null
      this.me = null
      store.set(TOKEN_KEY, null)
      store.set(USER_KEY, null)
      this._runHooks('_onSignOut')
      // Signing out returns to Welcome and forgets the side; an expired
      // session goes back to sign-in and keeps it — the app's own rule.
      if (expired) {
        if (this.route.path) store.set(PENDING_KEY, this.route.path)
        this.go('/auth', true)
      } else {
        store.set(MODE_KEY, null)
        this.mode = 'jobSeeker'
        this.go('/welcome', true)
      }
    },

    async confirmSignOut() {
      if (await this.ask({ title: 'Sign out?', message: 'You will need your mobile number to sign in again.', ok: 'Sign Out', danger: true })) {
        this.signOut()
      }
    },

    /** Gate an action on being signed in; remembers where to come back to. */
    requireAuth(returnTo) {
      if (this.token) return true
      store.set(PENDING_KEY, returnTo || this.route.path)
      this.go('/auth')
      return false
    },

    // ── feedback ──────────────────────────────────────────────────────
    showToast(message, isError = false) {
      if (!message) return
      this.toast = message
      this.toastError = isError
      clearTimeout(this._toastTimer)
      this._toastTimer = setTimeout(() => { this.toast = '' }, 4000)
    },

    /** A confirm dialog. Resolves true/false. */
    ask({ title = 'Are you sure?', message = '', ok = 'OK', cancel = 'Cancel', danger = false } = {}) {
      return new Promise((resolve) => {
        this.confirmBox = { open: true, title, message, ok, cancel, danger, _resolve: resolve }
      })
    },

    closeConfirm(result) {
      const r = this.confirmBox._resolve
      this.confirmBox = { ...this.confirmBox, open: false, _resolve: null }
      if (r) r(result)
    },

    // ── payments ──────────────────────────────────────────────────────
    /**
     * The app's "Payment method" sheet. Resolves with the method key
     * (`upi` | `card` | `netbanking`) or null when cancelled.
     */
    async choosePaymentMethod(amountLabel) {
      if (!this.paySheet.methods.length) {
        try {
          const res = await this.api('/payments/methods')
          this.paySheet.methods = res.data?.methods || res.data || []
        } catch (e) {
          this.paySheet.methods = [{ key: 'upi', label: 'UPI' }, { key: 'card', label: 'Card' }, { key: 'netbanking', label: 'Net banking' }]
        }
      }
      return new Promise((resolve) => {
        const first = this.paySheet.methods[0]
        this.paySheet = { ...this.paySheet, open: true, amount: amountLabel, method: first ? (first.key || first.id || first) : '', _resolve: resolve }
      })
    },

    closePaySheet(confirmed) {
      const r = this.paySheet._resolve
      const method = this.paySheet.method
      this.paySheet = { ...this.paySheet, open: false, _resolve: null }
      if (r) r(confirmed ? method : null)
    },

    /**
     * Settle an opened order: `POST /payments/orders/{id}/confirm`.
     * Resolves with the order; throws with the gateway's reason on failure.
     */
    async confirmOrder(orderId) {
      const res = await this.api(`/payments/orders/${orderId}/confirm`, { method: 'POST', body: { gateway_payload: {} } })
      const order = res.data?.order || res.data
      if (order?.status && order.status !== 'paid') {
        throw new Error(order.failure_reason || 'Payment failed. You were not charged.')
      }
      return order
    },

    // ── helpers shared by every screen ────────────────────────────────
    fmtNumber, rupees, timeAgo, fmtDate, initials, clone,

    toggleIn(list, value, max) {
      const i = list.indexOf(value)
      if (i !== -1) { list.splice(i, 1); return }
      if (max && list.length >= max) { this.showToast(`You can pick up to ${max}.`, true); return }
      list.push(value)
    },

    /** Copy text, with a toast either way. */
    async copy(text, done = 'Link copied') {
      try { await navigator.clipboard.writeText(text); this.showToast(done) } catch (e) { this.showToast(text) }
    },

    /** Native share where there is one, otherwise copy. */
    async share(title, url) {
      if (navigator.share) {
        try { await navigator.share({ title, url }); return } catch (e) { /* cancelled */ return }
      }
      this.copy(url)
    },

    get displayName() {
      return this.me?.name || (this.user?.phone ? '+91 ' + this.user.phone : 'there')
    },

    get firstName() {
      return (this.me?.name || '').trim().split(/\s+/)[0] || 'there'
    },

    get playStoreUrl() { return window.WEBAPP_CONFIG?.storeUrl || '/get-app/go' },
  }

  window.WebApp = {
    ROUTES,
    TABS,
    part(obj) { parts.push(obj) },
    helpers: { fmtNumber, rupees, timeAgo, fmtDate, initials, clone, store },
    /** Build the component: core first, then every part in load order. */
    build() {
      const app = {}
      for (const src of [core, ...parts]) {
        Object.defineProperties(app, Object.getOwnPropertyDescriptors(src))
      }
      // Fresh copies of object state, so nothing is shared with the source literals.
      for (const [k, d] of Object.entries(Object.getOwnPropertyDescriptors(app))) {
        if ('value' in d && d.value && typeof d.value === 'object' && !Array.isArray(d.value) && k !== 'ICONS' && k !== 'TABS') {
          app[k] = clone(d.value) ?? d.value
        }
      }
      app.ICONS = window.ICONS || {}
      app._hookNames = Object.keys(app).filter((k) => /^_(boot|afterSignIn|onSignOut)/.test(k))
      return app
    },
  }
})()
