/**
 * /account — the job seeker's profile as one page.
 *
 * Every section is a card that reads like a profile and turns into its own
 * form in place ("Edit" → Save / Cancel), one card at a time. The form model,
 * rules and endpoints are ProfileKit's (account-profile-kit.js).
 */
document.addEventListener('alpine:init', () => {
  const K = window.ProfileKit

  /** The page's sections, in the app's order, and what "complete" means for each. */
  const SECTIONS = [
    { id: 'personal', label: 'Personal information', parts: ['personal'] },
    { id: 'education', label: 'Education', parts: ['qualification'] },
    { id: 'experience', label: 'Experience', parts: ['experience'] },
    { id: 'languages', label: 'Languages', parts: ['languages'] },
    { id: 'preferences', label: 'Job preferences', parts: ['location'] },
    { id: 'availability', label: 'Availability', parts: ['availability'] },
    { id: 'about', label: 'About me & skills', parts: ['about', 'skills'] },
    { id: 'video', label: 'Intro video', parts: ['introVideo'] },
  ]

  Alpine.data('accountProfile', () => K.merge(K.mixin(), {
    /** Which card is in edit mode: personal | languages | preferences | availability | about | edu | exp. */
    editing: '',
    active: 'personal',
    oneOffs: [],
    buying: false,

    async init() {
      await Inthes.whenReady()
      if (!this.$store.auth.signedIn) {
        window.addEventListener('inthes:signed-in', () => this.load(), { once: true })
        return
      }
      this.load()
    },

    async load() {
      // The strength is the server's number — re-read on every visit.
      this.profFill()
      try { await Inthes.loadMe() } catch (e) { /* signed out */ }
      // The re-read can land after the visitor has opened a card — never wipe their edit.
      if (!this.editing && !this.profVideoDraft) this.profFill()
      this.$nextTick(() => {
        this.spy()
        // A link like /account#languages lands on that card.
        const id = location.hash.slice(1)
        if (id) this.jump(id, false)
      })
      Inthes.api('/payments/one-off').then((r) => { this.oneOffs = r.data?.purchases || [] }).catch(() => {})
    },

    /** Scroll-spy for the mini-nav. */
    spy() {
      if (!('IntersectionObserver' in window)) return
      const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => { if (e.isIntersecting) this.active = e.target.id.replace('sec-', '') })
      }, { rootMargin: '-90px 0px -65% 0px' })
      SECTIONS.forEach((s) => { const el = document.getElementById('sec-' + s.id); if (el) io.observe(el) })
    },

    get sections() {
      return SECTIONS.map((s) => {
        const ps = s.parts.map((p) => K.progressFor(this.me, p))
        const filled = ps.reduce((a, p) => a + p.filled, 0)
        const total = ps.reduce((a, p) => a + p.total, 0)
        return { ...s, complete: filled === total, empty: filled === 0, status: filled === total ? 'Complete' : (filled === 0 ? 'Missing' : `${filled} of ${total}`) }
      })
    },
    section(id) { return this.sections.find((s) => s.id === id) || {} },

    /**
     * "Complete these" — the app's prompts (resume, summary, languages, photo,
     * availability, skills, video), with the details most jobs filter on
     * first when they are missing. Each one jumps to the card that fixes it.
     */
    get boosts() {
      const p = this.me
      if (!p) return []
      const all = []
      if (!K.trim(p.name)) all.push({ label: 'Add your name', gain: 'Required', to: 'personal', edit: 'personal' })
      if (!p.qualification && !(p.educations || []).length) all.push({ label: 'Add your education', gain: 'Required', to: 'education', edit: 'edu' })
      if (!p.experience && !(p.experiences || []).length) all.push({ label: 'Add experience or mark fresher', gain: 'Required', to: 'experience', edit: '' })
      if (!(p.location || []).length) all.push({ label: 'Pick preferred cities', gain: 'Important', to: 'preferences', edit: 'preferences' })
      if (!p.resume) all.push({ label: 'Add your resume', gain: '+15%', href: '/account/resume' })
      if (!p.about) all.push({ label: 'Add a profile summary', gain: '+10%', to: 'about', edit: 'about' })
      if (!(p.languages || []).length) all.push({ label: 'Add languages', gain: '+5%', to: 'languages', edit: 'languages' })
      if (!p.photo) all.push({ label: 'Add a profile photo', gain: '+5%', to: 'top', photo: true })
      if (!K.progressFor(p, 'availability').complete) all.push({ label: 'Add your availability', gain: 'Recommended', to: 'availability', edit: 'availability' })
      if (!K.progressFor(p, 'skills').complete) all.push({ label: 'Add skills & interests', gain: 'Recommended', to: 'about', edit: 'about' })
      if (!p.intro_video_url) all.push({ label: 'Record a 1-min intro video', gain: 'Recommended', to: 'video', edit: '' })
      return all
    },

    get headline() {
      const p = this.me || {}
      return [p.qualification || 'No qualification yet', p.experience || 'Experience not set'].join(' · ')
    },
    get place() {
      const p = this.me || {}
      return [p.home_city, p.home_state].filter(Boolean).join(', ') || (p.location || [])[0] || ''
    },

    /** The watermark-free one-off, or null when bought, included, or not loaded. */
    get cleanOffer() {
      if (!this.me || this.me.resume_watermark_free) return null
      return this.oneOffs.find((o) => o.id === 'resume_watermark_free') || null
    },

    // ── navigation ──────────────────────────────────────────────────────
    jump(id, edit) {
      const el = document.getElementById(id === 'top' ? 'profile-top' : 'sec-' + id)
      if (!el) return
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      history.replaceState(null, '', '#' + id)
      if (edit) this.startEdit(edit)
    },
    boostGo(b) {
      if (b.href) { location.href = b.href; return }
      if (b.photo) { this.$refs.photoInput?.click(); return }
      this.jump(b.to, b.edit)
    },

    // ── edit mode ───────────────────────────────────────────────────────
    startEdit(key) {
      this.profFill()
      this.editing = key
      if (key === 'edu') this.pf.edu = K.emptyEdu()
      if (key === 'exp') this.pf.exp = K.emptyExp()
    },
    cancelEdit() {
      this.editing = ''
      this.profFill()
    },
    editEdu(e) {
      this.startEdit('edu')
      this.profEduEdit(e)
    },
    editExp(x) {
      this.startEdit('exp')
      this.profExpEdit(x)
    },
    async _profDone(message) {
      await Inthes.loadMe()
      this.editing = ''
      this.profFill()
      Inthes.toast(message)
    },

    async saveEdu() {
      const problem = this._profEduProblem()
      if (problem) { Inthes.toast(problem, true); return }
      const isNew = !this.pf.edu.id
      await this._profRun(async () => {
        await this._profEduWrite()
        await this._profDone(isNew ? 'Education added' : 'Education updated')
      })
    },
    async saveExp() {
      const problem = this._profExpProblem()
      if (problem) { Inthes.toast(problem, true); return }
      const isNew = !this.pf.exp.id
      await this._profRun(async () => {
        await this._profExpWrite()
        await Inthes.loadMe()
        await this._profSyncBand()
        await this._profDone(isNew ? 'Experience added' : 'Experience updated')
      })
    },

    // ── resume ──────────────────────────────────────────────────────────
    async buyClean() {
      const offer = this.cleanOffer
      if (!offer || this.buying) return
      const method = await Inthes.pay(offer.price_label || Inthes.rupees(offer.price_paise))
      if (!method) return
      this.buying = true
      try {
        const res = await Inthes.api('/payments/orders', { method: 'POST', body: { audience: 'jobSeeker', plan_id: offer.id, method } })
        const order = res.data?.order || res.data
        await Inthes.confirmOrder(order.id)
        await Inthes.loadMe()
        Inthes.toast('Your resume downloads without the INTHES mark.')
      } catch (e) {
        Inthes.toast(e.message || "That payment didn't go through.", true)
      }
      this.buying = false
    },

    // ── read-mode formatting ────────────────────────────────────────────
    yn(v) { return v === true ? 'Yes' : v === false ? 'No' : '' },
    langLine(l) { const lv = (this.me?.language_levels || {})[l]; return lv ? lv : '' },
    eduLine(e) { return [e.year, e.course_type, e.percentage != null ? Number(e.percentage) + '%' : ''].filter(Boolean).join(' · ') },
    expLine(x) { return [this.profPeriod(x), this.profDuration(x), (x.cities || []).join(', ')].filter(Boolean).join(' · ') },
    /** The About text without the team line — that line shows under Experience. */
    get aboutBody() { return (this.me?.about || '').split('\n').filter((l) => !l.trimStart().startsWith('Managed a team of')).join('\n').trim() },
    get teamLineSaved() { return (this.me?.about || '').split('\n').find((l) => l.trimStart().startsWith('Managed a team of')) || '' },
  }))
})
