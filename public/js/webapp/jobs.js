/**
 * Job seeker: Home feed, Jobs (results + filters), Search, Job detail, Apply,
 * Success, Saved, My Applications and Track — the app's dashboard tabs.
 *
 * Apply is the app's `applyToJob` decision, in the same order: already
 * applied → toast; own posting → its applicant list; signed out → sign in and
 * come back; profile short of what the job needs → Create profile in gated
 * mode; otherwise submit. The server re-checks every one of those, so this is
 * routing, never the rule itself.
 */
;(function () {
  const RECENT_KEY = 'inthes.recentSearches'
  const { store } = window.WebApp.helpers

  /** The `ProfileField` gate, as `isSatisfiedBy` reads the profile. */
  const SATISFIED = {
    name: (p) => !!(p.name || '').trim(),
    qualification: (p) => !!p.qualification,
    experience: (p) => !!p.experience,
    location: (p) => Array.isArray(p.location) ? p.location.length > 0 : !!p.location,
    resume: (p) => !!p.resume,
    gender: (p) => !!p.gender,
    dob: (p) => !!p.dob,
    address: (p) => !!(p.address || '').trim(),
  }
  const ALWAYS_REQUIRED = ['name', 'gender', 'dob', 'address']

  const FIELD_LABEL = {
    name: 'Name', gender: 'Gender', dob: 'Date of birth', address: 'Address',
    qualification: 'Qualification', experience: 'Experience', location: 'Preferred location', resume: 'Resume',
  }

  /** Application status, the product's own words — never "rejected" to a candidate. */
  const APP_STATUS = {
    applied: { label: 'Applied', note: 'Waiting for the employer to review', tone: 'bg-info-bg text-info' },
    shortlisted: { label: 'Shortlisted', note: 'The employer is interested in you', tone: 'bg-primary-light text-primary-dark' },
    selected: { label: 'Selected', note: 'You got the job', tone: 'bg-success-bg text-success' },
    rejected: { label: 'Not selected', note: 'The employer went with someone else', tone: 'bg-surface-muted text-ink-secondary' },
  }

  const APP_TABS = [
    { key: '', label: 'All' },
    { key: 'applied', label: 'Applied' },
    { key: 'shortlisted', label: 'Shortlisted' },
    { key: 'selected', label: 'Selected' },
    { key: 'rejected', label: 'Not selected' },
  ]

  const blankFilters = () => ({ city: [], experience: [], salary: [], job_type: [], shift: [] })

  /** "₹20K+" → 20000, "₹1L+" → 100000. */
  function salaryFloor(label) {
    const m = String(label).replace(/[₹,+\s]/g, '').match(/^([\d.]+)([KL]?)$/i)
    if (!m) return null
    const n = Number(m[1])
    return Math.round(m[2].toUpperCase() === 'L' ? n * 100000 : m[2] ? n * 1000 : n)
  }

  window.WebApp.part({
    APP_TABS,

    // ── shared job state ──────────────────────────────────────────────
    jobCategories: [],
    savedIds: [],
    appliedJobIds: [],

    // Home
    homeCategory: '',
    homeFeed: { list: [], busy: false, error: false },

    // Jobs (results)
    res: { query: '', category: '', list: [], page: 1, totalPages: 1, total: 0, busy: false, error: false },
    resFilters: { city: [], experience: [], salary: [], job_type: [], shift: [] },
    filterOpen: false,
    filterDraft: { city: [], experience: [], salary: [], job_type: [], shift: [] },
    filterPreview: null,
    _filterPreviewTimer: null,

    // Search
    srchQ: '',
    srchSuggestions: [],
    srchTrending: [],
    srchRecent: [],
    _srchTimer: null,

    // Job detail
    jd: { job: null, busy: false, error: '' },

    // Apply / success / track
    applyBusy: false,
    applyMsg: '',
    succ: { app: null },
    trk: { app: null, busy: false, error: false },

    // Saved / applications
    savedList: { list: [], busy: false, error: false },
    apps: { list: [], busy: false, error: false, tab: '' },

    _bootJobs() {
      this.srchRecent = JSON.parse(store.get(RECENT_KEY) || '[]')
      this.api('/jobs/categories').then((r) => { this.jobCategories = r.data || [] }).catch(() => {})
      if (this.token) this._loadCandidateState()
    },

    _afterSignInJobs() { this._loadCandidateState() },

    _onSignOutJobs() {
      this.savedIds = []
      this.appliedJobIds = []
      this.savedList = { list: [], busy: false, error: false }
      this.apps = { list: [], busy: false, error: false, tab: '' }
    },

    /** Saved ids and applied job ids drive every card's buttons. */
    _loadCandidateState() {
      this.api('/candidate/saved-jobs').then((r) => {
        this.savedList.list = r.data || []
        this.savedIds = this.savedList.list.map((j) => j.id)
      }).catch(() => {})
      this.api('/applications').then((r) => {
        this.apps.list = r.data || []
        this.appliedJobIds = this.apps.list.map((a) => a.job_id || a.job?.id)
      }).catch(() => {})
    },

    // ── job helpers ───────────────────────────────────────────────────
    isSaved(job) { return this.savedIds.includes(job.id) },
    hasApplied(job) { return this.appliedJobIds.includes(job.id) || !!job.has_applied },

    matchTone(p) {
      if (p >= 75) return { label: 'Great match', cls: 'bg-success-bg text-success' }
      if (p >= 50) return { label: 'Good match', cls: 'bg-warning-bg text-warning' }
      return { label: 'Partial match', cls: 'bg-surface-muted text-ink-secondary' }
    },

    isNewJob(job) {
      return job.posted_at && (Date.now() - new Date(job.posted_at).getTime()) <= 14 * 86400000
    },

    postedLabel(iso) {
      if (!iso) return ''
      const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000)
      if (days <= 0) return 'today'
      if (days === 1) return '1 day ago'
      return `${days} days ago`
    },

    jobSalary(job) { return job.salary_display || job.salary || '' },

    /** The fields this job needs that the profile does not yet satisfy. */
    missingFor(job) {
      const p = this.me || {}
      const fields = [...new Set([...ALWAYS_REQUIRED, ...(job?.required_fields || [])])]
      return fields.filter((f) => SATISFIED[f] && !SATISFIED[f](p))
    },

    fieldLabel(f) { return FIELD_LABEL[f] || f },

    async toggleSave(job) {
      if (!this.requireAuth('/job/' + job.id)) return
      const wasSaved = this.isSaved(job)
      // Optimistic, like the app's heart.
      if (wasSaved) {
        this.savedIds = this.savedIds.filter((id) => id !== job.id)
      } else {
        this.savedIds = [...this.savedIds, job.id]
      }
      try {
        if (wasSaved) await this.api('/candidate/saved-jobs/' + job.id, { method: 'DELETE' })
        else await this.api('/candidate/saved-jobs', { method: 'POST', body: { job_id: job.id } })
        this.showToast(wasSaved ? 'Removed from saved jobs' : 'Job saved')
        if (this.is('saved')) this.onSaved()
      } catch (e) {
        this.savedIds = wasSaved ? [...this.savedIds, job.id] : this.savedIds.filter((id) => id !== job.id)
        this.showToast(e.message, true)
      }
    },

    shareJob(job) {
      this.share(job.title, job.share_url || (location.origin + '/jobs/' + job.code))
    },

    /** The app's `applyToJob`. */
    applyTo(job) {
      if (this.token && this.hasApplied(job)) { this.showToast('You have already applied to this job'); return }
      if (job.own_posting) { this.go('/r/applicants/' + job.id); return }
      if (!this.requireAuth('/apply/' + job.id)) return
      this.go('/apply/' + job.id)
    },

    applyCta(job) {
      if (job.own_posting) return 'View applicants'
      if (this.token && this.hasApplied(job)) return 'Applied ✓'
      if (!this.token) return 'Apply now'
      return this.missingFor(job).length ? 'Finish my profile to apply' : 'Apply'
    },

    readinessLine(job) {
      if (job.own_posting) return 'This is your own posting'
      if (this.token && this.hasApplied(job)) {
        const a = this.apps.list.find((x) => (x.job_id || x.job?.id) === job.id)
        return a ? 'You applied on ' + this.fmtDate(a.applied_at) : 'You have applied to this job'
      }
      if (!this.token) return 'Sign in takes one OTP'
      const n = this.missingFor(job).length
      return n ? `This job needs ${n} more detail${n > 1 ? 's' : ''}` : 'Everything this job needs is already in your profile'
    },

    whatsappLink(job) {
      const phone = (job.whatsapp_phone || job.contact_phone || '').replace(/\D/g, '')
      const text = `Hi, I saw your "${job.title}" job on INTHES and I am interested.`
      return `https://wa.me/91${phone}?text=${encodeURIComponent(text)}`
    },

    canCall(job) { return !!job.contact_phone && job.contact_mode !== 'apply_only' },
    canWhatsapp(job) { return !!(job.whatsapp_phone || job.contact_phone) && job.contact_mode === 'calls_whatsapp' },

    // ── Home ──────────────────────────────────────────────────────────
    get homeTitle() { return this.token ? 'Recommended for you' : 'Latest openings' },

    onHome() { this.loadHomeFeed() },

    pickHomeCategory(name) {
      this.homeCategory = this.homeCategory === name ? '' : name
      this.loadHomeFeed()
    },

    async loadHomeFeed() {
      this.homeFeed.busy = true
      this.homeFeed.error = false
      try {
        const r = await this.api('/jobs' + this.qs({
          category: this.homeCategory || null,
          recommended: this.token ? 1 : null,
          per_page: 20,
        }))
        let list = r.data || []
        if (this.token) list = [...list].sort((a, b) => (b.match_percent ?? 0) - (a.match_percent ?? 0))
        this.homeFeed.list = list.slice(0, 5)
      } catch (e) {
        this.homeFeed.error = true
      }
      this.homeFeed.busy = false
    },

    // ── Jobs (results) ────────────────────────────────────────────────
    get filterGroups() { return this.opt('job_filters') },

    get activeFilterCount() {
      return Object.values(this.resFilters).reduce((n, v) => n + (v.length ? 1 : 0), 0)
    },

    _filterParams(f) {
      const params = {}
      for (const g of this.filterGroups) {
        const picked = f[g.key] || []
        if (!picked.length) continue
        if (g.type === 'min') {
          const floors = picked.map(salaryFloor).filter((n) => n != null)
          if (floors.length) params[g.param] = Math.min(...floors)
        } else {
          params[g.param] = picked
        }
      }
      return params
    },

    onResults(_, query) {
      this.res.query = query.q || ''
      this.res.category = query.category || ''
      this.loadResults(1)
    },

    async loadResults(page = 1) {
      this.res.busy = true
      this.res.error = false
      try {
        const r = await this.api('/jobs' + this.qs({
          query: this.res.query || null,
          category: this.res.category || null,
          recommended: this.token ? 1 : null,
          page,
          per_page: 20,
          ...this._filterParams(this.resFilters),
        }))
        let list = r.data || []
        if (this.token) list = [...list].sort((a, b) => (b.match_percent ?? 0) - (a.match_percent ?? 0))
        this.res.list = page === 1 ? list : [...this.res.list, ...list]
        this.res.page = r.meta?.page || page
        this.res.totalPages = r.meta?.total_pages || 1
        this.res.total = r.meta?.total ?? this.res.list.length
      } catch (e) {
        this.res.error = true
      }
      this.res.busy = false
    },

    pickResultsCategory(name) {
      this.res.category = this.res.category === name ? '' : name
      this.loadResults(1)
    },

    clearSearch() { this.go('/jobs') },

    openFilters() {
      this.filterDraft = this.clone(this.resFilters)
      this.filterOpen = true
      this.previewFilters()
    },

    toggleFilter(key, value) {
      this.toggleIn(this.filterDraft[key], value)
      this.previewFilters()
    },

    /** "Show N jobs" — a one-row probe that reads `meta.total`. */
    previewFilters() {
      clearTimeout(this._filterPreviewTimer)
      this._filterPreviewTimer = setTimeout(async () => {
        try {
          const r = await this.api('/jobs' + this.qs({
            query: this.res.query || null,
            category: this.res.category || null,
            per_page: 1,
            ...this._filterParams(this.filterDraft),
          }))
          this.filterPreview = r.meta?.total ?? null
        } catch (e) { this.filterPreview = null }
      }, 200)
    },

    clearFilters() {
      this.filterDraft = blankFilters()
      this.previewFilters()
    },

    applyFilters() {
      this.resFilters = this.clone(this.filterDraft)
      this.filterOpen = false
      if (this.is('results')) this.loadResults(1)
      else this.go('/jobs')
    },

    resetAllFilters() {
      this.resFilters = blankFilters()
      this.loadResults(1)
    },

    // ── Search ────────────────────────────────────────────────────────
    onSearch(_, query) {
      this.srchQ = query.q || ''
      this.srchSuggestions = []
      this.api('/jobs/search/trending').then((r) => { this.srchTrending = r.data || [] }).catch(() => {})
      if (this.srchQ) this.searchType()
    },

    searchType() {
      clearTimeout(this._srchTimer)
      const q = this.srchQ.trim()
      if (!q) { this.srchSuggestions = []; return }
      this._srchTimer = setTimeout(async () => {
        try {
          const r = await this.api('/jobs/search/suggestions' + this.qs({ q }))
          if (this.srchQ.trim() === q) this.srchSuggestions = r.data || []
        } catch (e) { /* keep the last list */ }
      }, 180)
    },

    runSearch(term) {
      const q = String(term ?? this.srchQ).trim()
      if (!q) return
      this.srchRecent = [q, ...this.srchRecent.filter((x) => x.toLowerCase() !== q.toLowerCase())].slice(0, 8)
      store.set(RECENT_KEY, JSON.stringify(this.srchRecent))
      this.go('/jobs?q=' + encodeURIComponent(q))
    },

    clearRecent() {
      this.srchRecent = []
      store.set(RECENT_KEY, null)
    },

    // ── Job detail ────────────────────────────────────────────────────
    async onJobDetail({ id }) {
      this.jd = { job: null, busy: true, error: '' }
      try {
        const r = await this.api('/jobs/' + encodeURIComponent(id))
        this.jd.job = r.data
      } catch (e) {
        this.jd.error = e.status === 404 ? 'This job is no longer available.' : 'Something went wrong'
      }
      this.jd.busy = false
    },

    // ── Apply ─────────────────────────────────────────────────────────
    /**
     * The submit step. Asks the server what this job still needs first —
     * the job's `required_fields` may have changed since the card was drawn.
     */
    async onApply({ id }) {
      this.applyBusy = true
      this.applyMsg = 'Checking your profile…'
      try {
        const req = (await this.api('/applications/requirements/' + encodeURIComponent(id))).data
        if (req.own_posting) { this.go('/r/applicants/' + req.job_id, true); return }
        if (req.already_applied || this.appliedJobIds.includes(req.job_id)) {
          this.showToast('You have already applied to this job')
          this.go('/job/' + req.job_id, true)
          return
        }
        if (req.missing_fields?.length) {
          this.go('/onboarding?job=' + encodeURIComponent(req.job_id) + '&missing=' + req.missing_fields.join(','), true)
          return
        }

        this.applyMsg = 'Sending your application…'
        const res = await this.api('/applications', { method: 'POST', body: { job_id: req.job_id } })
        const app = res.data
        this.appliedJobIds = [...this.appliedJobIds, req.job_id]
        this.apps.list = [app, ...this.apps.list]
        this.succ.app = app
        this.go('/success/' + encodeURIComponent(app.id), true)
      } catch (e) {
        this.showToast(e.message || "Couldn't submit your application. Please try again.", true)
        // A 422 here is the server finding the profile short after all.
        if (e.status === 422 && e.errors?.profile) {
          this.go('/onboarding?job=' + encodeURIComponent(id), true)
          return
        }
        this.go('/job/' + id, true)
      } finally {
        this.applyBusy = false
      }
    },

    async onSuccess({ id }) {
      if (this.succ.app?.id === id) return
      try {
        this.succ.app = (await this.api('/applications/' + encodeURIComponent(id))).data
      } catch (e) { this.go('/applied', true) }
    },

    // ── Saved ─────────────────────────────────────────────────────────
    async onSaved() {
      this.savedList.busy = !this.savedList.list.length
      this.savedList.error = false
      try {
        const r = await this.api('/candidate/saved-jobs')
        this.savedList.list = r.data || []
        this.savedIds = this.savedList.list.map((j) => j.id)
      } catch (e) { this.savedList.error = true }
      this.savedList.busy = false
    },

    // ── My Applications ───────────────────────────────────────────────
    async onApplied() {
      this.apps.busy = !this.apps.list.length
      this.apps.error = false
      try {
        const r = await this.api('/applications')
        this.apps.list = r.data || []
        this.appliedJobIds = this.apps.list.map((a) => a.job_id || a.job?.id)
      } catch (e) { this.apps.error = true }
      this.apps.busy = false
    },

    get appsFiltered() {
      return this.apps.tab ? this.apps.list.filter((a) => a.status === this.apps.tab) : this.apps.list
    },

    appsCount(key) {
      return key ? this.apps.list.filter((a) => a.status === key).length : this.apps.list.length
    },

    appStatus(status) { return APP_STATUS[status] || { label: status, note: '', tone: 'bg-surface-muted text-ink-secondary' } },

    // ── Track ─────────────────────────────────────────────────────────
    async onTrack({ id }) {
      this.trk = { app: null, busy: true, error: false }
      try {
        this.trk.app = (await this.api('/applications/' + encodeURIComponent(id))).data
      } catch (e) { this.trk.error = true }
      this.trk.busy = false
    },

    /** The three pipeline steps, each done / current / ahead. */
    trackSteps(app) {
      if (!app) return []
      const at = Object.fromEntries((app.timeline || []).map((t) => [t.stage, t.at]))
      const order = ['applied', 'shortlisted', 'selected']
      const reached = app.status === 'rejected'
        ? Math.max(0, ...order.map((s, i) => (at[s] ? i : -1)))
        : order.indexOf(app.status)
      return order.map((stage, i) => ({
        stage,
        label: APP_STATUS[stage].label,
        at: at[stage] || null,
        done: i <= reached,
        current: i === reached && app.status !== 'rejected',
      }))
    },

    rejectedAt(app) {
      return (app?.timeline || []).find((t) => t.stage === 'rejected')?.at || app?.stage_updated_at
    },

    interviewTypeLabel(t) { return t === 'online' ? 'Online' : t === 'inPerson' ? 'In person' : (t || '') },
  })
})()
