/**
 * /employer — the hiring dashboard (the app's Hiring Dashboard, widened):
 * KPIs, what needs the employer's attention, recent jobs and the latest
 * applicants across them.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('empDashboard', () => ({
    ...Emp.helpers,
    ...Emp.jobActions(),
    loading: true,
    error: '',
    jobs: [],
    orgs: [],
    applicants: [],
    applicantsLoaded: false,

    async init() {
      await Inthes.whenReady()
      if (Alpine.store('auth').signedIn) this.load()
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.loading = true
      this.error = ''
      try {
        const [jobs, orgs] = await Promise.all([Emp.loadMine(), Emp.loadOrgs().catch(() => [])])
        this.jobs = jobs
        this.orgs = orgs
      } catch (e) { this.error = e.message }
      this.loading = false
      this.loadApplicants()
    },

    /**
     * Applicants across the newest postings that have any — one request per
     * job, capped, since there is no cross-job endpoint. Enough for "new this
     * week" and the recent list.
     */
    async loadApplicants() {
      const withApps = this.jobs.filter((j) => (j.applicants_count || 0) > 0).slice(0, 8)
      const lists = await Promise.all(withApps.map((j) =>
        Inthes.api(`/recruiter/jobs/${encodeURIComponent(j.id)}/applicants?per_page=100&sort=newest`)
          .then((r) => (r.data || []).map((a) => Object.assign(a, { _job: j })))
          .catch(() => [])))
      this.applicants = lists.flat().sort((a, b) => new Date(b.applied_at) - new Date(a.applied_at))
      this.applicantsLoaded = true
    },

    onJobUpdated(job) { Emp.replaceIn(this.jobs, job) },

    get activeCount() { return this.jobs.filter((j) => j.posting_status === 'active').length },
    get pendingCount() { return this.jobs.filter((j) => j.posting_status === 'pending_approval').length },
    get totalApplicants() { return this.jobs.reduce((s, j) => s + (j.applicants_count || 0), 0) },
    get newThisWeek() {
      const since = Date.now() - 7 * 864e5
      return this.applicants.filter((a) => new Date(a.applied_at).getTime() >= since).length
    },
    get recentJobs() { return this.jobs.slice(0, 5) },
    get recentApplicants() { return this.applicants.slice(0, 6) },

    /** Everything waiting on the employer, most urgent first. */
    get attention() {
      const out = []
      for (const j of this.jobs) {
        if (Emp.awaitingPayment(j)) {
          out.push({ key: 'pay' + j.id, tone: 'warning', icon: 'wallet', title: j.title, text: 'Saved — choose a plan to make it live.', href: this.noticeHref(j, 'plan'), cta: 'Choose plan' })
        } else if (j.posting_status === 'rejected') {
          out.push({ key: 'rej' + j.id, tone: 'danger', icon: 'xCircle', title: j.title, text: j.rejection_reason ? 'Not approved: ' + j.rejection_reason : 'This job post was not approved.', href: this.jobUrl(j) + '/edit', cta: 'Edit & resubmit' })
        }
      }
      for (const o of this.orgs) {
        if (o.review_state === 'no_document' || (!o.document_name && !o.verified)) {
          const hidden = o.hidden_postings || 0
          out.push({
            key: 'doc' + o.id, tone: 'brand', icon: 'upload', title: o.name,
            text: hidden > 0 ? `Upload your company document — ${hidden} posting${hidden === 1 ? ' is' : 's are'} not being shown.` : 'Upload your company document to get verified.',
            href: '/employer/companies?doc=' + encodeURIComponent(o.id), cta: 'Upload document',
          })
        } else if (o.review_state === 'pending') {
          out.push({ key: 'ver' + o.id, tone: 'info', icon: 'clock', title: o.name, text: "We're checking your company document — jobs go live once it's verified.", href: '/employer/companies', cta: 'View' })
        }
      }
      return out
    },

    toneClass(t) {
      return {
        warning: 'bg-warning-bg text-warning', danger: 'bg-danger-bg text-danger',
        brand: 'bg-primary-light text-primary', info: 'bg-info-bg text-info',
      }[t]
    },

    apName: Emp.apName,
    apDesignation: Emp.apDesignation,
    apLocation: Emp.apLocation,
  }))
})
