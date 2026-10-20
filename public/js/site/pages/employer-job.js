/**
 * /employer/jobs/{id} — one posting's workspace (the app's Job Management,
 * Applicants and Applicant Profile on one page).
 *
 * Applicants is an ATS-style table — search, status tabs, facet filters,
 * sort, multi-select with bulk Shortlist / Reject — and a row opens the
 * applicant in a side drawer whose URL is `?a=<applicationId>`, so it can be
 * linked and the back button closes it. Tabs (`?tab=`) for stats and the
 * job's own details.
 */
document.addEventListener('alpine:init', () => {
  const SORTS = [
    { id: 'newest', label: 'Newest application' },
    { id: 'oldest', label: 'Oldest application' },
    { id: 'most_experience', label: 'Most experience' },
    { id: 'highest_strength', label: 'Highest profile strength' },
  ]
  const FACETS = [
    { key: 'experience', label: 'Experience' },
    { key: 'qualification', label: 'Qualification' },
    { key: 'location', label: 'Location' },
  ]
  const blankInterview = () => ({ open: false, date: '', time: '', type: 'online', location_or_link: '', notes: '', busy: false, error: '' })

  Alpine.data('empJob', (props = {}) => ({
    ...Emp.helpers,
    ...Emp.jobActions(),
    SORTS, FACETS,
    APP_STATUSES: Emp.APP_STATUSES,
    jobId: props.jobId,
    tab: 'applicants',
    loading: true,
    loadError: '',
    job: null,
    stats: null,
    list: [],
    listLoading: true,
    listError: '',

    // table state
    q: '',
    status: '',
    sort: 'newest',
    filters: { experience: [], qualification: [], location: [] },
    facetOpen: '',
    selected: [],
    bulkBusy: false,

    // drawer
    openId: '',
    detail: null,
    detailLoading: false,
    introUrl: '',
    introBusy: false,
    resumeBusy: false,
    statusBusy: false,
    moreOpen: false,
    aboutOpen: false,
    interview: blankInterview(),

    async init() {
      this.readUrl()
      window.addEventListener('popstate', () => this.readUrl())
      await Inthes.whenReady()
      if (Alpine.store('auth').signedIn) this.load()
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    readUrl() {
      const p = new URLSearchParams(location.search)
      this.tab = ['applicants', 'stats', 'details'].includes(p.get('tab')) ? p.get('tab') : 'applicants'
      const a = p.get('a') || ''
      if (a !== this.openId) this.showApplicant(a)
    },

    setTab(t) {
      this.tab = t
      const url = new URL(location.href)
      if (t === 'applicants') url.searchParams.delete('tab'); else url.searchParams.set('tab', t)
      url.searchParams.delete('a')
      this.openId = ''
      history.replaceState(null, '', url)
    },

    async load() {
      this.loading = true
      this.loadError = ''
      try {
        const jobs = await Emp.loadMine()
        this.job = jobs.find((j) => j.id === this.jobId) || null
        if (!this.job) this.loadError = 'This posting is no longer available.'
      } catch (e) { this.loadError = e.message }
      this.loading = false
      if (!this.job) return
      Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.jobId)}/stats`).then((r) => { this.stats = r.data || null }).catch(() => {})
      this.loadApplicants()
    },

    async loadApplicants() {
      this.listLoading = true
      this.listError = ''
      try {
        const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.jobId)}/applicants?per_page=100&sort=newest`)
        this.list = res.data || []
      } catch (e) { this.listError = e.message }
      this.listLoading = false
      if (this.openId) this.showApplicant(this.openId)
    },

    onJobUpdated(job) { this.job = Object.assign({}, this.job, job) },
    async editJob() { location.href = this.jobUrl(this.job) + '/edit' },

    get total() { return this.stats?.total_applicants ?? this.list.length ?? this.job?.applicants_count ?? 0 },
    statusCount(s) { return s ? this.list.filter((a) => a.status === s).length : this.list.length },

    // ── the table ──────────────────────────────────────────────────────
    apName: Emp.apName,
    apDesignation: Emp.apDesignation,
    apLocation: Emp.apLocation,
    apStrength: Emp.apStrength,
    apCurrentRole: Emp.apCurrentRole,
    strengthLabel: Emp.strengthLabel,

    get facets() {
      const uniq = (fn) => [...new Set(this.list.map(fn).filter(Boolean))].sort()
      return {
        experience: uniq((a) => a.profile?.experience),
        qualification: uniq((a) => a.profile?.qualification),
        location: uniq((a) => Emp.apLocation(a)),
      }
    },
    get filterCount() { const f = this.filters; return f.experience.length + f.qualification.length + f.location.length },
    get hasFilters() { return !!(this.status || this.filterCount || this.q.trim()) },
    clearFilters() { this.filters = { experience: [], qualification: [], location: [] }; this.status = ''; this.q = '' },

    get rows() {
      let list = this.list.slice()
      const q = this.q.trim().toLowerCase()
      if (q) {
        list = list.filter((a) => [Emp.apName(a), a.profile?.qualification, Emp.apDesignation(a), Emp.apLocation(a), a.profile?.phone]
          .join(' ').toLowerCase().includes(q))
      }
      if (this.status) list = list.filter((a) => a.status === this.status)
      const f = this.filters
      if (f.experience.length) list = list.filter((a) => f.experience.includes(a.profile?.experience))
      if (f.qualification.length) list = list.filter((a) => f.qualification.includes(a.profile?.qualification))
      if (f.location.length) list = list.filter((a) => f.location.includes(Emp.apLocation(a)))
      const t = (a) => new Date(a.applied_at).getTime() || 0
      const cmp = {
        newest: (a, b) => t(b) - t(a),
        oldest: (a, b) => t(a) - t(b),
        most_experience: (a, b) => Emp.experienceRank(b.profile?.experience) - Emp.experienceRank(a.profile?.experience),
        highest_strength: (a, b) => Emp.apStrength(b) - Emp.apStrength(a),
      }[this.sort]
      return list.sort(cmp)
    },

    isSelected(id) { return this.selected.includes(id) },
    toggleSelect(id) { Inthes.toggleIn(this.selected, id) },
    get allSelected() { return this.rows.length > 0 && this.rows.every((a) => this.selected.includes(a.application_id)) },
    toggleAll() {
      if (this.allSelected) this.selected = this.selected.filter((id) => !this.rows.some((a) => a.application_id === id))
      else this.selected = [...new Set([...this.selected, ...this.rows.map((a) => a.application_id)])]
    },

    /**
     * Bulk Shortlist / Reject: one PATCH per applicant (there is no bulk
     * call), each notifying its candidate. Shortlisting leaves selected and
     * already-shortlisted applicants alone; rejecting skips the rejected.
     */
    async bulk(status) {
      if (this.bulkBusy) return
      const picked = this.list.filter((a) => this.selected.includes(a.application_id))
      const targets = picked.filter((a) => status === 'shortlisted' ? a.status === 'applied' || a.status === 'rejected' : a.status !== 'rejected')
      if (!targets.length) { Inthes.toast(status === 'shortlisted' ? 'Those candidates are already shortlisted or selected.' : 'Those candidates are already not selected.', true); return }
      const n = targets.length
      const who = n === 1 ? '1 candidate' : `${n} candidates`
      const ok = await Inthes.confirm(status === 'shortlisted'
        ? { title: `Shortlist ${who}?`, message: 'Each candidate is notified that they were shortlisted.', ok: 'Shortlist' }
        : { title: `Reject ${who}?`, message: 'Each candidate is told their application was not selected.', ok: 'Reject', danger: true })
      if (!ok) return
      this.bulkBusy = true
      let done = 0
      for (const a of targets) {
        try {
          const res = await Inthes.api(this.apPath(a, '/status'), { method: 'PATCH', body: { status } })
          this.syncRow(Object.assign({}, a, res.data || { status }))
          done++
        } catch (e) { /* counted below */ }
      }
      this.bulkBusy = false
      this.selected = []
      this.refreshStats()
      const verb = status === 'shortlisted' ? 'shortlisted' : 'marked not selected'
      if (done === n) Inthes.toast(`${done === 1 ? '1 candidate' : done + ' candidates'} ${verb} — notified`)
      else Inthes.toast(`${done} of ${n} updated — some changes failed, please try again.`, true)
    },

    refreshStats() {
      Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.jobId)}/stats`).then((r) => { this.stats = r.data || null }).catch(() => {})
    },

    apPath(a, suffix = '') {
      return `/recruiter/jobs/${encodeURIComponent(a.job_id || this.jobId)}/applicants/${encodeURIComponent(a.application_id)}${suffix}`
    },

    /** Keeps the list row and the open drawer in step after a change. */
    syncRow(fresh) {
      const i = this.list.findIndex((x) => x.application_id === fresh.application_id)
      if (i !== -1) this.list.splice(i, 1, Object.assign({}, this.list[i], { status: fresh.status, interview: fresh.interview ?? this.list[i].interview, stage_updated_at: fresh.stage_updated_at }))
      if (this.detail?.application_id === fresh.application_id) this.detail = Object.assign({}, this.detail, fresh)
    },

    // ── the drawer ─────────────────────────────────────────────────────
    open(a) {
      const url = new URL(location.href)
      const wasOpen = !!this.openId
      url.searchParams.set('a', a.application_id)
      if (wasOpen) history.replaceState(null, '', url); else history.pushState({ drawer: 1 }, '', url)
      this.showApplicant(a.application_id)
    },

    close() {
      if (!this.openId) return
      if (history.state?.drawer) { history.back(); return }
      const url = new URL(location.href)
      url.searchParams.delete('a')
      history.replaceState(null, '', url)
      this.showApplicant('')
    },

    /** Shows what the list already has at once, then the full record (with any later profile changes). */
    async showApplicant(id) {
      this.openId = id
      this.moreOpen = false
      this.aboutOpen = false
      this.introUrl = ''
      this.interview = blankInterview()
      document.documentElement.classList.toggle('overflow-hidden', !!id && window.innerWidth < 1024)
      if (!id) { this.detail = null; return }
      const row = this.list.find((a) => a.application_id === id)
      this.detail = row ? Object.assign({}, row) : null
      if (!this.job) return
      this.detailLoading = true
      try {
        const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.jobId)}/applicants/${encodeURIComponent(id)}`)
        if (this.openId === id) this.detail = res.data || this.detail
      } catch (e) {
        if (this.openId === id && !this.detail) Inthes.toast(e.status === 404 ? 'That application was not found.' : e.message, true)
      }
      this.detailLoading = false
    },

    get position() { return this.rows.findIndex((a) => a.application_id === this.openId) },
    step(delta) {
      const next = this.rows[this.position + delta]
      if (next) this.open(next)
    },

    get dp() { return this.detail?.profile || {} },
    get live() { return this.detail?.live_profile || null },
    get about() { return this.dp.about || this.live?.about || '' },
    get laterAbout() { return !(this.dp.about || '').trim() && !!(this.live?.about || '').trim() },
    get hasVideo() { return !!this.dp.intro_video_url },
    get laterVideo() { return !this.dp.intro_video_url && !!this.live?.intro_video_url },

    /** Every status change notifies the candidate server-side, hence the suffix. */
    async setStatus(status, message) {
      if (!this.detail || this.statusBusy) return
      this.statusBusy = true
      this.moreOpen = false
      try {
        const res = await Inthes.api(this.apPath(this.detail, '/status'), { method: 'PATCH', body: { status } })
        this.syncRow(Object.assign({}, this.detail, res.data || { status }))
        this.refreshStats()
        Inthes.toast(message + ' — candidate notified')
      } catch (e) { Inthes.toast(e.message, true) }
      this.statusBusy = false
    },
    shortlist() { return this.setStatus('shortlisted', 'Candidate Shortlisted ✓') },
    markSelected() { return this.setStatus('selected', 'Candidate marked as Selected') },
    reopen() { return this.setStatus('shortlisted', 'Application reopened') },
    changeStatus(s) { return this.setStatus(s, 'Status updated to ' + Emp.APP_LABEL[s]) },
    async reject() {
      this.moreOpen = false
      const ok = await Inthes.confirm({ title: 'Reject Candidate?', message: 'Are you sure you want to reject this application?', ok: 'Reject', danger: true })
      if (ok) this.setStatus('rejected', 'Application rejected')
    },

    openInterview() {
      this.moreOpen = false
      const i = this.detail?.interview
      this.interview = Object.assign(blankInterview(), {
        open: true, type: i?.type === 'inPerson' ? 'inPerson' : 'online',
        // Rescheduling starts from the current slot.
        date: /^\d{4}-\d{2}-\d{2}/.test(i?.date || '') ? i.date.slice(0, 10) : '',
        location_or_link: i?.location_or_link || '', notes: i?.notes || '',
        time: (() => {
          const m = String(i?.time || '').match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i)
          if (!m) return ''
          let h = Number(m[1]) % 12
          if (m[3].toUpperCase() === 'PM') h += 12
          return String(h).padStart(2, '0') + ':' + m[2]
        })(),
      })
    },
    get interviewMinDate() { return new Date().toISOString().slice(0, 10) },
    get interviewMaxDate() { return new Date(Date.now() + 180 * 864e5).toISOString().slice(0, 10) },
    get interviewReady() { const v = this.interview; return !!(v.date && v.time && v.location_or_link.trim()) },

    /**
     * The time goes as the app's picker writes it (`11:30 AM`); the date as
     * `yyyy-MM-dd` (API_REFERENCE §9) — the app's `12 Nov 2026` passes the
     * `date` rule but the column rejects it.
     */
    async scheduleInterview() {
      const v = this.interview
      if (!this.interviewReady || v.busy) return
      const [hh, mm] = v.time.split(':').map(Number)
      const body = {
        date: v.date,
        time: `${hh % 12 === 0 ? 12 : hh % 12}:${String(mm).padStart(2, '0')} ${hh < 12 ? 'AM' : 'PM'}`,
        type: v.type,
        location_or_link: v.location_or_link.trim(),
        notes: v.notes.trim() || null,
      }
      v.busy = true
      v.error = ''
      try {
        const res = await Inthes.api(this.apPath(this.detail, '/interview'), { method: 'POST', body })
        this.syncRow(Object.assign({}, this.detail, res.data || {}))
        this.refreshStats()
        this.interview.open = false
        Inthes.toast('Interview scheduled with ' + Emp.apName(this.detail))
      } catch (e) { v.error = e.message }
      v.busy = false
    },

    interviewDate(i) { return /^\d{4}-\d{2}-\d{2}/.test(i?.date || '') ? Emp.day(i.date) : (i?.date || '') },

    /** A resume link minted at the click — the one on the payload expires in minutes. */
    async resume(download) {
      if (this.resumeBusy || !this.detail) return
      this.moreOpen = false
      // Opened before the await, or the browser treats it as a popup.
      const win = download ? null : window.open('', '_blank')
      this.resumeBusy = true
      try {
        const res = await Inthes.api(this.apPath(this.detail, '/resume'))
        const { url, name } = res.data || {}
        if (!url) throw new Error(download ? "Couldn't download the resume" : "Couldn't open the resume")
        if (download) {
          const a = document.createElement('a')
          a.href = url
          a.download = name || 'resume.pdf'
          a.rel = 'noopener'
          document.body.appendChild(a)
          a.click()
          a.remove()
        } else if (win) win.location.href = url
        else window.location.href = url
      } catch (e) {
        if (win) win.close()
        Inthes.toast(e.message, true)
      }
      this.resumeBusy = false
    },

    async playIntro() {
      if (this.introBusy) return
      this.introBusy = true
      try {
        const res = await Inthes.api(this.apPath(this.detail, '/intro-video'))
        if (!res.data?.url) throw new Error("Couldn't open the intro video")
        this.introUrl = res.data.url
      } catch (e) { Inthes.toast(e.message, true) }
      this.introBusy = false
    },

    call() { if (this.dp.phone) window.location.href = 'tel:' + this.dp.phone },
    email() {
      if (!this.dp.email) return
      const subject = 'Regarding your application for ' + (this.job?.title || 'this role')
      window.location.href = 'mailto:' + this.dp.email + '?subject=' + encodeURIComponent(subject)
    },
    messageUrl() { return '/employer/messages?c=' + encodeURIComponent(this.detail?.application_id || '') },
  }))
})
