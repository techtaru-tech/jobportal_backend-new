/**
 * /account/applications — every application with status tabs (`?status=`
 * in the URL, so a tab survives reload and back).
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('accountApplications', () => ({
    kit: Inthes.jobsKit,
    tabs: Inthes.jobsKit.APP_TABS,
    tab: new URLSearchParams(location.search).get('status') || '',
    list: [],
    busy: true,
    error: false,

    init() {
      Inthes.whenReady().then(() => { if (this.$store.auth.signedIn) this.load() })
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.busy = !this.list.length
      this.error = false
      try {
        const r = await Inthes.api('/applications')
        this.list = r.data || []
        this.$store.jobs.apps = this.list
      } catch (e) { this.error = true }
      this.busy = false
    },

    pick(key) {
      this.tab = key
      history.replaceState(null, '', location.pathname + Inthes.qs({ status: key || null }))
    },
    count(key) { return key ? this.list.filter((a) => a.status === key).length : this.list.length },
    get filtered() { return this.tab ? this.list.filter((a) => a.status === this.tab) : this.list },
    open(a) { location.href = '/account/applications/' + encodeURIComponent(a.id) },
  }))
})
