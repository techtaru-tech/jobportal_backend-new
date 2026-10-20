/**
 * /jobs/{code} — the live parts of a server-rendered posting: the Apply rail,
 * "How you match", Save / Share, and `?apply=1` (deep links and job cards)
 * opening Quick Apply straight away.
 *
 * Starts from the API's shape of the job rendered into the page, then
 * re-reads it with the visitor's token for match %, saved and applied state.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('jobPage', (props) => ({
    kit: Inthes.jobsKit,
    job: props.job,

    init() {
      const wantsApply = new URLSearchParams(location.search).get('apply') === '1'
      if (wantsApply) {
        // Once only: a reload or a shared copy of this URL should not re-open it.
        const url = new URL(location.href)
        url.searchParams.delete('apply')
        history.replaceState(null, '', url.pathname + url.search + url.hash)
      }
      Inthes.whenReady().then(async () => {
        if (this.$store.auth.signedIn) await this.refresh()
        if (wantsApply) Inthes.quickApply(this.job)
      })
      window.addEventListener('inthes:signed-in', () => this.refresh())
      window.addEventListener('inthes:signed-out', () => { this.job = { ...this.job, own_posting: false, has_applied: false, match_percent: null } })
      window.addEventListener('inthes:applied', (e) => { if (e.detail.job_id === this.job.id) this.job.has_applied = true })
    },

    async refresh() {
      this.$store.jobs.ensure().catch(() => {})
      try { this.job = (await Inthes.api('/jobs/' + encodeURIComponent(this.job.id))).data } catch (e) { /* keep the server copy */ }
    },
  }))
})
