/**
 * The header's bell and the Messages icon's dot, on every page.
 *
 * `Alpine.store('notif')` holds the latest notifications for the side the
 * visitor is on (the employer area is the recruiter inbox, everything else
 * the job seeker's — the same split as the app's two inboxes), the unread
 * counts, and `go(n)`: where a notification leads, the app's
 * NotificationRouter mapped onto website URLs.
 *
 * There is no count endpoint, so both dots come from the lists: refreshed when
 * the bell opens, on sign-in, and every 60s while the tab is visible.
 */
;(function () {
  const REFRESH_MS = 60000

  const enc = encodeURIComponent
  const onEmployer = () => /^\/employer(\/|$)/.test(location.pathname)

  /** Where a notification goes — NotificationRouter.open, rule for rule, as website URLs. */
  async function routeFor(n) {
    const recruiter = n.audience === 'recruiter'
    const appId = n.application_id || null
    const jobId = n.job_id || null
    const convId = n.conversation_id || appId
    if (n.type === 'new_message' && convId) {
      return (recruiter ? '/employer/messages?c=' : '/account/messages?c=') + enc(convId)
    }
    if (appId) {
      if (recruiter) return jobId ? `/employer/jobs/${enc(jobId)}?a=${enc(appId)}` : '/employer/messages?c=' + enc(appId)
      return '/account/applications/' + enc(appId)
    }
    if (jobId) {
      // Public job pages are keyed by the job code; a notification only carries the id.
      if (recruiter) return '/employer/jobs/' + enc(jobId)
      try {
        const res = await Inthes.api('/jobs/' + enc(jobId))
        if (res.data?.code) return '/jobs/' + enc(res.data.code)
      } catch (e) { /* fall through */ }
      return '/jobs?job=' + enc(jobId)
    }
    return '' // a plain notice: nowhere to go, as in the app
  }

  function iconFor(n) {
    if (n.type === 'new_message') return 'message'
    if (n.type === 'job_match') return 'briefcase'
    if (n.type === 'application_update') return 'clipboard'
    return 'bell'
  }

  document.addEventListener('alpine:init', () => {
    Alpine.store('notif', {
      items: [],
      loaded: false,
      busy: false,
      unread: 0,
      chatUnread: 0,
      _timer: null,
      _seq: 0,

      get audience() { return onEmployer() ? 'recruiter' : 'jobSeeker' },
      get chatSide() { return onEmployer() ? 'recruiter' : 'candidate' },
      get messagesUrl() { return onEmployer() ? '/employer/messages' : '/account/messages' },
      get allUrl() { return '/account/notifications' + (onEmployer() ? '?audience=recruiter' : '') },
      get latest() { return this.items.slice(0, 8) },

      icon: iconFor,
      routeFor,

      async refresh() {
        if (!Alpine.store('auth').token) return
        const seq = ++this._seq
        this.busy = true
        const [n, c] = await Promise.allSettled([
          Inthes.api('/notifications' + Inthes.qs({ audience: this.audience })),
          Inthes.api('/conversations' + Inthes.qs({ as: this.chatSide, per_page: 100 })),
        ])
        if (seq !== this._seq) return
        if (n.status === 'fulfilled') {
          this.items = n.value.data || []
          this.unread = this.items.filter((x) => !x.read).length
          this.loaded = true
        }
        if (c.status === 'fulfilled') {
          this.chatUnread = (c.value.data || []).reduce((s, x) => s + (Number(x.unread_count) || 0), 0)
        }
        this.busy = false
      },

      async markAll() {
        try {
          await Inthes.api('/notifications/read', { method: 'POST', body: { audience: this.audience } })
          this.items = this.items.map((x) => ({ ...x, read: true }))
          this.unread = 0
        } catch (e) { Inthes.toast(e.message, true) }
      },

      async go(n) {
        const url = await routeFor(n)
        if (url) location.href = url
      },

      start() {
        this.refresh()
        clearInterval(this._timer)
        this._timer = setInterval(() => { if (document.visibilityState === 'visible') this.refresh() }, REFRESH_MS)
      },

      stop() {
        clearInterval(this._timer)
        this._timer = null
        this._seq++
        Object.assign(this, { items: [], loaded: false, unread: 0, chatUnread: 0, busy: false })
      },
    })
  })

  window.addEventListener('inthes:ready', () => { if (Alpine.store('auth').token) Alpine.store('notif').start() })
  window.addEventListener('inthes:signed-in', () => Alpine.store('notif').start())
  window.addEventListener('inthes:signed-out', () => Alpine.store('notif').stop())
  // Back from another tab: catch up at once rather than at the next minute.
  document.addEventListener('visibilitychange', () => {
    const s = window.Alpine && Alpine.store('notif')
    if (s && s._timer && document.visibilityState === 'visible') s.refresh()
  })
})()
