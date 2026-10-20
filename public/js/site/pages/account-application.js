/**
 * /account/applications/{id} — track one application: the job, the status
 * timeline, an interview if one is set, and what was sent.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('accountApplication', (props) => ({
    kit: Inthes.jobsKit,
    id: props.id,
    app: null,
    busy: true,
    error: '',

    init() {
      Inthes.whenReady().then(() => { if (this.$store.auth.signedIn) this.load() })
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.busy = !this.app
      this.error = ''
      try {
        this.app = (await Inthes.api('/applications/' + encodeURIComponent(this.id))).data
        document.title = (this.app.job?.title || 'Application') + ' — your application — Inthes'
      } catch (e) {
        this.error = e.status === 404 || e.status === 403 ? 'This application is not on your account.' : "We couldn't load this application. Check your connection and try again."
      }
      this.busy = false
    },

    get steps() { return this.kit.trackSteps(this.app) },

    /** The snapshot rows worth showing — what the employer received. */
    get snapshot() {
      const s = this.app?.profile_snapshot
      if (!s) return []
      return [
        ['Name', s.name], ['Phone', s.phone ? '+91 ' + s.phone : ''], ['Email', s.email],
        ['Gender', s.gender], ['Age', s.age], ['Qualification', s.qualification || (s.educations || [])[0]?.qualification],
        ['Experience', s.experience], ['Preferred cities', (s.location || []).join(', ')], ['Address', s.address],
      ].filter((r) => r[1])
    },
  }))
})
