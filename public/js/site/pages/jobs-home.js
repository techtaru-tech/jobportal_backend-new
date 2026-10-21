/**
 * Home, signed in only: "Recommended for you" (GET /jobs?recommended=1, best
 * match first) and a glance at your applications. Visitors see the plain
 * server-rendered home page; nothing here runs for them.
 */
document.addEventListener('alpine:init', () => {
  Alpine.store('myJobIds', [])
  window.addEventListener('inthes:signed-out', () => Alpine.store('myJobIds', []))

  Alpine.data('homeForYou', () => ({
    kit: Inthes.jobsKit,
    jobs: [],
    busy: true,
    /** The visitor's own postings — one account holds both sides. */
    posted: [],
    postedTotal: 0,

    init() {
      Inthes.whenReady().then(() => { if (this.$store.auth.signedIn) this.load() })
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    /**
     * Somebody who posts jobs and has never applied is here as an employer:
     * they get their hiring at a glance instead of a job-seeker feed full of
     * other employers' openings. Somebody who does both gets both.
     */
    get hiringOnly() { return this.postedTotal > 0 && !this.apps.length },
    get activeCount() { return this.posted.filter((j) => j.posting_status === 'active').length },
    get pendingCount() { return this.posted.filter((j) => j.posting_status === 'pending_approval').length },
    get applicantsCount() { return this.posted.reduce((n, j) => n + (j.applicants_count || 0), 0) },

    async load() {
      this.busy = true
      Inthes.api('/recruiter/jobs/mine' + Inthes.qs({ per_page: 50 }))
        .then((r) => { this.posted = r.data || []; this.postedTotal = r.meta?.total ?? this.posted.length })
        .catch(() => { this.posted = []; this.postedTotal = 0 })
        // The public job cards on this page read it to say "Manage" on the
        // visitor's own postings rather than inviting them to apply.
        .finally(() => Alpine.store('myJobIds', this.posted.map((j) => j.id)))
      await this.$store.jobs.ensure().catch(() => {})
      try {
        const r = await Inthes.api('/jobs' + Inthes.qs({ recommended: 1, per_page: 20 }))
        this.jobs = [...(r.data || [])].sort((a, b) => (b.match_percent ?? 0) - (a.match_percent ?? 0)).slice(0, 4)
      } catch (e) { this.jobs = [] }
      this.busy = false
    },

    get apps() { return this.$store.jobs.apps },
    count(status) { return this.apps.filter((a) => a.status === status).length },
  }))
})
