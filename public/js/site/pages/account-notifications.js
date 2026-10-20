/**
 * /account/notifications — every notification for one side (Job search or
 * Hiring; `?audience=recruiter` opens Hiring), with an All / Unread filter.
 *
 * As in the app, opening the inbox is what "seen" means: the side is marked
 * read on load, but the rows that were unread stay highlighted (and listed
 * under "Unread") for this visit so they can still be spotted.
 * Rows route through `$store.notif.go` — the same rules as the header bell.
 */
;(function () {
  document.addEventListener('alpine:init', () => {
    Alpine.data('accountNotifications', () => ({
      audience: new URL(location.href).searchParams.get('audience') === 'recruiter' ? 'recruiter' : 'jobSeeker',
      filter: 'all',
      items: [],
      fresh: [], // ids unread when this side was opened
      loaded: false,
      error: '',
      _seq: 0,

      async init() {
        await Inthes.whenReady()
        window.addEventListener('inthes:signed-in', () => this.load())
        if (Alpine.store('auth').token) this.load()
      },

      setAudience(a) {
        if (a === this.audience) return
        this.audience = a
        this.filter = 'all'
        const url = new URL(location.href)
        if (a === 'recruiter') url.searchParams.set('audience', 'recruiter')
        else url.searchParams.delete('audience')
        history.replaceState(null, '', url)
        this.load()
      },

      async load() {
        const seq = ++this._seq
        const audience = this.audience
        this.loaded = false
        this.error = ''
        try {
          const res = await Inthes.api('/notifications' + Inthes.qs({ audience }))
          if (seq !== this._seq) return
          this.items = res.data || []
          this.fresh = this.items.filter((n) => !n.read).map((n) => n.id)
        } catch (e) {
          if (seq !== this._seq) return
          this.error = e.message
        }
        this.loaded = true
        if (this.fresh.length) this.markRead(audience, false)
      },

      async markRead(audience, clearHighlight) {
        try {
          await Inthes.api('/notifications/read', { method: 'POST', body: { audience } })
          if (audience !== this.audience) return
          this.items = this.items.map((n) => ({ ...n, read: true }))
          if (clearHighlight) this.fresh = []
          const s = Alpine.store('notif')
          if (s && s.audience === audience) { s.items = s.items.map((n) => ({ ...n, read: true })); s.unread = 0 }
        } catch (e) { if (clearHighlight) Inthes.toast(e.message, true) }
      },

      async markAll() {
        await this.markRead(this.audience, true)
        Inthes.toast('All notifications marked as read')
      },

      isNew(n) { return !n.read || this.fresh.includes(n.id) },
      get unreadCount() { return this.items.filter((n) => this.isNew(n)).length },
      get shown() { return this.filter === 'unread' ? this.items.filter((n) => this.isNew(n)) : this.items },

      icon(n) { return Alpine.store('notif').icon(n) },
      open(n) { Alpine.store('notif').go(n) },
    }))
  })
})()
