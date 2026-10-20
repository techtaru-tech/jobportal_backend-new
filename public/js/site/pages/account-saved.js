/**
 * /account/saved — saved jobs, from `$store.jobs` (the same list every Save
 * button writes to), so unsaving here and elsewhere stays in step.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('accountSaved', () => ({
    kit: Inthes.jobsKit,
    busy: true,
    error: false,

    init() {
      Inthes.whenReady().then(() => { if (this.$store.auth.signedIn) this.load() })
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.busy = !this.$store.jobs.loaded
      this.error = false
      try { await this.$store.jobs.ensure(true) } catch (e) { this.error = true }
      this.busy = false
    },

    /** Unsaved rows leave the list; a mistaken click is one Save away on the job. */
    get list() { return this.$store.jobs.saved.filter((j) => this.$store.jobs.savedIds.includes(j.id)) },
  }))
})
