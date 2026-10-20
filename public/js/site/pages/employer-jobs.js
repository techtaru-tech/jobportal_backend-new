/**
 * /employer/jobs — every posting (My Posted Jobs), as a table: status tabs
 * with counts and a search, both kept in the query string.
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

  Alpine.data('empJobs', () => ({
    ...Emp.helpers,
    ...Emp.jobActions(),
    TABS,
    loading: true,
    error: '',
    jobs: [],
    status: '',
    q: '',

    async init() {
      const p = new URLSearchParams(location.search)
      this.status = TABS.some((t) => t.key === p.get('status')) ? p.get('status') : ''
      this.q = p.get('q') || ''
      this.$watch('status', () => this.syncUrl())
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
      const url = location.pathname + Inthes.qs({ status: this.status, q: this.q.trim() })
      history.replaceState(null, '', url)
    },

    onJobUpdated(job) { Emp.replaceIn(this.jobs, job) },

    count(key) { return this.jobs.filter((j) => inTab(j, key)).length },

    get filtered() {
      const q = this.q.trim().toLowerCase()
      return this.jobs.filter((j) => inTab(j, this.status) && (!q ||
        [j.title, j.code, j.organisation, j.city, j.role].join(' ').toLowerCase().includes(q)))
    },
  }))
})
