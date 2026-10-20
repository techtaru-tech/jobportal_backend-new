/**
 * extras part — Chats, a chat thread, Notifications, Job Alerts, My Plan and
 * the static content pages (Terms, Privacy, About, Contact, Help & Support).
 *
 * Mirrors the app's conversations / candidate_chat / applicant_chat /
 * notifications / job_alerts / subscription / info modules and their
 * repositories. Also owns the header's unread dots (`chatUnread`,
 * `notifUnread`), which the app shows on the same two icons.
 */
;(function () {
  const POLL_MS = 5000 // the app's chat poll: stands in for a push channel
  const BADGE_MS = 60000 // header dots, while signed in
  const TYPING_IDLE_MS = 3000 // ChatService._typingIdleTimeout

  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

  /** The app's slugs, plus the obvious spellings a link might use. */
  const PAGE_ALIASES = {
    terms: 'terms', 'terms-of-use': 'terms', 'terms-and-conditions': 'terms', tos: 'terms',
    privacy: 'privacy', 'privacy-policy': 'privacy',
    about: 'about', 'about-us': 'about',
    contact: 'contact', 'contact-us': 'contact',
    faq: 'faq', faqs: 'faq', help: 'faq', 'help-support': 'faq', support: 'faq',
  }

  /** Fallback titles — what each app screen calls itself before content lands. */
  const PAGE_TITLES = { terms: 'Terms of Use', privacy: 'Privacy Policy', about: 'About Us', contact: 'Contact Us', faq: 'Help & Support' }

  WebApp.part({
    // ── header badges ─────────────────────────────────────────────────
    chatUnread: 0,
    notifUnread: 0,
    _badgeTimer: null,

    _extrasBooted: false,

    _bootExtras() {
      // Once only: a second run would start a second badge timer.
      if (this._extrasBooted) return
      this._extrasBooted = true
      if (this.token) this.refreshBadges()
      this._badgeTimer = setInterval(() => { if (this.token) this.refreshBadges() }, BADGE_MS)
      // Both dots are per side, like the app's inboxes.
      this.$watch('mode', () => { if (this.token) this.refreshBadges() })
      // Leaving a chat stops its poll and tells the server we stopped looking.
      this.$watch('route.name', (name) => { if (name !== 'chat') this._chatLeave() })
      // A hidden tab is "not viewing", as backgrounding the app is.
      document.addEventListener('visibilitychange', () => {
        if (!this.chatId) return
        this._chatViewing(document.visibilityState === 'visible')
      })
      window.addEventListener('resize', () => { if (this.chatId) this._chatMeasure() })
    },

    _afterSignInExtras() { this.refreshBadges() },

    _onSignOutExtras() {
      this._chatLeave(false)
      this.chatUnread = 0
      this.notifUnread = 0
      this.chatList = []
      this.chatListLoaded = false
      this.notifItems = []
      this.notifLoaded = false
      this.alertItems = []
      this.alertLoaded = false
      this.subData = null
    },

    get _audience() { return this.mode === 'recruiter' ? 'recruiter' : 'jobSeeker' },
    get _chatSide() { return this.mode === 'recruiter' ? 'recruiter' : 'candidate' },

    /** No count endpoint exists, so the dots come from the two lists. */
    async refreshBadges() {
      const audience = this._audience
      const side = this._chatSide
      try {
        const res = await this.api('/conversations' + this.qs({ as: side, per_page: 100 }))
        if (this._chatSide === side) this.chatUnread = (res.data || []).reduce((n, c) => n + (Number(c.unread_count) || 0), 0)
      } catch (e) { /* the dot just stays as it was */ }
      try {
        const res = await this.api('/notifications' + this.qs({ audience }))
        if (this._audience === audience) this.notifUnread = (res.data || []).filter((n) => !n.read).length
      } catch (e) { /* same */ }
    },

    // ── Chats (conversations) ─────────────────────────────────────────
    chatList: [],
    chatListLoaded: false,
    chatListBusy: false,

    async onConversations() {
      this.chatListBusy = true
      if (this.chatListSide !== this._chatSide) { this.chatList = []; this.chatListLoaded = false }
      try {
        const side = this._chatSide
        const res = await this.api('/conversations' + this.qs({ as: side, per_page: 100 }))
        this.chatList = res.data || []
        this.chatListSide = side
        this.chatListLoaded = true
        this.chatUnread = this.chatList.reduce((n, c) => n + (Number(c.unread_count) || 0), 0)
      } finally {
        this.chatListBusy = false
      }
    },
    chatListSide: '',

    /** "Recruiter: …" on the candidate side, as the app's tile prefixes it. */
    chatPreview(c) {
      const m = c.last_message
      if (!m) return ''
      return this.chatListSide === 'candidate' && m.sender === 'recruiter' ? 'Recruiter: ' + m.text : m.text
    },

    // ── Chat thread ───────────────────────────────────────────────────
    chatId: '',
    /** 'candidate' | 'recruiter' — which end of this thread I am. */
    chatMe: 'candidate',
    chatInfo: null, // the conversations-list row: title, subtitle, job_id
    chatMessages: [],
    chatLoaded: false,
    chatText: '',
    chatOtherTyping: false,
    chatSending: false,
    chatHeight: 560,
    _chatPoll: null,
    _chatTypingSent: false,
    _chatTypingTimer: null,

    async onChat(params) {
      // Switching straight from one thread to another: close the old one first.
      if (this.chatId && this.chatId !== params.id) this._chatLeave()
      const id = params.id
      this.chatId = id
      this.chatMessages = []
      this.chatLoaded = false
      this.chatText = ''
      this.chatOtherTyping = false
      this.chatInfo = null
      this.chatMe = this._chatSide
      this.$nextTick(() => this._chatMeasure())

      this._chatViewing(true)
      this._chatFindInfo(id)
      try {
        await this._chatLoad()
      } catch (e) {
        if (this.chatId !== id) return
        this.chatLoaded = true
        if (e.status === 404) { this.showToast('This conversation is not available.', true); return }
        this.showToast(e.message, true)
      }
      if (this.chatId !== id) return
      // Opening the thread just marked it read server-side.
      this.refreshBadges()
      clearInterval(this._chatPoll)
      this._chatPoll = setInterval(() => this._chatTick(), POLL_MS)
    },

    /**
     * The thread endpoint carries only messages, so the header (the other
     * party, the job) comes from the conversations row — on this side first,
     * then the other, since one account can be on either end.
     */
    async _chatFindInfo(id) {
      const sides = this._chatSide === 'recruiter' ? ['recruiter', 'candidate'] : ['candidate', 'recruiter']
      for (const side of sides) {
        const cached = this.chatListSide === side && this.chatList.find((c) => c.conversation_id === id)
        let row = cached || null
        if (!row) {
          try {
            const res = await this.api('/conversations' + this.qs({ as: side, per_page: 100 }))
            row = (res.data || []).find((c) => c.conversation_id === id) || null
          } catch (e) { row = null }
        }
        if (this.chatId !== id) return
        if (row) { this.chatInfo = row; this.chatMe = side; return }
      }
    },

    async _chatLoad() {
      const id = this.chatId
      const res = await this.api(`/conversations/${encodeURIComponent(id)}/messages`)
      if (this.chatId !== id) return
      const list = res.data || []
      const pending = this.chatMessages.filter((m) => m.status === 'sending')
      const before = this.chatMessages.length
      const lastBefore = this.chatMessages[before - 1]?.id
      this.chatMessages = [...list, ...pending]
      this.chatLoaded = true
      const lastNow = this.chatMessages[this.chatMessages.length - 1]?.id
      if (this.chatMessages.length !== before || lastNow !== lastBefore) this._chatScroll(before === 0)
      // Opening a thread marks it read server-side; reflect that in the dot.
      const row = this.chatList.find((c) => c.conversation_id === id)
      if (row && row.unread_count) { this.chatUnread = Math.max(0, this.chatUnread - row.unread_count); row.unread_count = 0 }
    },

    async _chatTick() {
      const id = this.chatId
      if (!id || !this.is('chat')) { this._chatLeave(); return }
      try {
        const t = await this.api(`/conversations/${encodeURIComponent(id)}/typing`)
        if (this.chatId === id) {
          const was = this.chatOtherTyping
          this.chatOtherTyping = this.chatMe === 'recruiter' ? !!t.data?.candidate : !!t.data?.recruiter
          if (this.chatOtherTyping && !was) this._chatScroll(false)
        }
      } catch (e) { /* transient */ }
      if (document.visibilityState === 'visible') this._chatViewing(true) // the flag expires in ~15s
      try { await this._chatLoad() } catch (e) { /* keep the last known thread */ }
    },

    _chatViewing(viewing, id = this.chatId) {
      if (!id || !this.token) return
      this.api(`/conversations/${encodeURIComponent(id)}/viewing`, { method: 'POST', body: { viewing } }).catch(() => {})
    },

    _chatSetTyping(typing) {
      if (!this.chatId) return
      this.api(`/conversations/${encodeURIComponent(this.chatId)}/typing`, { method: 'POST', body: { typing } }).catch(() => {})
    },

    /** Re-armed on every keystroke; "stopped" after 3s idle or a blank field. */
    chatOnInput() {
      clearTimeout(this._chatTypingTimer)
      if (!this.chatText.trim()) { this._chatStopTyping(); return }
      if (!this._chatTypingSent) { this._chatTypingSent = true; this._chatSetTyping(true) }
      this._chatTypingTimer = setTimeout(() => this._chatStopTyping(), TYPING_IDLE_MS)
    },

    _chatStopTyping() {
      clearTimeout(this._chatTypingTimer)
      if (this._chatTypingSent) { this._chatTypingSent = false; this._chatSetTyping(false) }
    },

    /** Stop polling and tell the server we have left. Safe to call twice. */
    _chatLeave(notify = true) {
      clearInterval(this._chatPoll)
      this._chatPoll = null
      if (!this.chatId) return
      if (notify) {
        this._chatStopTyping()
        this._chatViewing(false)
      }
      clearTimeout(this._chatTypingTimer)
      this._chatTypingSent = false
      this.chatId = ''
      this.chatOtherTyping = false
    },

    async chatSend() {
      const text = this.chatText.trim()
      if (!text || !this.chatId) return
      const id = this.chatId
      this.chatText = ''
      this._chatStopTyping()
      const temp = { id: 'tmp-' + Date.now(), sender: this.chatMe, text, sent_at: new Date().toISOString(), status: 'sending' }
      this.chatMessages = [...this.chatMessages, temp]
      this._chatScroll(false)
      try {
        const res = await this.api(`/conversations/${encodeURIComponent(id)}/messages`, { method: 'POST', body: { text } })
        if (this.chatId !== id) return
        this.chatMessages = this.chatMessages.map((m) => (m.id === temp.id ? res.data : m))
      } catch (e) {
        if (this.chatId !== id) return
        this.chatMessages = this.chatMessages.filter((m) => m.id !== temp.id)
        if (!this.chatText) this.chatText = text
        this.showToast(e.message, true)
      }
    },

    /** Messages with "Today" / "Yesterday" / "5 Oct 2026" separators. */
    get chatRows() {
      const rows = []
      let last = ''
      for (const m of this.chatMessages) {
        const d = new Date(m.sent_at)
        const key = d.toDateString()
        if (key !== last) { rows.push({ sep: true, key: 'd-' + key, label: this._chatDayLabel(d) }); last = key }
        rows.push({ sep: false, key: m.id, m })
      }
      return rows
    },

    _chatDayLabel(d) {
      const today = new Date(); today.setHours(0, 0, 0, 0)
      const day = new Date(d); day.setHours(0, 0, 0, 0)
      const diff = Math.round((today - day) / 86400000)
      if (diff === 0) return 'Today'
      if (diff === 1) return 'Yesterday'
      return `${day.getDate()} ${MONTHS[day.getMonth()]} ${day.getFullYear()}`
    },

    chatTime(iso) {
      const d = new Date(iso)
      if (Number.isNaN(d.getTime())) return ''
      const h = d.getHours() % 12 === 0 ? 12 : d.getHours() % 12
      return `${h}:${String(d.getMinutes()).padStart(2, '0')} ${d.getHours() >= 12 ? 'PM' : 'AM'}`
    },

    get chatTitle() {
      if (this.chatInfo?.title) return this.chatInfo.title
      return this.chatMe === 'recruiter' ? 'Applicant' : 'Recruiter'
    },

    /** The recruiter's way back to the applicant card for this thread. */
    get chatApplicantLink() {
      if (this.chatMe !== 'recruiter' || !this.chatInfo?.job_id) return ''
      return `#/r/applicant/${encodeURIComponent(this.chatInfo.job_id)}/${encodeURIComponent(this.chatId)}`
    },

    _chatScroll(instant) {
      this.$nextTick(() => {
        const el = document.getElementById('chat-scroll')
        if (el) el.scrollTo({ top: el.scrollHeight, behavior: instant ? 'auto' : 'smooth' })
      })
    },

    /**
     * The thread fills the window between the app bar and the space <main>
     * keeps clear for the bottom nav, so only the message list scrolls.
     */
    _chatMeasure() {
      const header = document.querySelector('header')
      const main = document.querySelector('main')
      const pad = main ? parseFloat(getComputedStyle(main).paddingBottom) || 0 : 0
      const top = window.innerWidth >= 1024 ? 24 : 0 // lg:pt-xxl on the wrapper
      this.chatHeight = Math.max(320, window.innerHeight - (header?.offsetHeight || 60) - pad - top)
    },

    // ── Notifications ─────────────────────────────────────────────────
    notifItems: [],
    notifLoaded: false,
    notifBusy: false,
    /** Ids that were unread when the screen opened — still drawn as new. */
    notifNew: [],

    async onNotifications() {
      const audience = this._audience
      if (this.notifAudience !== audience) { this.notifItems = []; this.notifLoaded = false }
      this.notifBusy = true
      try {
        const res = await this.api('/notifications' + this.qs({ audience }))
        this.notifItems = res.data || []
        this.notifAudience = audience
        this.notifLoaded = true
        this.notifNew = this.notifItems.filter((n) => !n.read).map((n) => n.id)
      } finally {
        this.notifBusy = false
      }
      // As the app does: opening the inbox is what "seen" means. The rows
      // that were new keep their dot for this visit so they can be spotted.
      if (this.notifNew.length) this._notifMarkRead(audience)
    },
    notifAudience: '',

    async _notifMarkRead(audience) {
      try {
        await this.api('/notifications/read', { method: 'POST', body: { audience } })
        this.notifItems = this.notifItems.map((n) => ({ ...n, read: true }))
        if (this._audience === audience) this.notifUnread = 0
      } catch (e) { /* the dot comes back on the next refresh */ }
    },

    async notifMarkAll() {
      this.notifNew = []
      await this._notifMarkRead(this.notifAudience || this._audience)
      this.showToast('All notifications marked as read')
    },

    notifIsNew(n) { return !n.read || this.notifNew.includes(n.id) },

    /** Where a tap goes — `NotificationRouter.open`, rule for rule. */
    notifOpen(n) {
      const forRecruiter = n.audience === 'recruiter'
      const jobId = n.job_id || null
      const appId = n.application_id || null
      const convId = n.conversation_id || appId
      const enc = encodeURIComponent
      if (n.type === 'new_message' && convId) { this.go('/chat/' + enc(convId)); return }
      if (appId) {
        if (forRecruiter && jobId) this.go(`/r/applicant/${enc(jobId)}/${enc(appId)}`)
        else this.go('/track/' + enc(appId))
        return
      }
      if (jobId) { this.go('/job/' + enc(jobId)); return }
      // A plain system notice: nowhere to go, as in the app.
    },

    notifIcon(n) {
      if (n.type === 'new_message') return 'message'
      if (n.type === 'job_match') return 'briefcase'
      if (n.type === 'application_update') return 'clipboard'
      return 'bell'
    },

    // ── Job alerts ────────────────────────────────────────────────────
    alertItems: [],
    alertLoaded: false,
    alertForm: { role: '', city: '', keyword: '' },
    alertSaving: false,
    alertMax: 10,

    async onAlerts() {
      try {
        const res = await this.api('/candidate/job-alerts')
        this.alertItems = res.data?.alerts || []
      } finally {
        this.alertLoaded = true
      }
    },

    /** Said before saving: an all-blank alert matches every new job. */
    get alertIsCatchAll() {
      return !this.alertForm.role && !this.alertForm.city && !this.alertForm.keyword.trim()
    },

    async alertCreate() {
      if (this.alertSaving) return
      this.alertSaving = true
      const blank = (v) => (String(v || '').trim() ? String(v).trim() : null)
      try {
        const res = await this.api('/candidate/job-alerts', {
          method: 'POST',
          body: { role: blank(this.alertForm.role), city: blank(this.alertForm.city), keyword: blank(this.alertForm.keyword) },
        })
        const created = res.data?.alert
        if (created) {
          // Replace, not append: an identical alert comes back as the old row.
          const i = this.alertItems.findIndex((a) => a.id === created.id)
          if (i === -1) this.alertItems = [created, ...this.alertItems]
          else this.alertItems.splice(i, 1, created)
        }
        this.alertForm = { role: '', city: '', keyword: '' }
        this.showToast(res.message === 'You already have that alert.' ? res.message : 'Job alert saved')
      } catch (e) {
        this.showToast(e.message, true)
      }
      this.alertSaving = false
    },

    async alertToggle(a) {
      try {
        const res = await this.api('/candidate/job-alerts/' + a.id, { method: 'PATCH', body: { is_active: !a.is_active } })
        const saved = res.data?.alert
        const i = this.alertItems.findIndex((x) => x.id === a.id)
        if (saved && i !== -1) this.alertItems.splice(i, 1, saved)
        this.showToast(a.is_active ? 'Alert paused' : 'Alert resumed')
      } catch (e) { this.showToast(e.message, true) }
    },

    async alertDelete(a) {
      const ok = await this.ask({ title: 'Delete this alert?', message: a.summary, ok: 'Delete', danger: true })
      if (!ok) return
      try {
        await this.api('/candidate/job-alerts/' + a.id, { method: 'DELETE' })
        this.alertItems = this.alertItems.filter((x) => x.id !== a.id)
        this.showToast('Alert deleted')
      } catch (e) { this.showToast(e.message, true) }
    },

    // ── My Plan (subscription) ────────────────────────────────────────
    subData: null,
    subSide: 'jobSeeker',
    subBusy: '', // the plan id being bought / switched to

    async onSubscription() {
      this.subSide = this._audience
      await this._subLoad()
    },

    async _subLoad() {
      const res = await this.api('/subscription')
      this.subData = res.data || null
    },

    /** The side on screen first, then the other — both live on one account. */
    get subSides() {
      return this.mode === 'recruiter' ? ['recruiter', 'jobSeeker'] : ['jobSeeker', 'recruiter']
    },

    get subPlans() { return this.subData?.plans?.[this.subSide] || [] },
    get subActive() { return this.subData?.active?.[this.subSide] || null },
    subIsCurrent(plan) { return plan.id === this.subActive?.plan_id },

    async subChoose(plan) {
      if (this.subIsCurrent(plan) || this.subBusy) return
      const audience = this.subSide
      this.subBusy = plan.id
      try {
        if (plan.is_free) {
          await this.api('/subscription', { method: 'POST', body: { audience, plan_id: plan.id } })
        } else {
          // Open an order, then capture it — the app's two-step payment flow.
          const method = await this.choosePaymentMethod(plan.price_label || this.rupees(plan.price_paise))
          if (!method) { this.subBusy = ''; return }
          const res = await this.api('/payments/orders', { method: 'POST', body: { audience, plan_id: plan.id, method } })
          const order = res.data?.order
          if (!order?.id) throw new Error('Could not start the payment. Please try again.')
          await this.confirmOrder(order.id)
        }
        await this._subLoad()
        this.showToast(`${plan.name} plan activated`)
      } catch (e) {
        this.showToast(e.message, true)
        this._subLoad().catch(() => {})
      }
      this.subBusy = ''
    },

    // ── Static content pages ──────────────────────────────────────────
    pageContent: null, // { pages, faqs } from GET /content, cached per session
    pageSlug: '',
    pageOpenFaq: -1,

    async onPage(params) {
      this.pageSlug = PAGE_ALIASES[String(params.slug || '').toLowerCase()] || ''
      this.pageOpenFaq = -1
      if (!this.pageContent) {
        const res = await this.api('/content')
        this.pageContent = res.data || { pages: {}, faqs: [] }
      }
    },

    get pageData() {
      if (!this.pageSlug || this.pageSlug === 'faq') return null
      return this.pageContent?.pages?.[this.pageSlug] || null
    },

    get pageTitle() {
      return this.pageData?.title || PAGE_TITLES[this.pageSlug] || 'Page not found'
    },

    get pageFaqs() { return this.pageContent?.faqs || [] },

    /** `## ` starts a heading; blank lines split paragraphs — ContentPage.blocks. */
    get pageBlocks() {
      const body = String(this.pageData?.body || '')
      if (!body.trim()) return []
      const out = []
      body.split(/\n\s*\n/).forEach((raw) => {
        const block = raw.trim()
        if (!block) return
        if (!block.startsWith('## ')) { out.push({ h: false, text: block }); return }
        const lines = block.split('\n')
        out.push({ h: true, text: lines[0].slice(3).trim() })
        const rest = lines.slice(1).join('\n').trim()
        if (rest) out.push({ h: false, text: rest })
      })
      return out
    },

    /** Only the two legal pages show "Last updated", as in the app. */
    get pageUpdated() {
      if (!['terms', 'privacy'].includes(this.pageSlug) || !this.pageData?.updated_at) return ''
      return this.fmtDate(this.pageData.updated_at)
    },

    get pageMeta() { return this.pageData?.meta || {} },
  })
})()
