/**
 * /jobs — search, filters, results and the selected job side by side.
 *
 * The URL is the state: `?q=&category=&city[]=…&min_salary=&sort=&job=` —
 * the same names `GET /api/v1/jobs` takes — rewritten with replaceState on
 * every change, so a filtered view can be shared and survives a reload. The
 * server renders the first page into `props.initial`; signed-in visitors get
 * a fresh fetch with their token (match %, saved, applied, recommended order).
 *
 * Below 1024px there is no split: a row is a plain link to /jobs/{code} and
 * the filters open as a full-screen sheet.
 */
document.addEventListener('alpine:init', () => {
  const RECENT_KEY = 'inthes.recentSearches'
  const PER_PAGE = 20

  Alpine.data('jobsBrowse', (props) => ({
    kit: Inthes.jobsKit,
    groups: props.groups || [],
    categories: props.categories || [],

    q: props.state.q || '',
    /** The search actually applied (`q` is what is being typed). */
    query: props.state.q || '',
    category: props.state.category || '',
    sort: props.state.sort || 'relevant',
    filters: Inthes.clone(props.state.filters || {}),

    list: props.initial.list || [],
    page: props.initial.meta?.page || 1,
    totalPages: props.initial.meta?.total_pages || 1,
    total: props.initial.meta?.total ?? (props.initial.list || []).length,
    busy: false,
    error: false,
    _seq: 0,
    _keepSelection: !!props.state.job,
    _timer: null,

    selectedId: props.state.job || '',
    detail: null,
    detailBusy: false,
    detailMissing: false,

    isDesktop: window.matchMedia('(min-width: 1024px)').matches,
    sheetOpen: false,

    // typeahead
    sugOpen: false,
    sugItems: [],
    trending: [],
    recent: [],
    sugIdx: -1,
    _sugTimer: null,

    init() {
      const mq = window.matchMedia('(min-width: 1024px)')
      mq.addEventListener('change', (e) => { this.isDesktop = e.matches; if (e.matches) { this.sheetOpen = false; this._autoSelect() } })
      try { this.recent = JSON.parse(Inthes.store.get(RECENT_KEY) || '[]') } catch (e) { this.recent = [] }
      this._autoSelect()
      // Canonical names in the address bar (old `role=` / `city=` links included).
      this._syncUrl()
      this.$watch('sheetOpen', (v) => { document.documentElement.style.overflow = v ? 'hidden' : '' })

      Inthes.whenReady().then(() => {
        if (this.$store.auth.signedIn) {
          this.$store.jobs.ensure().catch(() => {})
          this.load(1)
        } else if (props.state.page > 1) {
          this.load(1)
        }
      })
      window.addEventListener('inthes:signed-in', () => { this.$store.jobs.ensure().catch(() => {}); this.load(1) })
      window.addEventListener('inthes:signed-out', () => this.load(1))
      window.addEventListener('inthes:applied', (e) => {
        const mark = (j) => { if (j && j.id === e.detail.job_id) j.has_applied = true }
        this.list.forEach(mark)
        mark(this.detail)
      })
    },

    // ── query ─────────────────────────────────────────────────────────
    _apiParams(page) {
      return {
        query: this.query || null,
        category: this.category || null,
        recommended: this.$store.auth.signedIn && this.sort === 'relevant' ? 1 : null,
        page,
        per_page: PER_PAGE,
        ...this.filters,
      }
    },

    _syncUrl() {
      const params = { q: this.query || null, category: this.category || null, ...this.filters, sort: this.sort === 'newest' ? 'newest' : null }
      if (this.isDesktop && this.selectedId) params.job = this.selectedId
      history.replaceState(null, '', location.pathname + Inthes.qs(params))
    },

    async load(page = 1) {
      const seq = ++this._seq
      this.busy = true
      this.error = false
      try {
        const r = await Inthes.api('/jobs' + Inthes.qs(this._apiParams(page)))
        if (seq !== this._seq) return
        let rows = r.data || []
        if (this.$store.auth.signedIn && this.sort === 'relevant') rows = [...rows].sort((a, b) => (b.match_percent ?? 0) - (a.match_percent ?? 0))
        this.list = page === 1 ? rows : [...this.list, ...rows.filter((j) => !this.list.some((x) => x.id === j.id))]
        this.page = r.meta?.page || page
        this.totalPages = r.meta?.total_pages || 1
        this.total = r.meta?.total ?? this.list.length
        if (page === 1) {
          if (this.selectedId && !this.list.some((j) => j.id === this.selectedId) && !this._keepSelection) this.selectedId = ''
          this._keepSelection = false
          this._autoSelect()
          this._syncUrl()
        }
      } catch (e) {
        if (seq === this._seq) this.error = true
      }
      if (seq === this._seq) this.busy = false
    },

    /** Any filter / search / sort change: URL first, then a debounced reload. */
    changed() {
      this.selectedId = ''
      this._syncUrl()
      clearTimeout(this._timer)
      this._timer = setTimeout(() => this.load(1), 150)
    },

    loadMore() { if (!this.busy && this.page < this.totalPages) this.load(this.page + 1) },

    // ── filters ───────────────────────────────────────────────────────
    isOn(g, o) {
      if (g.type === 'min') return String(this.filters[g.param] || '') === String(Inthes.jobsKit.salaryFloor(o))
      return (this.filters[g.param] || []).includes(o)
    },
    toggle(g, o) {
      if (g.type === 'min') {
        const v = String(Inthes.jobsKit.salaryFloor(o))
        this.filters[g.param] = this.filters[g.param] === v ? '' : v
      } else {
        const list = [...(this.filters[g.param] || [])]
        const i = list.indexOf(o)
        if (i === -1) list.push(o); else list.splice(i, 1)
        this.filters[g.param] = list
      }
      this.changed()
    },
    groupCount(g) {
      const v = this.filters[g.param]
      return Array.isArray(v) ? v.length : (v ? 1 : 0)
    },
    get activeCount() {
      return this.groups.reduce((n, g) => n + this.groupCount(g), 0) + (this.category ? 1 : 0)
    },
    /** The removable chips above the results. */
    get chips() {
      const out = []
      if (this.query) out.push({ key: 'q', label: '“' + this.query + '”' })
      if (this.category) out.push({ key: 'category', label: this.category })
      for (const g of this.groups) {
        const v = this.filters[g.param]
        if (g.type === 'min') {
          if (v) {
            const label = (g.options || []).find((o) => String(Inthes.jobsKit.salaryFloor(o)) === String(v))
            out.push({ key: g.param, label: label || '₹' + Inthes.fmtNumber(v) + '+' })
          }
        } else (v || []).forEach((x) => out.push({ key: g.param, value: x, label: x }))
      }
      return out
    },
    removeChip(c) {
      if (c.key === 'q') { this.q = ''; this.query = '' }
      else if (c.key === 'category') this.category = ''
      else if (c.value !== undefined) this.filters[c.key] = (this.filters[c.key] || []).filter((x) => x !== c.value)
      else this.filters[c.key] = ''
      this.changed()
    },
    clearAll() {
      for (const g of this.groups) this.filters[g.param] = g.type === 'min' ? '' : []
      this.category = ''
      this.query = ''
      this.q = ''
      this.changed()
    },

    // ── selection ─────────────────────────────────────────────────────
    _autoSelect() {
      if (!this.isDesktop) return
      if (!this.selectedId && this.list.length) this.selectedId = this.list[0].id
      if (this.selectedId) this.openDetail(this.selectedId, false)
    },

    /** Row click: on desktop it fills the panel; on phones the link just navigates. */
    pick(job, ev) {
      if (!this.isDesktop || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button === 1) return
      ev.preventDefault()
      if (this.selectedId === job.id) return
      this.selectedId = job.id
      this._syncUrl()
      this.openDetail(job.id, true)
    },

    async openDetail(id, scroll) {
      const fromList = this.list.find((j) => j.id === id)
      if (!this.detail || this.detail.id !== id) this.detail = fromList ? Inthes.clone(fromList) : null
      this.detailMissing = false
      if (scroll && this.$refs.detail) this.$refs.detail.scrollTop = 0
      this.detailBusy = !this.detail
      try {
        const r = await Inthes.api('/jobs/' + encodeURIComponent(id))
        if (this.selectedId === id) this.detail = r.data
      } catch (e) {
        if (this.selectedId === id && !this.detail) this.detailMissing = true
      }
      this.detailBusy = false
    },

    // ── search box ────────────────────────────────────────────────────
    focusSearch() {
      this.sugOpen = true
      this.sugIdx = -1
      if (!this.trending.length) Inthes.api('/jobs/search/trending').then((r) => { this.trending = r.data || [] }).catch(() => {})
      this.typed()
    },
    typed() {
      clearTimeout(this._sugTimer)
      this.sugIdx = -1
      const q = this.q.trim()
      if (!q) { this.sugItems = []; return }
      this._sugTimer = setTimeout(async () => {
        try {
          const r = await Inthes.api('/jobs/search/suggestions' + Inthes.qs({ q }))
          if (this.q.trim() === q) this.sugItems = r.data || []
        } catch (e) { /* keep the last list */ }
      }, 180)
    },
    /** What the dropdown shows: suggestions while typing, else recent + trending. */
    get sugRows() {
      if (this.q.trim()) return this.sugItems.map((s) => ({ term: s.term, meta: s.job_count + ' jobs', icon: 'search' }))
      return [
        ...this.recent.slice(0, 4).map((t) => ({ term: t, meta: 'Recent', icon: 'history' })),
        ...this.trending.filter((t) => !this.recent.includes(t.term)).map((t) => ({ term: t.term, meta: t.job_count + ' jobs', icon: 'trendingUp' })),
      ]
    },
    sugKey(ev) {
      const n = this.sugRows.length
      if (ev.key === 'ArrowDown') { ev.preventDefault(); this.sugOpen = true; this.sugIdx = n ? (this.sugIdx + 1) % n : -1 }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); this.sugIdx = n ? (this.sugIdx - 1 + n) % n : -1 }
      else if (ev.key === 'Escape') { this.sugOpen = false }
    },
    runSearch(term) {
      if (term === undefined && this.sugOpen && this.sugIdx >= 0 && this.sugRows[this.sugIdx]) term = this.sugRows[this.sugIdx].term
      if (term !== undefined) this.q = term
      this.query = this.q.trim()
      const q = this.q.trim()
      if (q) {
        this.recent = [q, ...this.recent.filter((x) => x.toLowerCase() !== q.toLowerCase())].slice(0, 8)
        Inthes.store.set(RECENT_KEY, JSON.stringify(this.recent))
      }
      this.sugOpen = false
      this.$refs.search?.blur()
      this.changed()
    },
    clearSearch() { this.q = ''; this.query = ''; this.sugItems = []; this.changed() },

    get heading() {
      if (this.query) return 'Results for “' + this.query + '”'
      if (this.category) return this.category + ' jobs'
      return this.$store.auth.signedIn && this.sort === 'relevant' ? 'Jobs for you' : 'All healthcare jobs'
    },
  }))
})
