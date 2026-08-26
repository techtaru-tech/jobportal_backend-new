/**
 * Inthes Admin Panel — the Alpine component behind resources/views/admin/.
 *
 * A Blade + Alpine port of admin_panel/ (React + Vite), talking to the same
 * /api/v1/admin/* endpoints on the same origin, so there is no CORS setup and
 * no second build pipeline.
 *
 * Structure mirrors the React app it replaces:
 *   NAV / ICONS / QUICK_ACTIONS  -> components/layout/nav.ts
 *   the format* helpers          -> lib/format.ts
 *   STATUS_* maps                -> lib/chartTheme.ts + components/ui/Badge.tsx
 *   areaChart/statusPie/...      -> the Recharts components in pages/Dashboard
 *
 * The chart helpers own their scales so the Blade markup stays declarative:
 * every one returns plain geometry (percentages and point lists) that a
 * template can bind straight to an SVG attribute.
 */
(function () {
  const API_BASE = '/api/v1'

  /** Remembered across reloads: an operator who collapsed the rail meant it. */
  const COLLAPSE_KEY = 'inthes.admin.sidebarCollapsed'
  const TOKEN_KEY = 'inthes.admin.token'
  const ADMIN_KEY = 'inthes.admin.profile'

  /**
   * The panel's sections. The sidebar renders them as links and the top bar's
   * search matches against them, so a section added here shows up in both.
   *
   * `badge: true` puts the notification feed's open-work count beside the
   * item, surfacing work waiting to be done rather than only listing places to
   * go — the verification queue in particular is invisible otherwise.
   */
  const NAV = [
    { key: 'dashboard', label: 'Dashboard', icon: 'dashboard',
      keywords: ['overview', 'home', 'stats', 'metrics', 'funnel', 'analytics'] },
    { key: 'users', label: 'Accounts', icon: 'users',
      keywords: ['users', 'candidates', 'recruiters', 'people', 'profiles'] },
    { key: 'jobs', label: 'Job postings', icon: 'briefcase',
      keywords: ['jobs', 'vacancies', 'listings', 'postings'] },
    { key: 'applications', label: 'Applications', icon: 'clipboard',
      keywords: ['applicants', 'pipeline', 'shortlist', 'applied'] },
    { key: 'organisations', label: 'Employers', icon: 'badgeCheck', badge: 'pending_verification',
      keywords: ['organisations', 'companies', 'hospitals', 'verification', 'verify', 'gst'] },
    { key: 'notifications', label: 'Notifications', icon: 'bell', badge: true,
      keywords: ['alerts', 'notifications', 'registered', 'signups', 'new employer',
                 'new job', 'approval', 'push', 'delivery', 'read rate'] },
    { key: 'subscriptions', label: 'Plans', icon: 'creditCard',
      keywords: ['subscriptions', 'billing', 'pricing', 'paid', 'free'] },
    { key: 'optionLists', label: 'Reference data', icon: 'sliders',
      keywords: ['skills', 'cities', 'qualifications', 'categories', 'options', 'lists',
                 'shifts', 'job types'] },
    { key: 'content', label: 'Support & Legal', icon: 'fileText',
      keywords: ['terms', 'privacy', 'about us', 'contact us', 'faq', 'faqs', 'help',
                 'support', 'legal', 'policy'] },
  ]

  /** Which list a detail view belongs to, for the breadcrumb and Back links. */
  const DETAIL_PARENT = {
    userDetail: 'users',
    jobDetail: 'jobs',
    applicationDetail: 'applications',
    organisationDetail: 'organisations',
    optionListDetail: 'optionLists',
  }

  const ACTIVITY_TABS = [
    { key: 'applications', label: 'Applications' },
    { key: 'users', label: 'New users' },
    { key: 'jobs', label: 'Postings' },
    { key: 'messages', label: 'Messages' },
  ]

  const JOB_STATUSES = ['pending_approval', 'active', 'paused', 'draft', 'closed', 'expired', 'rejected']
  const APP_STATUSES = ['applied', 'shortlisted', 'selected', 'rejected']

  /**
   * Status colours. Semantic, not decorative — the same status must be the
   * same colour on a dot as it is in the donut, or the chart and the table
   * beside it disagree about what "shortlisted" looks like.
   */
  const APPLICATION_DOTS = {
    applied: '#2563A6', shortlisted: '#EB0401', selected: '#1E9E5A', rejected: '#C81E1E',
  }
  const JOB_DOTS = {
    pending_approval: '#B8790A', active: '#1E9E5A', paused: '#B8790A', draft: '#9A9A9E',
    closed: '#6B6B70', expired: '#C81E1E', rejected: '#C81E1E',
  }
  /** Statuses whose raw value does not read as a label. */
  const APPLICATION_LABELS = {
    applied: 'Applied', shortlisted: 'Shortlisted',
    // The product's own wording — never "Rejected" where a person can read it.
    rejected: 'Not selected', selected: 'Selected',
  }
  const JOB_LABELS = { pending_approval: 'Pending approval' }

  // ── formatting ────────────────────────────────────────────────────────
  // Kept together so a date or a count never renders two different ways on
  // two screens. Mirrors admin_panel/src/lib/format.ts.

  const fmtNumber = (v) => (v ?? 0).toLocaleString('en-IN')

  function fmtDate(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
  }

  function fmtDateTime(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    return d.toLocaleString('en-IN', {
      day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit',
    })
  }

  /**
   * Relative time, mirroring the app's `Helpers.timeAgo`. Falls back to an
   * absolute date past a month: "7 weeks ago" is harder to place than
   * "2 Jul 2026", and precision stops being the useful thing at that range.
   */
  function timeAgo(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'

    const seconds = Math.floor((Date.now() - d.getTime()) / 1000)
    if (seconds < 60) return 'just now'
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days === 1) return 'yesterday'
    if (days < 30) return `${days}d ago`
    return fmtDate(iso)
  }

  /** Shortens a chart's day bucket to "19 Aug". */
  function shortDay(isoDate) {
    const d = new Date(`${isoDate}T00:00:00`)
    if (Number.isNaN(d.getTime())) return isoDate
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })
  }

  /**
   * Initials from a display name, matching `Helpers.initials` in the app:
   * first letters of the first two words, uppercased.
   */
  function initials(name) {
    const parts = (name ?? '').trim().split(/\s+/).filter(Boolean)
    if (parts.length === 0) return '?'
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }

  /** A "nice" axis maximum, so ticks land on round numbers rather than 37. */
  function niceMax(value) {
    if (value <= 0) return 4
    const magnitude = Math.pow(10, Math.floor(Math.log10(value)))
    const normalized = value / magnitude
    const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10
    return step * magnitude
  }

  function adminApp() {
    return {
      // ── auth / shell ────────────────────────────────────────────────
      token: '',
      admin: null,
      isRestoring: true,
      loginForm: { email: '', password: '' },
      loginBusy: false,
      loginError: '',

      toast: '',
      toastError: false,
      _toastTimer: null,

      view: 'dashboard',
      drawerOpen: false,
      collapsed: false,

      bellOpen: false,
      profileOpen: false,
      searchOpen: false,
      searchQuery: '',
      searchHighlight: 0,

      NAV, ICONS: window.ICONS || {}, ACTIVITY_TABS,
      JOB_STATUSES, APP_STATUSES,

      QUICK_ACTIONS: [
        { label: 'Verify employers', hint: 'Review GST documents', icon: 'badgeCheck',
          view: 'organisations', filter: { state: 'pending' } },
        { label: 'Unstick applications', hint: 'No movement in a while', icon: 'clipboard',
          view: 'applications', filter: { stuck: true } },
        { label: 'Review the queue', hint: 'Everything waiting on an operator', icon: 'bell',
          view: 'notifications' },
        { label: 'Reference data', hint: 'Skills, cities, qualifications', icon: 'sliders',
          view: 'optionLists' },
      ],

      // ── page state ──────────────────────────────────────────────────
      dash: { days: 30, data: null, busy: false, error: false, tab: 'applications' },
      notifications: { data: [], meta: null, delivery: null, windowDays: 0, busy: false },
      actionTotal: 0,
      /** Per-queue open counts from the feed, keyed as the NAV badges name them. */
      openWork: {},

      users: { q: '', side: '', sort: '', data: [], meta: null, busy: false, error: false },
      userDetail: { data: null, busy: false },

      jobs: { q: '', status: '', zero: false, unverified: false, missingCoords: false,
              data: [], meta: null, busy: false, error: false },
      jobDetail: { data: null, busy: false },
      jobStatusPick: '',
      jobExpiryPick: '',

      apps: { q: '', status: '', stuck: false, missingInterview: false,
              data: [], meta: null, busy: false, error: false },
      appDetail: { data: null, busy: false },
      appStatusPick: '',
      appStatusReason: '',

      orgs: { q: '', state: '', dup: false, data: [], meta: null, busy: false, error: false },
      orgDetail: { data: null, busy: false },
      orgUnverifyReason: '',

      plans: { data: null },
      subs: { audience: '', state: '', data: [], meta: null, busy: false, error: false },

      optionLists: { data: null, busy: false },
      optionListDetail: { data: null, key: '', busy: false },
      newOptionValue: '',

      pages: { data: [], busy: false },
      faqs: { data: [], busy: false },
      addFaqOpen: false,
      newFaq: { question: '', answer: '' },

      // ── computed ────────────────────────────────────────────────────
      get canWrite() { return !!(this.admin && this.admin.can_write) },
      /** The drawer always shows labels: it only exists on small screens. */
      get railed() { return this.collapsed && !this.drawerOpen },

      // ── lifecycle ───────────────────────────────────────────────────
      init() {
        // Alpine reached this component, so the page is going to render —
        // stand down the blank-page guard armed in the Blade view.
        clearTimeout(window.__adminBootTimer)

        this.collapsed = localStorage.getItem(COLLAPSE_KEY) === '1'
        this.token = localStorage.getItem(TOKEN_KEY) || ''

        const cached = localStorage.getItem(ADMIN_KEY)
        if (cached) {
          try { this.admin = JSON.parse(cached) } catch (e) { /* ignore */ }
        }

        if (!this.token) {
          this.admin = null
          this.isRestoring = false
          return
        }

        // Confirm the token still resolves before trusting the cached profile:
        // an admin sign-in elsewhere retires this one's tokens.
        this.api('/admin/auth/me')
          .then((res) => {
            this.admin = res.data.admin
            localStorage.setItem(ADMIN_KEY, JSON.stringify(this.admin))
            this.loadNotifications()
            this._startPolling()
          })
          .catch(() => { this.clearSession() })
          .finally(() => { this.isRestoring = false })

        // Cmd/Ctrl+K from anywhere — the shortcut every operator expects.
        document.addEventListener('keydown', (event) => {
          if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault()
            this.$refs.menuSearch && this.$refs.menuSearch.focus()
          }
        })
      },

      // ── fetch ───────────────────────────────────────────────────────
      async api(path, opts = {}) {
        const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {})
        let body = opts.body
        if (body !== undefined && !(body instanceof FormData)) {
          headers['Content-Type'] = 'application/json'
          body = JSON.stringify(body)
        }
        if (this.token) headers.Authorization = 'Bearer ' + this.token

        const res = await fetch(API_BASE + path, { ...opts, headers, body })

        let json = null
        try { json = await res.json() } catch (e) { /* empty body */ }

        if (res.status === 401) {
          this.clearSession()
          throw new Error('Your session expired — please sign in again.')
        }
        if (!res.ok) {
          throw new Error(
            (json && json.message) ||
            (json && json.errors ? Object.values(json.errors).flat().join(' ') : null) ||
            'Something went wrong.',
          )
        }
        return json
      },

      /** Every list request funnels through here so busy/error handling is uniform. */
      loadList(state, path, params, page) {
        const search = new URLSearchParams()
        Object.entries(params || {}).forEach(([k, v]) => {
          if (v === '' || v === false || v === null || v === undefined) return
          search.set(k, v === true ? '1' : v)
        })
        search.set('page', page || 1)
        search.set('per_page', 20)

        state.busy = true
        state.error = false
        return this.api(`${path}?${search.toString()}`)
          .then((res) => { state.data = res.data; state.meta = res.meta })
          .catch((e) => { state.error = true; this.showToast(e.message, true) })
          .finally(() => { state.busy = false })
      },

      showToast(message, isError = false) {
        this.toast = message
        this.toastError = isError
        clearTimeout(this._toastTimer)
        this._toastTimer = setTimeout(() => { this.toast = '' }, 4000)
      },

      // ── auth actions ────────────────────────────────────────────────
      async login() {
        this.loginBusy = true
        this.loginError = ''
        try {
          const res = await this.api('/admin/auth/login', {
            method: 'POST',
            body: { email: this.loginForm.email.trim(), password: this.loginForm.password },
          })
          this.token = res.data.token
          this.admin = res.data.admin
          localStorage.setItem(TOKEN_KEY, this.token)
          localStorage.setItem(ADMIN_KEY, JSON.stringify(this.admin))
          this.loginForm = { email: '', password: '' }
          this.view = 'dashboard'
          this.loadNotifications()
          this._startPolling()
        } catch (e) {
          this.loginError = e.message
        }
        this.loginBusy = false
      },

      logout() {
        if (this.token) this.api('/admin/auth/logout', { method: 'POST' }).catch(() => {})
        this.clearSession()
      },

      clearSession() {
        clearInterval(this._poll)
        this._poll = null
        // Cleared so the next session announces from scratch rather than
        // treating the previous operator's backlog as already-seen.
        this._seen = null
        this.token = ''
        this.admin = null
        this.isRestoring = false
        this.profileOpen = false
        this.bellOpen = false
        localStorage.removeItem(TOKEN_KEY)
        localStorage.removeItem(ADMIN_KEY)
        this.view = 'dashboard'
      },

      // ── navigation ──────────────────────────────────────────────────
      go(key) {
        this.view = key
        this.drawerOpen = false
        this.bellOpen = false
        this.profileOpen = false
      },

      toggleCollapsed() {
        this.collapsed = !this.collapsed
        localStorage.setItem(COLLAPSE_KEY, this.collapsed ? '1' : '0')
      },

      /** True while `key`'s section is showing, including its detail views. */
      isSection(key) {
        return this.view === key || DETAIL_PARENT[this.view] === key
      },
      isDetail() { return Boolean(DETAIL_PARENT[this.view]) },
      sectionKey() { return DETAIL_PARENT[this.view] || this.view },
      sectionLabel() {
        const item = NAV.find((n) => n.key === this.sectionKey())
        return item ? item.label : ''
      },

      /**
       * The count beside a nav item.
       *
       * `badge: true` shows everything still open (the Notifications item);
       * a string names one queue from the feed's `open` breakdown, so the
       * section that owns a queue badges just its own rather than a combined
       * number repeated in three places.
       */
      badgeCount(item) {
        if (!item.badge) return 0
        if (item.badge === true) return this.actionTotal
        return this.openWork[item.badge] || 0
      },

      searchMatches() {
        const term = this.searchQuery.trim().toLowerCase()
        if (!term) return []
        return NAV.filter(
          (item) =>
            item.label.toLowerCase().includes(term) ||
            (item.keywords || []).some((k) => k.includes(term)),
        )
      },
      moveSearch(delta) {
        const matches = this.searchMatches()
        if (matches.length === 0) return
        this.searchHighlight = (this.searchHighlight + delta + matches.length) % matches.length
      },
      openSearchHit() {
        const matches = this.searchMatches()
        if (matches.length === 0) return
        this.go(matches[this.searchHighlight].key)
        this.searchQuery = ''
        this.searchOpen = false
      },

      runQuickAction(action) {
        if (action.filter) Object.assign(this[this.stateFor(action.view)], action.filter)
        this.go(action.view)
      },
      stateFor(view) {
        return { organisations: 'orgs', applications: 'apps', jobs: 'jobs' }[view] || view
      },

      /**
       * Opens whatever a notification is about. The server names the target as
       * `{view, id}` rather than a URL, so the panel does not have to parse
       * routes belonging to a different frontend.
       */
      openNotification(row) {
        const link = row.link || {}
        if (link.view === 'organisations' && link.id) return this.openOrganisation(link.id)
        if (link.view === 'jobs' && link.id) return this.openJob(link.id)
        if (link.view === 'users' && link.id) return this.openUser(link.id)
        if (link.view) return this.go(link.view)
      },

      /** Which glyph a feed row gets, by event type. */
      notificationIcon(type) {
        return {
          'user.registered': 'users',
          'organisation.registered': 'badgeCheck',
          'job.submitted': 'briefcase',
        }[type] || 'bell'
      },

      // ── shared display helpers ──────────────────────────────────────
      fmtNumber, fmtDate, fmtDateTime, timeAgo, shortDay, initials,

      /**
       * The status-dot idiom: a bare 7px circle followed by a caption label.
       * No ring, no background pill — semantic colour is rationed to these.
       * Returned as markup so a template can drop it into any cell.
       */
      statusDot(status, kind) {
        if (!status) return ''
        const map = kind === 'application' ? APPLICATION_DOTS : JOB_DOTS
        const labels = kind === 'application' ? APPLICATION_LABELS : JOB_LABELS
        const label = labels[status] || status
        const color = map[status] || '#9A9A9E'
        return (
          '<span class="inline-flex items-center gap-[6px] whitespace-nowrap">' +
          `<span class="h-[7px] w-[7px] shrink-0 rounded-full" style="background:${color}"></span>` +
          `<span class="text-caption capitalize text-ink-secondary">${label}</span></span>`
        )
      },

      trendClass(value) {
        if (Math.round(value) === 0) return 'bg-surface-muted text-ink-muted'
        return value > 0 ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'
      },

      welcomeTitle() {
        const first = this.admin && this.admin.name ? this.admin.name.trim().split(/\s+/)[0] : ''
        return 'Welcome back' + (first ? `, ${first}` : '')
      },

      // ── dashboard ───────────────────────────────────────────────────
      loadDashboard() {
        this.dash.busy = true
        this.dash.error = false
        this.api('/admin/dashboard?days=' + this.dash.days)
          .then((res) => { this.dash.data = res.data })
          .catch((e) => { this.dash.error = true; this.showToast(e.message, true) })
          .finally(() => { this.dash.busy = false })
      },

      /**
       * Percentage change between the first and second half of a series.
       *
       * Deliberately halves the window rather than comparing the last point to
       * the first: one quiet day at either end would swing the figure wildly
       * and the arrow would be noise. Null when there is no prior period —
       * "up ∞%" from zero is not information.
       */
      trendOf(series) {
        if (!series || series.length < 4) return null
        const mid = Math.floor(series.length / 2)
        const sum = (rows) => rows.reduce((t, d) => t + d.count, 0)
        const before = sum(series.slice(0, mid))
        const after = sum(series.slice(mid))
        if (before === 0) return null
        return ((after - before) / before) * 100
      },

      dashCards() {
        if (!this.dash.data) return []
        const t = this.dash.data.totals
        const s = this.dash.data.series
        return [
          { key: 'applications', feature: true, label: 'Applications', to: 'applications',
            value: fmtNumber(t.applications.total),
            delta: `${fmtNumber(t.applications.new)} in this range`,
            icon: 'clipboard', spark: s.applications.map((d) => d.count),
            trend: this.trendOf(s.applications) },
          { key: 'users', label: 'Users', to: 'users',
            value: fmtNumber(t.users.total), delta: `${fmtNumber(t.users.new)} new`,
            icon: 'users', spark: s.users.map((d) => d.count), trend: this.trendOf(s.users) },
          { key: 'jobs', label: 'Active jobs', to: 'jobs',
            value: fmtNumber(t.jobs.active), delta: `of ${fmtNumber(t.jobs.total)} posted`,
            icon: 'briefcase', spark: s.jobs.map((d) => d.count), trend: this.trendOf(s.jobs) },
          { key: 'orgs', label: 'Verified employers', to: 'organisations',
            value: fmtNumber(t.organisations.verified),
            delta: `of ${fmtNumber(t.organisations.total)} registered`,
            icon: 'badgeCheck', spark: null, trend: null },
        ]
      },

      /**
       * The operator's own to-do list, and only that.
       *
       * Nothing about applications is here any more. "Stuck applications",
       * "no interview set" and "silent chats" all described somebody else's
       * work — an application belongs to the recruiter who owns the posting,
       * and an admin moving it is an intervention rather than routine. They
       * also made up most of the row, so the numbers an operator can actually
       * clear were the minority on their own dashboard.
       *
       * What is left either blocks a candidate from seeing something (an
       * unverified employer, an unapproved posting) or is a data fault only an
       * admin can see (a posting with no coordinates drops out of distance
       * sorting silently).
       *
       * The dashboard endpoint still returns the dropped counts; they are
       * simply not rendered, so nothing had to change server-side.
       */
      attentionItems() {
        if (!this.dash.data) return []
        const a = this.dash.data.attention
        const defs = [
          { key: 'pending_verification', label: 'Awaiting verification', icon: 'badgeCheck',
            go: () => { this.orgs.state = 'pending'; this.go('organisations') } },
          { key: 'unverified_no_document', label: 'No GST document', icon: 'fileText',
            go: () => { this.orgs.state = 'no_document'; this.go('organisations') } },
          { key: 'zero_applicant_active_jobs', label: 'Zero applicants', icon: 'briefcase',
            go: () => { this.jobs.zero = true; this.go('jobs') } },
          { key: 'jobs_without_coordinates', label: 'Missing location', icon: 'mapPinOff',
            go: () => { this.jobs.missingCoords = true; this.go('jobs') } },
        ]
        return defs
          .filter((d) => (a[d.key] || 0) > 0)
          .map((d) => ({ ...d, count: a[d.key] }))
      },

      activitySeries() {
        if (!this.dash.data) return []
        return this.dash.data.series[this.dash.tab] || []
      },
      activityTotal() { return this.activitySeries().reduce((sum, d) => sum + d.count, 0) },
      activityPeak() { return Math.max(...this.activitySeries().map((d) => d.count), 0) },

      // ── chart geometry ──────────────────────────────────────────────

      /** Sparkline points in a 0–100 box, for the stat tiles. */
      sparkPoints(values) {
        const max = Math.max(...values, 1)
        const min = Math.min(...values, 0)
        const range = max - min || 1
        return values
          .map((v, i) => `${(i / (values.length - 1)) * 100},${100 - ((v - min) / range) * 100}`)
          .join(' ')
      },
      sparkArea(values) {
        return `${this.sparkPoints(values)} 100,100 0,100`
      },

      /**
       * The activity area chart. Everything is in a 0–100 user space that the
       * SVG stretches to the card, so the template never does arithmetic.
       */
      areaChart() {
        const series = this.activitySeries()
        if (series.length === 0) {
          return { points: [], line: '', area: '', gridY: [], yTicks: [], xLabels: [] }
        }

        const max = niceMax(Math.max(...series.map((d) => d.count), 1))
        const points = series.map((d, i) => ({
          x: series.length === 1 ? 50 : (i / (series.length - 1)) * 100,
          y: 100 - (d.count / max) * 100,
          count: d.count,
          date: d.date,
        }))

        const line = points.map((p) => `${p.x},${p.y}`).join(' ')
        const area = `${line} 100,100 0,100`

        // Five rules, matching the tick count Recharts settles on at this height.
        const steps = 4
        const gridY = []
        const yTicks = []
        for (let i = 0; i <= steps; i++) {
          const y = (i / steps) * 100
          gridY.push(y)
          yTicks.push({ y, value: Math.round(max - (i / steps) * max) })
        }

        // Thinned so labels never collide, standing in for `minTickGap`.
        const every = Math.max(1, Math.ceil(series.length / 6))
        const xLabels = series.filter((_, i) => i % every === 0).map((d) => shortDay(d.date))

        return { points, line, area, gridY, yTicks, xLabels }
      },

      /**
       * The area chart as a complete SVG string.
       *
       * Built here rather than from `<template x-for>` elements in the Blade
       * view because a `<template>` written inside an `<svg>` is parsed into
       * the SVG namespace, where it is an unknown element with no `.content`
       * fragment — Alpine's `x-for` then silently renders nothing. Returning
       * markup for `x-html` sidesteps the namespace entirely, and stays
       * reactive: Alpine re-runs this whenever the series or the tab changes.
       */
      areaSvg() {
        const chart = this.areaChart()
        if (chart.points.length === 0) return ''

        const grid = chart.gridY
          .map((y) => `<line x1="0" y1="${y}" x2="100" y2="${y}" stroke="#EDEDEF" stroke-width="1" vector-effect="non-scaling-stroke"/>`)
          .join('')

        return (
          '<svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-full w-full" aria-hidden="true">' +
          '<defs><linearGradient id="fillRed" x1="0" y1="0" x2="0" y2="1">' +
          '<stop offset="0%" stop-color="#EB0401" stop-opacity="0.4"/>' +
          '<stop offset="100%" stop-color="#EB0401" stop-opacity="0"/>' +
          '</linearGradient></defs>' +
          // Horizontal rules only — a vertical grid competes with the fill.
          grid +
          `<polygon points="${chart.area}" fill="url(#fillRed)"/>` +
          `<polyline points="${chart.line}" fill="none" stroke="#EB0401" stroke-width="2" ` +
          'stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>' +
          '</svg>'
        )
      },

      /** The status donut as a complete SVG string — see [areaSvg] for why. */
      donutSvg() {
        const pie = this.statusPie()
        if (pie.total === 0) return ''

        const rings = pie.slices
          .map(
            (slice) =>
              `<circle cx="84" cy="84" r="${pie.radius}" fill="none" stroke="${slice.color}" ` +
              `stroke-width="${pie.thickness}" stroke-dasharray="${slice.dash}" ` +
              `stroke-dashoffset="${slice.offset}"/>`,
          )
          .join('')

        // -90deg so the first wedge starts at twelve o'clock, as Recharts does.
        return (
          '<svg viewBox="0 0 168 168" class="h-full w-full -rotate-90" aria-hidden="true">' +
          rings +
          '</svg>'
        )
      },

      /**
       * The status donut. Wedges are dash-array arcs on one circle rather than
       * paths — which is what makes the 3° padding gap between them trivial.
       */
      statusPie() {
        const rows = (this.dash.data && this.dash.data.distributions.application_status) || []
        const total = rows.reduce((sum, d) => sum + d.count, 0)
        if (total === 0) return { total: 0, slices: [], radius: 66, thickness: 28 }

        // innerRadius 52 / outerRadius 80 in a 168px box -> a 66px mid-line
        // with a 28px stroke, which draws the same ring.
        const radius = 66
        const thickness = 28
        const circumference = 2 * Math.PI * radius
        const gap = circumference * (3 / 360) // paddingAngle={3}

        let consumed = 0
        const slices = rows.map((row) => {
          const fraction = row.count / total
          const length = Math.max(0, fraction * circumference - gap)
          const slice = {
            status: row.status,
            label: row.label,
            count: row.count,
            percent: Math.round(fraction * 100),
            color: APPLICATION_DOTS[row.status] || '#9A9A9E',
            dash: `${length} ${circumference - length}`,
            offset: -consumed,
          }
          consumed += fraction * circumference
          return slice
        })

        return { total, slices, radius, thickness }
      },

      /** Profile-strength bands, as bar heights in percent. */
      histogram() {
        const rows = (this.dash.data && this.dash.data.distributions.profile_strength) || []
        const max = niceMax(Math.max(...rows.map((d) => d.count), 1))

        const bars = rows.map((row, i) => ({
          bucket: row.bucket,
          count: row.count,
          height: (row.count / max) * 100,
          // A stronger red the fuller the profile — the x-axis restated as
          // colour, so the shape reads before the labels do.
          opacity: 0.35 + (i / Math.max(rows.length - 1, 1)) * 0.65,
          center: ((i + 0.5) / Math.max(rows.length, 1)) * 100,
        }))

        const steps = 4
        const yTicks = []
        for (let i = 0; i <= steps; i++) {
          yTicks.push({ y: (i / steps) * 100, value: Math.round(max - (i / steps) * max) })
        }

        return { bars, yTicks }
      },

      /** Jobs against applications per city, scaled to the larger of the two. */
      pairedBars(rows, labelKey) {
        if (!rows || rows.length === 0) return []
        const max = Math.max(...rows.map((r) => Math.max(r.jobs, r.applications)), 1)
        return rows.map((r) => ({
          label: r[labelKey],
          jobs: r.jobs,
          applications: r.applications,
          jobsPct: (r.jobs / max) * 100,
          applicationsPct: (r.applications / max) * 100,
        }))
      },
      topCities() {
        return this.pairedBars(
          (this.dash.data && this.dash.data.distributions.top_cities) || [], 'city')
      },
      topRoles() {
        return this.pairedBars(
          (this.dash.data && this.dash.data.distributions.top_roles) || [], 'role')
      },

      // ── users ───────────────────────────────────────────────────────
      loadUsers(page = 1) {
        return this.loadList(this.users, '/admin/users',
          { query: this.users.q, side: this.users.side, sort: this.users.sort }, page)
      },

      openUser(id) {
        this.go('userDetail')
        this.userDetail.data = null
        this.userDetail.busy = true
        this.api('/admin/users/' + id)
          .then((res) => { this.userDetail.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.userDetail.busy = false })
      },

      revokeTokens() {
        if (!confirm('Sign this account out of every device?')) return
        this.api('/admin/users/' + this.userDetail.data.account.id + '/revoke-tokens', { method: 'POST' })
          .then((res) => this.showToast(res.message || 'Done.'))
          .catch((e) => this.showToast(e.message, true))
      },

      // ── jobs ────────────────────────────────────────────────────────
      loadJobs(page = 1) {
        return this.loadList(this.jobs, '/admin/jobs', {
          query: this.jobs.q,
          status: this.jobs.status,
          zero_applicants: this.jobs.zero,
          unverified_employer: this.jobs.unverified,
          missing_coordinates: this.jobs.missingCoords,
        }, page)
      },

      openJob(id) {
        this.go('jobDetail')
        this.jobDetail.data = null
        this.jobDetail.busy = true
        this.jobStatusPick = ''
        this.jobExpiryPick = ''
        this.api('/admin/jobs/' + id)
          .then((res) => { this.jobDetail.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.jobDetail.busy = false })
      },

      approveJob() {
        const id = this.jobDetail.data.job.id
        this.api('/admin/jobs/' + id + '/approve', { method: 'POST' })
          .then((res) => { this.showToast(res.message || 'Approved.'); this.openJob(id); this.loadNotifications() })
          .catch((e) => this.showToast(e.message, true))
      },

      rejectJob() {
        const reason = prompt('Rejection reason (min 4 characters):')
        if (!reason || reason.trim().length < 4) return
        const id = this.jobDetail.data.job.id
        this.api('/admin/jobs/' + id + '/reject', { method: 'POST', body: { reason: reason.trim() } })
          .then((res) => { this.showToast(res.message || 'Rejected.'); this.openJob(id); this.loadNotifications() })
          .catch((e) => this.showToast(e.message, true))
      },

      setJobStatus() {
        if (!this.jobStatusPick) return
        const id = this.jobDetail.data.job.id
        this.api('/admin/jobs/' + id + '/status', {
          method: 'PATCH',
          body: { status: this.jobStatusPick, reason: prompt('Reason (optional):') || '' },
        })
          .then((res) => { this.showToast(res.message || 'Updated.'); this.jobStatusPick = ''; this.openJob(id) })
          .catch((e) => this.showToast(e.message, true))
      },

      setJobExpiry() {
        const id = this.jobDetail.data.job.id
        this.api('/admin/jobs/' + id + '/expiry', {
          method: 'PATCH', body: { expires_at: this.jobExpiryPick || null },
        })
          .then((res) => { this.showToast(res.message || 'Expiry updated.'); this.openJob(id) })
          .catch((e) => this.showToast(e.message, true))
      },

      // ── applications ────────────────────────────────────────────────
      loadApplications(page = 1) {
        return this.loadList(this.apps, '/admin/applications', {
          query: this.apps.q,
          status: this.apps.status,
          stuck: this.apps.stuck,
          missing_interview: this.apps.missingInterview,
        }, page)
      },

      openApplication(reference) {
        this.go('applicationDetail')
        this.appDetail.data = null
        this.appDetail.busy = true
        this.appStatusPick = ''
        this.appStatusReason = ''
        this.api('/admin/applications/' + reference)
          .then((res) => { this.appDetail.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.appDetail.busy = false })
      },

      setAppStatus() {
        if (!this.appStatusPick || this.appStatusReason.trim().length < 3) return
        const reference = this.appDetail.data.application.reference
        this.api('/admin/applications/' + reference + '/status', {
          method: 'PATCH',
          body: { status: this.appStatusPick, reason: this.appStatusReason.trim() },
        })
          .then((res) => {
            this.showToast(res.message || 'Updated.')
            this.appStatusPick = ''
            this.appStatusReason = ''
            this.openApplication(reference)
            this.loadNotifications()
          })
          .catch((e) => this.showToast(e.message, true))
      },

      // ── organisations ───────────────────────────────────────────────
      loadOrgs(page = 1) {
        return this.loadList(this.orgs, '/admin/organisations',
          { query: this.orgs.q, state: this.orgs.state, duplicate_gst: this.orgs.dup }, page)
      },

      openOrganisation(id) {
        this.go('organisationDetail')
        this.orgDetail.data = null
        this.orgDetail.busy = true
        this.orgUnverifyReason = ''
        this.api('/admin/organisations/' + id)
          .then((res) => { this.orgDetail.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.orgDetail.busy = false })
      },

      verifyOrg() {
        const id = this.orgDetail.data.organisation.id
        this.api('/admin/organisations/' + id + '/verify', { method: 'POST' })
          .then((res) => { this.showToast(res.message || 'Verified.'); this.openOrganisation(id); this.loadNotifications() })
          .catch((e) => this.showToast(e.message, true))
      },

      unverifyOrg() {
        if (this.orgUnverifyReason.trim().length < 3) return
        const id = this.orgDetail.data.organisation.id
        this.api('/admin/organisations/' + id + '/unverify', {
          method: 'POST', body: { reason: this.orgUnverifyReason.trim() },
        })
          .then((res) => {
            this.showToast(res.message || 'Verification withdrawn.')
            this.orgUnverifyReason = ''
            this.openOrganisation(id)
            this.loadNotifications()
          })
          .catch((e) => this.showToast(e.message, true))
      },

      /**
       * Whether a verification document can be shown inline as an image, or
       * only linked out to.
       *
       * Read from the stored filename first and the URL path second — the URL
       * carries a signed query string, so the extension has to be taken from
       * the path alone. Anything unrecognised is treated as a download rather
       * than guessed at: an <img> pointed at a PDF renders as a broken image,
       * which reads as "the document is missing" when it is not.
       */
      docKind(org) {
        const source = org.document_name || (org.document_url || '').split('?')[0]
        const ext = (source.match(/\.([a-z0-9]+)$/i) || [, ''])[1].toLowerCase()

        if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'image'
        if (ext === 'pdf') return 'pdf'
        return 'other'
      },

      /** Review-checklist pill colours — pass / warn / fail. */
      checkClass(status) {
        return {
          pass: 'bg-success-bg text-success',
          warn: 'bg-warning-bg text-warning',
          fail: 'bg-danger-bg text-danger',
        }[status] || 'bg-surface-muted text-ink-secondary'
      },

      // ── subscriptions ───────────────────────────────────────────────
      loadPlans() {
        this.api('/admin/subscriptions/plans')
          .then((res) => { this.plans.data = res.data })
          .catch((e) => this.showToast(e.message, true))
      },
      loadSubs(page = 1) {
        return this.loadList(this.subs, '/admin/subscriptions',
          { audience: this.subs.audience, state: this.subs.state }, page)
      },

      // ── option lists ────────────────────────────────────────────────
      loadOptionLists() {
        this.optionLists.busy = true
        this.api('/admin/option-lists')
          .then((res) => { this.optionLists.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.optionLists.busy = false })
      },

      openOptionList(key) {
        this.optionListDetail.key = key
        this.optionListDetail.busy = true
        this.go('optionListDetail')
        this.api('/admin/option-lists/' + key)
          .then((res) => { this.optionListDetail.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.optionListDetail.busy = false })
      },

      addOptionItem() {
        const value = this.newOptionValue.trim()
        if (!value) return
        this.api('/admin/option-lists/' + this.optionListDetail.key + '/items',
          { method: 'POST', body: { value } })
          .then(() => {
            this.newOptionValue = ''
            this.showToast('Added.')
            this.openOptionList(this.optionListDetail.key)
          })
          .catch((e) => this.showToast(e.message, true))
      },

      updateOptionItem(item, patch) {
        this.api('/admin/option-lists/' + this.optionListDetail.key + '/items/' + item.id,
          { method: 'PATCH', body: patch })
          .then(() => this.showToast('Saved.'))
          .catch((e) => {
            this.showToast(e.message, true)
            this.openOptionList(this.optionListDetail.key)
          })
      },

      deleteOptionItem(item) {
        if (!confirm(`Delete “${item.value}”?`)) return
        this.api('/admin/option-lists/' + this.optionListDetail.key + '/items/' + item.id,
          { method: 'DELETE' })
          .then(() => { this.showToast('Removed.'); this.openOptionList(this.optionListDetail.key) })
          .catch((e) => this.showToast(e.message, true))
      },

      moveOptionItem(idx, dir) {
        const items = this.optionListDetail.data.items
        const target = idx + dir
        if (target < 0 || target >= items.length) return
        const arr = items.slice()
        ;[arr[idx], arr[target]] = [arr[target], arr[idx]]
        this.optionListDetail.data.items = arr
        this.api('/admin/option-lists/' + this.optionListDetail.key + '/reorder',
          { method: 'PUT', body: { ids: arr.map((i) => i.id) } })
          .then(() => this.showToast('Order saved.'))
          .catch((e) => {
            this.showToast(e.message, true)
            this.openOptionList(this.optionListDetail.key)
          })
      },

      resetOptionList() {
        if (!confirm('Reset this list to the shipped defaults? This removes every edit.')) return
        this.api('/admin/option-lists/' + this.optionListDetail.key + '/override', { method: 'DELETE' })
          .then(() => { this.showToast('Reverted to defaults.'); this.openOptionList(this.optionListDetail.key) })
          .catch((e) => this.showToast(e.message, true))
      },

      // ── content ─────────────────────────────────────────────────────
      loadPages() {
        this.pages.busy = true
        this.api('/admin/content/pages')
          .then((res) => { this.pages.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.pages.busy = false })
      },

      savePage(page) {
        this.api('/admin/content/pages/' + page.slug,
          { method: 'PATCH', body: { title: page.title, body: page.body } })
          .then(() => this.showToast('Saved.'))
          .catch((e) => this.showToast(e.message, true))
      },

      loadFaqs() {
        this.faqs.busy = true
        this.api('/admin/content/faqs')
          .then((res) => { this.faqs.data = res.data })
          .catch((e) => this.showToast(e.message, true))
          .finally(() => { this.faqs.busy = false })
      },

      addFaq() {
        if (!this.newFaq.question.trim() || !this.newFaq.answer.trim()) return
        this.api('/admin/content/faqs', { method: 'POST', body: this.newFaq })
          .then(() => {
            this.newFaq = { question: '', answer: '' }
            this.addFaqOpen = false
            this.showToast('Added.')
            this.loadFaqs()
          })
          .catch((e) => this.showToast(e.message, true))
      },

      saveFaq(faq) {
        this.api('/admin/content/faqs/' + faq.id, {
          method: 'PATCH',
          body: { question: faq.question, answer: faq.answer, is_active: faq.is_active },
        })
          .then(() => this.showToast('Saved.'))
          .catch((e) => this.showToast(e.message, true))
      },

      deleteFaq(faq) {
        if (!confirm('Delete this FAQ?')) return
        this.api('/admin/content/faqs/' + faq.id, { method: 'DELETE' })
          .then(() => { this.showToast('Removed.'); this.loadFaqs() })
          .catch((e) => this.showToast(e.message, true))
      },

      moveFaq(idx, dir) {
        const arr = this.faqs.data.slice()
        const target = idx + dir
        if (target < 0 || target >= arr.length) return
        ;[arr[idx], arr[target]] = [arr[target], arr[idx]]
        this.faqs.data = arr
        this.api('/admin/content/faqs/reorder', { method: 'PUT', body: { ids: arr.map((f) => f.id) } })
          .then(() => this.showToast('Order saved.'))
          .catch((e) => { this.showToast(e.message, true); this.loadFaqs() })
      },

      // ── notifications ───────────────────────────────────────────────
      /**
       * The event feed, and the numbers the sidebar badges read.
       *
       * Failures are swallowed: this also runs on sign-in purely to populate
       * the badge, and a dropped request there should cost a count, not put an
       * error in front of an operator who asked for the dashboard.
       */
      loadNotifications(page = 1, { quiet = false } = {}) {
        // `quiet` is for the background poll: flipping `busy` there would dim
        // the page under an operator who did not ask for anything.
        if (!quiet) this.notifications.busy = true

        return this.api(`/admin/notifications?page=${page}&per_page=30`)
          .then((res) => {
            const rows = res.data || []

            // Diffed before the assignment, against ids rather than a count:
            // an employer getting verified while a job is posted leaves the
            // total unchanged and would hide both.
            if (page === 1) this._announce(rows)

            this.notifications.data = rows
            this.notifications.meta = res.meta
            this.notifications.delivery = res.delivery
            this.notifications.windowDays = res.window_days
            this.actionTotal = res.action_total || 0
            this.openWork = res.open || {}
          })
          .catch(() => { /* leave the last known counts in place */ })
          .finally(() => { if (!quiet) this.notifications.busy = false })
      },

      // ── desktop alerts ──────────────────────────────────────────────
      //
      // The panel is a page, not an app, so there is no OS-level push to
      // receive. What there is: while a tab is open it can poll and raise a
      // real system notification, which is what an operator with the panel
      // open in another window actually needs.
      //
      // Deliberately not silent-failing into nothing: `alertsState` drives a
      // control in the top bar, so "notifications are off" is visible rather
      // than something an operator assumes is working.

      /** 'unsupported' | 'default' | 'granted' | 'denied' */
      get alertsState() {
        if (typeof Notification === 'undefined') return 'unsupported'
        return Notification.permission
      },

      /**
       * Asks the browser for permission.
       *
       * Must be called from a click: browsers ignore (and Chrome penalises) a
       * permission prompt raised on page load, so this is wired to a button
       * rather than to `init`.
       */
      async enableDesktopAlerts() {
        if (typeof Notification === 'undefined') return
        try {
          await Notification.requestPermission()
          // `Notification.permission` is not reactive, so nudge Alpine.
          this.alertsTick++
        } catch (e) {
          /* a refusal is an answer, not an error */
        }
      },

      /** Bumped purely to re-render the top bar after a permission change. */
      alertsTick: 0,

      /** Ids already seen, so a poll only announces what actually arrived. */
      _seen: null,

      /**
       * Raises a system notification for rows that were not in the last poll.
       *
       * The first pass only records what is already there. Announcing on the
       * first load would fire a burst of notifications for a backlog the
       * operator has very likely already dealt with — which is how people learn
       * to dismiss these without reading them.
       */
      _announce(rows) {
        const ids = rows.map((r) => r.id)

        if (this._seen === null) {
          this._seen = new Set(ids)
          return
        }

        const fresh = rows.filter((r) => !this._seen.has(r.id))
        ids.forEach((id) => this._seen.add(id))

        if (fresh.length === 0) return
        if (this.alertsState !== 'granted') return

        // Three at most, then one line for the rest. A dozen at once is a
        // notification centre nobody reads to the bottom of.
        fresh.slice(0, 3).forEach((row) => this._systemNotify(row))

        if (fresh.length > 3) {
          this._systemNotify({
            id: 'more-' + Date.now(),
            title: `${fresh.length - 3} more updates`,
            detail: 'Open Notifications to see everything.',
            link: { view: 'notifications' },
          })
        }
      },

      _systemNotify(row) {
        try {
          const note = new Notification(row.title, {
            body: row.detail || '',
            // Keyed per row so the OS replaces rather than stacks a repeat.
            tag: 'inthes-admin-' + row.id,
            icon: '/brand/inthes-mark.png',
          })

          note.onclick = () => {
            window.focus()
            this.openNotification(row)
            note.close()
          }
        } catch (e) {
          /* the OS can refuse; the in-panel badge still updated */
        }
      },

      /**
       * Background poll, started once a session exists.
       *
       * Kept running while the tab is hidden on purpose — that is exactly when
       * a system notification is worth anything. The interval is slack because
       * the feed is derived from three table reads and nothing here is urgent
       * to the second.
       */
      _startPolling() {
        clearInterval(this._poll)
        this._poll = setInterval(() => {
          if (!this.token) return
          this.loadNotifications(1, { quiet: true })
        }, 45000)
      },

      _poll: null,
    }
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('adminApp', adminApp)
  })
})()
