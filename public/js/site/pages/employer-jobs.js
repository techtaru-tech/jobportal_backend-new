/**
 * /employer/jobs — every posting (My Posted Jobs), as a table: status tabs
 * with counts, a company filter and a search, all kept in the query string.
 *
 * The company filter: an employer posts for several companies — their own
 * and others they hire for — and narrows the list to one of them (the
 * app's My Jobs chips, MyJobsController.companyFilter).
 */
document.addEventListener('alpine:init', () => {
  const TABS = [
    { key: '', label: 'All' },
    { key: 'active', label: 'Active' },
    { key: 'pending_approval', label: 'Pending approval' },
    { key: 'paused', label: 'Paused' },
    { key: 'draft', label: 'Draft' },
    { key: 'closed', label: 'Closed' },
    { key: 'rejected', label: 'Rejected' },
  ]
  // Expired postings sit with closed ones — both have stopped taking applications.
  const inTab = (job, key) => !key || job.posting_status === key || (key === 'closed' && job.posting_status === 'expired')
  // The company a posting was filed under: its id, or for one from before
  // companies were records, its name (MyJobsController.companyKey).
  const companyKey = (job) => job.organisation_id || 'name:' + String(job.organisation || '').trim().toLowerCase()

  Alpine.data('empJobs', () => ({
    ...Emp.helpers,
    ...Emp.jobActions(),
    TABS,
    loading: true,
    error: '',
    jobs: [],
    status: '',
    company: '',
    q: '',

    async init() {
      const p = new URLSearchParams(location.search)
      this.status = TABS.some((t) => t.key === p.get('status')) ? p.get('status') : ''
      this.q = p.get('q') || ''
      this.company = p.get('company') || ''
      this.$watch('status', () => this.syncUrl())
      this.$watch('company', () => this.syncUrl())
      this.$watch('q', () => this.syncUrl())
      await Inthes.whenReady()
      if (Alpine.store('auth').signedIn) this.load()
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.loading = true
      this.error = ''
      try { this.jobs = await Emp.loadMine() } catch (e) { this.error = e.message }
      this.loading = false
    },

    syncUrl() {
      const url = location.pathname + Inthes.qs({ status: this.status, company: this.company, q: this.q.trim() })
      history.replaceState(null, '', url)
    },

    onJobUpdated(job) { Emp.replaceIn(this.jobs, job) },

    /** Every company posted for, with its count, in first-seen order. */
    get companies() {
      const byKey = new Map()
      for (const j of this.jobs) {
        const key = companyKey(j)
        const seen = byKey.get(key)
        byKey.set(key, { key, name: seen?.name || j.organisation || 'Company', count: (seen?.count || 0) + 1 })
      }
      return [...byKey.values()]
    },

    /** The picked company's jobs — all of them when it has none left. */
    get ofCompany() {
      if (!this.company) return this.jobs
      const picked = this.jobs.filter((j) => companyKey(j) === this.company)
      return picked.length ? picked : this.jobs
    },

    count(key) { return this.ofCompany.filter((j) => inTab(j, key)).length },

    get filtered() {
      const q = this.q.trim().toLowerCase()
      return this.ofCompany.filter((j) => inTab(j, this.status) && (!q ||
        [j.title, j.code, j.organisation, j.city, j.role].join(' ').toLowerCase().includes(q)))
    },
  }))
})
