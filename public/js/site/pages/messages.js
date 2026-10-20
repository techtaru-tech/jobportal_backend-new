/**
 * The messages inbox — one two-pane component for both sides:
 * `/account/messages` (side = candidate) and `/employer/messages` (side =
 * recruiter). A thread is keyed by the application id and lives in `?c=`, so
 * it can be linked (notifications do) and the browser's back button closes it.
 *
 * Chat rules are the app's ChatService's: a 5s poll stands in for a push
 * channel (thread + the other side's typing flag), "typing" is sent on the
 * first keystroke and cleared after 3s idle, and "viewing" is set while the
 * thread is open in a visible tab (the server expires it, so the poll renews
 * it). Polling runs only while a thread is open and the tab is visible.
 */
;(function () {
  const POLL_MS = 5000
  const LIST_MS = 30000
  const TYPING_IDLE_MS = 3000
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const enc = encodeURIComponent

  function dayLabel(d) {
    const today = new Date(); today.setHours(0, 0, 0, 0)
    const day = new Date(d); day.setHours(0, 0, 0, 0)
    const diff = Math.round((today - day) / 86400000)
    if (diff === 0) return 'Today'
    if (diff === 1) return 'Yesterday'
    return `${day.getDate()} ${MONTHS[day.getMonth()]} ${day.getFullYear()}`
  }

  function clock(iso) {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    const h = d.getHours() % 12 === 0 ? 12 : d.getHours() % 12
    return `${h}:${String(d.getMinutes()).padStart(2, '0')} ${d.getHours() >= 12 ? 'PM' : 'AM'}`
  }

  /** List time: a clock today, "Yesterday", weekday this week, else a date. */
  function listTime(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    const today = new Date(); today.setHours(0, 0, 0, 0)
    const day = new Date(d); day.setHours(0, 0, 0, 0)
    const diff = Math.round((today - day) / 86400000)
    if (diff === 0) return clock(iso)
    if (diff === 1) return 'Yesterday'
    if (diff < 7) return d.toLocaleDateString('en-IN', { weekday: 'short' })
    return `${d.getDate()} ${MONTHS[d.getMonth()]}`
  }

  /** "Leaving" must reach the server even as the page unloads. */
  function beacon(path, body) {
    const token = Alpine.store('auth').token
    if (!token) return
    try {
      fetch('/api/v1' + path, {
        method: 'POST',
        keepalive: true,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
        body: JSON.stringify(body),
      }).catch(() => {})
    } catch (e) { /* unloading */ }
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('inbox', ({ side }) => ({
      side, // 'candidate' | 'recruiter' — which end of every thread I am
      list: [],
      listLoaded: false,
      listError: '',
      search: '',

      activeId: '',
      messages: [],
      threadLoaded: false,
      threadError: '',
      text: '',
      otherTyping: false,

      _poll: null,
      _ticking: false,
      _listTimer: null,
      _typingSent: false,
      _typingTimer: null,
      _pushed: false,

      async init() {
        await Inthes.whenReady()
        window.addEventListener('inthes:signed-in', () => this.boot())
        window.addEventListener('inthes:signed-out', () => { this.close(false); this.list = []; this.listLoaded = false })
        window.addEventListener('popstate', () => this.syncFromUrl())
        document.addEventListener('visibilitychange', () => this.onVisibility())
        window.addEventListener('pagehide', () => this.close(true, true))
        if (Alpine.store('auth').token) this.boot()
      },

      async boot() {
        await this.loadList()
        this.syncFromUrl()
        clearInterval(this._listTimer)
        this._listTimer = setInterval(() => { if (document.visibilityState === 'visible') this.loadList() }, LIST_MS)
      },

      // ── conversation list ────────────────────────────────────────────
      async loadList() {
        try {
          const res = await Inthes.api('/conversations' + Inthes.qs({ as: this.side, per_page: 100 }))
          const rows = res.data || []
          // The open thread was just read here; don't let a racing list bring its badge back.
          const open = rows.find((c) => c.conversation_id === this.activeId)
          if (open) open.unread_count = 0
          this.list = rows
          this.listError = ''
          this.syncBadge()
        } catch (e) {
          if (!this.listLoaded) this.listError = e.message
        }
        this.listLoaded = true
      },

      syncBadge() {
        const s = Alpine.store('notif')
        if (s && s.chatSide === this.side) s.chatUnread = this.list.reduce((n, c) => n + (Number(c.unread_count) || 0), 0)
      },

      get filtered() {
        const q = this.search.trim().toLowerCase()
        const rows = [...this.list].sort((a, b) => String(b.last_message_at || '').localeCompare(String(a.last_message_at || '')))
        if (!q) return rows
        return rows.filter((c) => [c.title, c.subtitle, c.last_message?.text].some((v) => String(v || '').toLowerCase().includes(q)))
      },

      preview(c) {
        const m = c.last_message
        if (!m) return 'No messages yet — say hello'
        return (m.sender === this.side ? 'You: ' : '') + m.text
      },

      listTime,
      clock,

      get active() { return this.list.find((c) => c.conversation_id === this.activeId) || null },

      get title() {
        if (this.active?.title) return this.active.title
        return this.side === 'recruiter' ? 'Applicant' : 'Recruiter'
      },

      get profileLink() {
        if (!this.activeId) return ''
        if (this.side === 'recruiter') {
          return this.active?.job_id ? `/employer/jobs/${enc(this.active.job_id)}?a=${enc(this.activeId)}` : ''
        }
        return '/account/applications/' + enc(this.activeId)
      },

      statusLabel(s) {
        return String(s || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
      },

      // ── URL ↔ open thread ────────────────────────────────────────────
      syncFromUrl() {
        const id = new URL(location.href).searchParams.get('c') || ''
        if (id === this.activeId) return
        if (id) this.openThread(id)
        else this.close()
      },

      select(id) {
        if (id === this.activeId) return
        const url = new URL(location.href)
        url.searchParams.set('c', id)
        // Thread to thread replaces; list to thread pushes, so back returns to the list.
        if (this.activeId) history.replaceState(null, '', url)
        else { history.pushState(null, '', url); this._pushed = true }
        this.openThread(id)
      },

      /** The thread's back arrow (phones): the list again. */
      backToList() {
        if (this._pushed) { this._pushed = false; history.back(); return }
        const url = new URL(location.href)
        url.searchParams.delete('c')
        history.replaceState(null, '', url)
        this.close()
      },

      // ── thread ───────────────────────────────────────────────────────
      async openThread(id) {
        if (this.activeId && this.activeId !== id) this.close()
        this.activeId = id
        this.messages = []
        this.threadLoaded = false
        this.threadError = ''
        this.text = ''
        this.otherTyping = false
        if (document.visibilityState === 'visible') this.setViewing(true)
        try {
          await this.loadThread()
        } catch (e) {
          if (this.activeId !== id) return
          this.threadLoaded = true
          this.threadError = e.status === 404 || e.status === 403 ? 'This conversation is not available.' : e.message
          return
        }
        if (this.activeId !== id) return
        // Opening the thread marks it read on the server.
        const row = this.active
        if (row) row.unread_count = 0
        this.syncBadge()
        this.startPoll()
        this.$nextTick(() => { if (window.innerWidth >= 768) this.$refs.composer?.focus() })
      },

      async loadThread() {
        const id = this.activeId
        const res = await Inthes.api(`/conversations/${enc(id)}/messages`)
        if (this.activeId !== id) return
        const pending = this.messages.filter((m) => m.status === 'sending')
        const before = this.messages.length
        const lastBefore = this.messages[before - 1]?.id
        this.messages = [...(res.data || []), ...pending]
        this.threadLoaded = true
        const lastNow = this.messages[this.messages.length - 1]?.id
        if (this.messages.length !== before || lastNow !== lastBefore) this.scrollDown(before === 0)
        // Keep the list's preview current without waiting for its own refresh.
        const last = (res.data || []).slice(-1)[0]
        const row = this.active
        if (row && last && row.last_message?.id !== last.id) { row.last_message = last; row.last_message_at = last.sent_at }
        if (row) row.unread_count = 0
      },

      startPoll() {
        clearInterval(this._poll)
        this._poll = null
        if (!this.activeId || document.visibilityState !== 'visible') return
        this._poll = setInterval(() => this.tick(), POLL_MS)
      },

      async tick() {
        // One tick at a time: on a slow connection they would otherwise pile up.
        if (this._ticking) return
        const id = this.activeId
        const looking = () => this.activeId === id && document.visibilityState === 'visible'
        if (!id || !looking()) { this.stopPoll(); return }
        this._ticking = true
        try {
          try {
            const t = await Inthes.api(`/conversations/${enc(id)}/typing`)
            if (this.activeId === id) {
              const was = this.otherTyping
              this.otherTyping = this.side === 'recruiter' ? !!t.data?.candidate : !!t.data?.recruiter
              if (this.otherTyping && !was) this.scrollDown(false)
            }
          } catch (e) { /* transient */ }
          // Left the thread or hid the tab while that was in flight: don't claim to be looking.
          if (!looking()) return
          this.setViewing(true) // the server's flag expires; renew it while looking
          try { await this.loadThread() } catch (e) { /* keep what we have */ }
        } finally {
          this._ticking = false
        }
      },

      stopPoll() { clearInterval(this._poll); this._poll = null },

      onVisibility() {
        if (!this.activeId) return
        if (document.visibilityState === 'visible') {
          this.setViewing(true)
          this.tick()
          this.startPoll()
        } else {
          // A hidden tab is "not viewing", as backgrounding the app is.
          this.stopPoll()
          this.stopTyping()
          this.setViewing(false)
        }
      },

      setViewing(viewing, id = this.activeId) {
        if (!id || !Alpine.store('auth').token) return
        Inthes.api(`/conversations/${enc(id)}/viewing`, { method: 'POST', body: { viewing } }).catch(() => {})
      },

      setTyping(typing) {
        if (!this.activeId) return
        Inthes.api(`/conversations/${enc(this.activeId)}/typing`, { method: 'POST', body: { typing } }).catch(() => {})
      },

      onInput() {
        clearTimeout(this._typingTimer)
        if (!this.text.trim()) { this.stopTyping(); return }
        if (!this._typingSent) { this._typingSent = true; this.setTyping(true) }
        this._typingTimer = setTimeout(() => this.stopTyping(), TYPING_IDLE_MS)
      },

      stopTyping() {
        clearTimeout(this._typingTimer)
        if (this._typingSent) { this._typingSent = false; this.setTyping(false) }
      },

      /** Stop polling and tell the server we left. Safe to call twice. */
      close(notify = true, unloading = false) {
        this.stopPoll()
        const id = this.activeId
        if (!id) return
        if (notify) {
          if (unloading) {
            if (this._typingSent) beacon(`/conversations/${enc(id)}/typing`, { typing: false })
            beacon(`/conversations/${enc(id)}/viewing`, { viewing: false })
          } else {
            this.stopTyping()
            this.setViewing(false, id)
          }
        }
        clearTimeout(this._typingTimer)
        this._typingSent = false
        this.activeId = ''
        this.messages = []
        this.otherTyping = false
      },

      async send() {
        const text = this.text.trim()
        if (!text || !this.activeId) return
        const id = this.activeId
        this.text = ''
        this.stopTyping()
        const temp = { id: 'tmp-' + Date.now(), sender: this.side, text, sent_at: new Date().toISOString(), status: 'sending' }
        this.messages = [...this.messages, temp]
        this.scrollDown(false)
        try {
          const res = await Inthes.api(`/conversations/${enc(id)}/messages`, { method: 'POST', body: { text } })
          if (this.activeId !== id) return
          this.messages = this.messages.map((m) => (m.id === temp.id ? res.data : m))
          const row = this.active
          if (row && res.data) { row.last_message = res.data; row.last_message_at = res.data.sent_at }
        } catch (e) {
          if (this.activeId !== id) return
          this.messages = this.messages.filter((m) => m.id !== temp.id)
          if (!this.text) this.text = text
          Inthes.toast(e.message, true)
        }
      },

      onEnter(e) {
        if (e.shiftKey || e.isComposing) return
        e.preventDefault()
        this.send()
      },

      /** Messages with "Today" / "Yesterday" / "5 Oct 2026" separators. */
      get rows() {
        const rows = []
        let last = ''
        for (const m of this.messages) {
          const d = new Date(m.sent_at)
          const key = d.toDateString()
          if (key !== last) { rows.push({ sep: true, key: 'd-' + key, label: dayLabel(d) }); last = key }
          rows.push({ sep: false, key: m.id, m })
        }
        return rows
      },

      scrollDown(instant) {
        this.$nextTick(() => {
          const el = this.$refs.scroller
          if (el) el.scrollTo({ top: el.scrollHeight, behavior: instant ? 'auto' : 'smooth' })
        })
      },

      destroy() {
        this.close()
        clearInterval(this._listTimer)
      },
    }))
  })
})()
