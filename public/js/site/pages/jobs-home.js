/**
 * Home, signed in only: "Recommended for you" (GET /jobs?recommended=1, best
 * match first) and a glance at your applications. Visitors see the plain
 * server-rendered home page; nothing here runs for them.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('homeForYou', () => ({
    kit: Inthes.jobsKit,
    jobs: [],
    busy: true,

    init() {
      Inthes.whenReady().then(() => { if (this.$store.auth.signedIn) this.load() })
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.busy = true
      this.$store.jobs.ensure().catch(() => {})
      try {
        const r = await Inthes.api('/jobs' + Inthes.qs({ recommended: 1, per_page: 20 }))
        this.jobs = [...(r.data || [])].sort((a, b) => (b.match_percent ?? 0) - (a.match_percent ?? 0)).slice(0, 6)
      } catch (e) { this.jobs = [] }
      this.busy = false
    },

    get apps() { return this.$store.jobs.apps },
    count(status) { return this.apps.filter((a) => a.status === status).length },
  }))
})
