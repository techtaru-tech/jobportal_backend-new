/**
 * /account/alerts — standing searches. Criteria are ANDed, a blank one
 * matches anything, and an alert fires when a matching job goes live.
 * Up to 10 (the server's limit; said here before it says no).
 */
document.addEventListener('alpine:init', () => {
  const MAX = 10
  const blank = (v) => (String(v || '').trim() ? String(v).trim() : null)

  Alpine.data('accountAlerts', () => ({
    max: MAX,
    items: [],
    loaded: false,
    error: false,
    saving: false,
    form: { role: '', city: '', keyword: '' },

    init() {
      Inthes.whenReady().then(() => { if (this.$store.auth.signedIn) this.load() })
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.error = false
      try {
        const res = await Inthes.api('/candidate/job-alerts')
        this.items = res.data?.alerts || []
      } catch (e) { this.error = true }
      this.loaded = true
    },

    /** Said before saving: an all-blank alert matches every new job. */
    get isCatchAll() { return !this.form.role && !this.form.city && !this.form.keyword.trim() },
    get full() { return this.items.length >= MAX },

    async create() {
      if (this.saving || this.full) return
      this.saving = true
      try {
        const res = await Inthes.api('/candidate/job-alerts', {
          method: 'POST',
          body: { role: blank(this.form.role), city: blank(this.form.city), keyword: blank(this.form.keyword) },
        })
        const created = res.data?.alert
        if (created) {
          // Replace, not append: an identical alert comes back as the old row.
          const i = this.items.findIndex((a) => a.id === created.id)
          if (i === -1) this.items = [created, ...this.items]
          else this.items.splice(i, 1, created)
        }
        this.form = { role: '', city: '', keyword: '' }
        Inthes.toast(res.message === 'You already have that alert.' ? res.message : 'Job alert saved')
      } catch (e) { Inthes.toast(e.message, true) }
      this.saving = false
    },

    async toggle(a) {
      try {
        const res = await Inthes.api('/candidate/job-alerts/' + a.id, { method: 'PATCH', body: { is_active: !a.is_active } })
        const saved = res.data?.alert
        const i = this.items.findIndex((x) => x.id === a.id)
        if (saved && i !== -1) this.items.splice(i, 1, saved)
        Inthes.toast(a.is_active ? 'Alert paused' : 'Alert resumed')
      } catch (e) { Inthes.toast(e.message, true) }
    },

    async remove(a) {
      const ok = await Inthes.confirm({ title: 'Delete this alert?', message: a.summary, ok: 'Delete', danger: true })
      if (!ok) return
      try {
        await Inthes.api('/candidate/job-alerts/' + a.id, { method: 'DELETE' })
        this.items = this.items.filter((x) => x.id !== a.id)
        Inthes.toast('Alert deleted')
      } catch (e) { Inthes.toast(e.message, true) }
    },

    /** The browse page with this alert's criteria — what it would match today. */
    browseUrl(a) {
      return '/jobs' + Inthes.qs({ category: a.role || null, city: a.city ? [a.city] : null, q: a.keyword || null })
    },
  }))
})
