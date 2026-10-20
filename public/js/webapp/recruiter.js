/**
 * The recruiter ("Post a Job") side — the app's recruiter shell on the web.
 *
 * Mirrors lib/app/modules/recruiter/*, post_job and recruiter_profile:
 * Hiring Dashboard, My Posted Jobs, the four-step Post a Job wizard (Job
 * details → Candidate requirements → Company details → Choose plan), Job
 * Management, Applicants, Applicant Profile, Companies and Contact details.
 *
 * Everything is prefixed `r` — the component is shared with every other part.
 * Picked files live outside Alpine state (`FILES`): a reactive Proxy around a
 * File is not a Blob, and FormData refuses it.
 */
;(function () {
  const STEP_TITLES = ['Job details', 'Candidate requirements', 'Company details', 'Choose plan']
  const ANY_EXPERIENCE = 'Any experience'
  /** Every new posting asks for these before Apply goes out (PostJobController.defaultRequiredFields). */
  const DEFAULT_REQUIRED_FIELDS = ['qualification', 'experience', 'resume']
  /** Optional fields an employer can empty on an edit — sent as null (JobDraft.clearable). */
  const CLEARABLE = ['about', 'locality', 'contact_phone', 'whatsapp_phone']
  /** Every plan is bought at District reach — the app offers no other. */
  const REACH = 'district'
  const MAX_OPENINGS = 1000

  const STATUS_LABEL = {
    pending_approval: 'Pending approval', active: 'Active', paused: 'Paused', draft: 'Draft',
    closed: 'Closed', expired: 'Expired', rejected: 'Rejected',
  }
  const STATUS_DOT = {
    pending_approval: 'bg-warning', active: 'bg-success', paused: 'bg-warning', draft: 'bg-ink-muted',
    closed: 'bg-ink-secondary', expired: 'bg-danger', rejected: 'bg-danger',
  }
  const APP_STATUSES = ['applied', 'shortlisted', 'selected', 'rejected']
  /** The product's own wording — never "rejected" where a person reads it. */
  const APP_LABEL = { applied: 'Applied', shortlisted: 'Shortlisted', selected: 'Selected', rejected: 'Not selected' }
  const APP_DOT = { applied: 'bg-info', shortlisted: 'bg-primary', selected: 'bg-success', rejected: 'bg-danger' }
  const SORTS = [
    { id: 'newest', label: 'Newest application' },
    { id: 'oldest', label: 'Oldest application' },
    { id: 'most_experience', label: 'Most experience' },
    { id: 'highest_strength', label: 'Highest profile strength' },
  ]
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

  /** Files picked in the two company forms, by form ('post' | 'org'). */
  const FILES = { post: { doc: null, logo: null }, org: { doc: null, logo: null } }

  const blankCompany = () => ({
    id: '', hiring_type: 'company', name: '', email: '', industry: '', size: '', about: '',
    address: '', website: '', gst_number: '', doc_name: '', doc_new: false, logo: false,
    logo_url: '', logo_new: false, logo_preview: '', verified: false,
  })

  const blankForm = () => ({
    title: '', role: '', show_description: false, about: '', openings: '1',
    salary_min: '', salary_max: '', shift: '', type: '',
    state: '', city: '', locality: '', pincode: '', latitude: null, longitude: null,
    charges_fee: false,
    min_education: '', experience: '', preferred_gender: 'Any', skills: [], qualifications: [],
    contact_mode: 'calls_whatsapp', contact_phone: '', same_whatsapp: true, whatsapp_phone: '',
    required_fields: DEFAULT_REQUIRED_FIELDS.slice(),
  })

  const blankRun = () => ({
    ready: false, runFor: null, step: 0, editingId: null, draftId: null, submitted: null, busy: false,
    salaryError: '', locating: false,
    f: blankForm(), co: blankCompany(), org: null,
    plans: null, plansLoading: false, tier: '', quantity: 1, paying: false,
  })

  const withScheme = (url) => {
    const t = String(url || '').trim()
    if (!t) return ''
    return /^https?:\/\//i.test(t) ? t : 'https://' + t
  }
  const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)
  const isUrl = (v) => /^https?:\/\/[^\s/.]+(\.[^\s/.]+)+(\/\S*)?$/i.test(v)
  const tenDigits = (v) => String(v || '').replace(/\D/g, '').slice(-10)

  /** `13 Oct 2026` — the app's `dd MMM yyyy`. */
  function day(iso) {
    if (!iso) return '-'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '-'
    return `${String(d.getDate()).padStart(2, '0')} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`
  }

  /** The company form's required fields (CompanyFormFields.problem). */
  function companyProblem(co) {
    if (!co.name.trim()) return 'Enter the company name'
    if (!co.email.trim()) return 'Enter the company email'
    if (!isEmail(co.email.trim())) return 'Enter a valid company email'
    if (!co.size) return 'Select the company size'
    if (co.website.trim() && !isUrl(withScheme(co.website))) return 'Enter a valid website, e.g. www.yourcompany.com'
    if (!co.doc_name) return 'Upload your company document'
    return null
  }

  /** An organisation from the API, as the company form edits it. */
  function companyFrom(org) {
    const co = blankCompany()
    if (!org) return co
    Object.assign(co, {
      id: org.id, hiring_type: org.hiring_type || 'company', name: org.name || '', email: org.email || '',
      industry: org.industry || '', size: org.size || '', about: org.about || '', address: org.address || '',
      website: org.website || '', gst_number: org.gst_number || '', doc_name: org.document_name || '',
      logo: !!org.logo, logo_url: org.logo_url || '', verified: !!org.verified,
    })
    return co
  }

  function companyBody(co) {
    const orNull = (v) => (String(v || '').trim() ? String(v).trim() : null)
    return {
      name: co.name.trim(),
      email: orNull(co.email),
      hiring_type: orNull(co.hiring_type),
      industry: orNull(co.industry),
      size: orNull(co.size),
      address: orNull(co.address),
      website: orNull(withScheme(co.website)),
      gst_number: orNull(co.gst_number.toUpperCase()),
      about: orNull(co.about),
    }
  }

  function experienceRank(band) {
    const m = String(band || '').match(/(\d+)/)
    return m ? Number(m[1]) : 0
  }

  WebApp.part({
    R_STEPS: STEP_TITLES,
    R_SORTS: SORTS,
    R_APP_STATUSES: APP_STATUSES,
    R_REACH: REACH,

    // ── shared recruiter data ─────────────────────────────────────────
    rJobsList: [],
    rJobsLoading: false,
    rJobsLoaded: false,
    rOrgs: [],
    rOrgsLoaded: false,
    rContact: { contact_person_name: '', contact_email: '', contact_phone: '' },
    rContactLoaded: false,
    rPlanName: '',

    rStatusLabel(s) { return STATUS_LABEL[s] || s || '' },
    rStatusDot(s) { return STATUS_DOT[s] || 'bg-ink-muted' },
    rAppLabel(s) { return APP_LABEL[s] || s || '' },
    rAppDot(s) { return APP_DOT[s] || 'bg-ink-muted' },
    rDay: day,

    async rLoadMine() {
      this.rJobsLoading = true
      try {
        const res = await this.api('/recruiter/jobs/mine?per_page=100')
        this.rJobsList = res.data || []
        this.rJobsLoaded = true
      } finally {
        this.rJobsLoading = false
      }
      return this.rJobsList
    },

    async rLoadOrgs() {
      const res = await this.api('/recruiter/organisations')
      this.rOrgs = res.data || []
      this.rOrgsLoaded = true
      return this.rOrgs
    },

    async rLoadContact() {
      const res = await this.api('/recruiter/profile')
      // Nulls become '' — the form trims these.
      const d = res.data || {}
      this.rContact = { contact_person_name: d.contact_person_name || '', contact_email: d.contact_email || '', contact_phone: d.contact_phone || '' }
      this.rContactLoaded = true
      return this.rContact
    },

    rJobById(id) { return this.rJobsList.find((j) => j.id === id) || null },

    _onSignOutRecruiter() {
      this.rJobsList = []; this.rJobsLoaded = false
      this.rOrgs = []; this.rOrgsLoaded = false
      this.rContact = { contact_person_name: '', contact_email: '', contact_phone: '' }; this.rContactLoaded = false
      this.rPlanName = ''
      this.rPost = blankRun()
      this.rJob = null; this.rJobStats = null
      this.rAppl = { job: null, list: [], loading: false }
      this.rApplicantData = null
      FILES.post = { doc: null, logo: null }; FILES.org = { doc: null, logo: null }
    },

    // ── Hiring Dashboard ──────────────────────────────────────────────
    async onRHome() { await this.rLoadMine() },

    get rActiveCount() { return this.rJobsList.filter((j) => j.posting_status === 'active').length },
    get rTotalApplicants() { return this.rJobsList.reduce((s, j) => s + (j.applicants_count || 0), 0) },
    get rRecentJobs() { return this.rJobsList.slice(0, 3) },

    // ── My Posted Jobs ────────────────────────────────────────────────
    async onRJobs() { await this.rLoadMine() },

    rAwaitingPayment(job) { return job.hiring_stage === 'payment_pending' },
    rDocMissing(job) { return job.hiring_stage === 'paid_document_missing' || job.hiring_stage === 'posted_document_missing' },

    /**
     * The explanation under a posting whose status alone doesn't say what
     * happened or what to do (my_jobs_view.dart `_PostingNotice`).
     * Returns `{ tone, icon, message, action }` — action: 'plan' | 'doc' | null.
     */
    rJobNotice(job) {
      const s = job.posting_status
      if (this.rAwaitingPayment(job)) {
        return { tone: 'warning', icon: 'wallet', message: 'Saved — choose a plan to make it live.', action: 'plan', label: 'Choose plan' }
      }
      if (s === 'rejected') {
        return { tone: 'danger', icon: 'xCircle', message: job.rejection_reason ? 'Not approved: ' + job.rejection_reason : 'This job post was not approved.', action: null }
      }
      if (s === 'pending_approval') {
        const doc = this.rDocMissing(job)
        return {
          tone: 'warning', icon: 'clock',
          message: doc ? 'Under review — upload your company document to get it live.'
            : job.hiring_stage === 'document_under_review' ? "Under review — we're checking your company document."
              : "Waiting for approval — you'll be notified once it's live.",
          action: doc ? 'doc' : null, label: 'Upload document',
        }
      }
      return null
    },

    /** A live posting under an unverified employer reaches nobody — said on the card. */
    rHiddenNotice(job) {
      if (job.posting_status !== 'active' || job.organisation_verified) return null
      const doc = job.hiring_stage === 'posted_document_missing'
      return {
        tone: 'brand', icon: 'eye',
        message: doc ? 'Not visible to candidates yet — upload your company document to get verified.'
          : `Not visible to candidates yet — we're verifying ${job.organisation}.`,
        action: doc ? 'doc' : null, label: 'Upload document',
      }
    },

    rNoticeClass(tone) {
      return {
        warning: 'border-warning/25 bg-warning-bg text-warning',
        danger: 'border-danger/25 bg-danger-bg text-danger',
        brand: 'border-primary-line bg-primary-light text-primary-dark',
      }[tone]
    },

    rNoticeAction(job, action) {
      if (action === 'plan') this.rEditJob(job, true)
      else if (action === 'doc') this.go('/r/orgs')
    },

    /**
     * Opens the posting in the wizard. Editing a live posting sends it back
     * for approval, so the consequence is spelled out first
     * (PostJobController.confirmEditing).
     */
    async rEditJob(job, toPlan = false) {
      if (job.posting_status === 'active') {
        const ok = await this.ask({
          title: 'Edit this live job post?',
          message: 'Changes need approval, so the post will be paused for candidates until an admin reviews it.',
          ok: 'Edit anyway', danger: true,
        })
        if (!ok) return
      }
      this.rPost.runFor = null
      this.go('/r/edit/' + encodeURIComponent(job.id) + (toPlan ? '?plan=1' : ''))
    },

    rShareJob(job) {
      if (!job.share_url) { this.showToast("This job can't be shared yet", true); return }
      this.share(job.title, job.share_url)
    },

    /** Pause / resume server-side (MyJobsController.toggleActive). */
    async rToggleJob(job) {
      if (job.posting_status === 'pending_approval') {
        this.showToast("That job post is still being reviewed — you'll be notified.", true)
        return
      }
      const next = job.posting_status === 'active' ? 'paused' : 'active'
      try {
        const res = await this.api(`/recruiter/jobs/${job.id}/status`, { method: 'PATCH', body: { status: next } })
        this.rReplaceJob(res.data)
        this.showToast(next === 'active' ? 'Job resumed' : 'Job paused')
      } catch (e) { this.showToast(e.message, true) }
    },

    async rCloseJob(job) {
      try {
        const res = await this.api(`/recruiter/jobs/${job.id}/status`, { method: 'PATCH', body: { status: 'closed' } })
        this.rReplaceJob(res.data)
        this.showToast('Job closed to new applications')
      } catch (e) { this.showToast(e.message, true) }
    },

    /** Swaps a fresh posting into every place it is shown, keeping the list-only count. */
    rReplaceJob(job) {
      if (!job) return
      const i = this.rJobsList.findIndex((j) => j.id === job.id)
      const merged = Object.assign({}, i === -1 ? {} : this.rJobsList[i], job)
      if (merged.applicants_count == null && i !== -1) merged.applicants_count = this.rJobsList[i].applicants_count
      if (i === -1) this.rJobsList.unshift(merged)
      else this.rJobsList.splice(i, 1, merged)
      if (this.rJob && this.rJob.id === job.id) this.rJob = merged
    },

    // ── Job Management ────────────────────────────────────────────────
    rJob: null,
    rJobStats: null,
    rJobMissing: false,

    async onRJob({ id }) {
      this.rJobMissing = false
      this.rJob = this.rJobById(id)
      this.rJobStats = null
      await this.rLoadMine()
      this.rJob = this.rJobById(id)
      if (!this.rJob) { this.rJobMissing = true; return }
      try {
        const res = await this.api(`/recruiter/jobs/${encodeURIComponent(id)}/stats`)
        this.rJobStats = res.data || null
      } catch (e) { /* the breakdown just stays hidden */ }
    },

    get rJobTotal() { return this.rJobStats?.total_applicants ?? this.rJob?.applicants_count ?? 0 },

    // ── Applicants ────────────────────────────────────────────────────
    rAppl: { job: null, list: [], loading: false, missing: false },
    rApplQuery: '',
    rApplStatus: '',
    rApplSort: 'newest',
    rApplFilters: { experience: [], qualification: [], location: [] },
    rApplDraft: { experience: [], qualification: [], location: [] },
    rApplSheet: '',

    async onRApplicants({ id }) {
      if (this.rAppl.job?.id !== id) {
        this.rApplQuery = ''; this.rApplStatus = ''; this.rApplSort = 'newest'
        this.rApplFilters = { experience: [], qualification: [], location: [] }
      }
      this.rApplSheet = ''
      this.rAppl = { job: this.rJobById(id), list: [], loading: true, missing: false }
      try {
        await this.rLoadMine()
        const job = this.rJobById(id)
        if (!job) { this.rAppl = { job: null, list: [], loading: false, missing: true }; return }
        const res = await this.api(`/recruiter/jobs/${encodeURIComponent(id)}/applicants?per_page=100&sort=newest`)
        this.rAppl = { job, list: res.data || [], loading: false, missing: false }
      } catch (e) {
        this.rAppl.loading = false
        throw e
      }
    },

    // The scan-level facts the card and filters read (ApplicantModel getters).
    rApName(a) { return a?.profile?.name || 'Applicant' },
    rApCurrentRole(a) {
      const ex = a?.profile?.experiences || []
      return ex.find((e) => e.currently_working) || ex[0] || null
    },
    rApDesignation(a) {
      const role = this.rApCurrentRole(a)
      if (role) return role.designation || ''
      return (a?.profile?.preferred_roles || [])[0] || ''
    },
    rApLocation(a) {
      const p = a?.profile || {}
      return p.home_city || (p.location || [])[0] || ''
    },
    rApStrength(a) { return Number(a?.profile?.profile_strength || 0) },

    rApplyApplicantFilters(filters) {
      let list = this.rAppl.list.slice()
      const q = this.rApplQuery.trim().toLowerCase()
      if (q) {
        list = list.filter((a) => [this.rApName(a), a.profile?.qualification, this.rApDesignation(a), this.rApLocation(a)]
          .join(' ').toLowerCase().includes(q))
      }
      if (this.rApplStatus) list = list.filter((a) => a.status === this.rApplStatus)
      if (filters.experience.length) list = list.filter((a) => filters.experience.includes(a.profile?.experience))
      if (filters.qualification.length) list = list.filter((a) => filters.qualification.includes(a.profile?.qualification))
      if (filters.location.length) list = list.filter((a) => filters.location.includes(this.rApLocation(a)))
      return list
    },

    get rApplFiltered() {
      const list = this.rApplyApplicantFilters(this.rApplFilters)
      const t = (a) => new Date(a.applied_at).getTime() || 0
      const cmp = {
        newest: (a, b) => t(b) - t(a),
        oldest: (a, b) => t(a) - t(b),
        most_experience: (a, b) => experienceRank(b.profile?.experience) - experienceRank(a.profile?.experience),
        highest_strength: (a, b) => this.rApStrength(b) - this.rApStrength(a),
      }[this.rApplSort]
      return list.sort(cmp)
    },

    get rApplFacets() {
      const uniq = (fn) => [...new Set(this.rAppl.list.map(fn).filter(Boolean))]
      return {
        experience: uniq((a) => a.profile?.experience),
        qualification: uniq((a) => a.profile?.qualification),
        location: uniq((a) => this.rApLocation(a)),
      }
    },

    get rApplFilterCount() {
      const f = this.rApplFilters
      return f.experience.length + f.qualification.length + f.location.length
    },

    get rApplHasFilters() { return !!(this.rApplStatus || this.rApplFilterCount || this.rApplQuery) },

    rOpenApplFilters() {
      this.rApplDraft = this.clone(this.rApplFilters)
      this.rApplSheet = 'filter'
    },

    rApplyDraft() {
      this.rApplFilters = this.clone(this.rApplDraft)
      this.rApplSheet = ''
    },

    rClearApplFilters() {
      this.rApplFilters = { experience: [], qualification: [], location: [] }
      this.rApplStatus = ''
      this.rApplQuery = ''
    },

    // ── Applicant Profile ─────────────────────────────────────────────
    rApplicantData: null,
    rApplicantJob: null,
    rApplicantLoading: false,
    rAboutOpen: false,
    rMoreOpen: false,
    rStatusSheet: false,
    rIntroUrl: '',
    rIntroBusy: false,
    rResumeBusy: false,
    rStatusBusy: false,
    rInterview: { open: false, date: '', time: '', type: 'online', location_or_link: '', notes: '', busy: false },

    async onRApplicant({ job, app }) {
      this.rApplicantData = null
      this.rApplicantJob = this.rJobById(job)
      this.rApplicantLoading = true
      this.rAboutOpen = false; this.rMoreOpen = false; this.rStatusSheet = false
      this.rIntroUrl = ''
      this.rInterview = { open: false, date: '', time: '', type: 'online', location_or_link: '', notes: '', busy: false }
      try {
        const [res] = await Promise.all([
          this.api(`/recruiter/jobs/${encodeURIComponent(job)}/applicants/${encodeURIComponent(app)}`),
          this.rJobsLoaded ? Promise.resolve() : this.rLoadMine(),
        ])
        this.rApplicantData = res.data || null
        this.rApplicantJob = this.rJobById(job)
      } catch (e) {
        if (e.status !== 404) throw e
      } finally {
        this.rApplicantLoading = false
      }
    },

    get rAp() { return this.rApplicantData },
    get rApP() { return this.rApplicantData?.profile || {} },
    get rApLive() { return this.rApplicantData?.live_profile || null },
    get rApAbout() { return this.rApP.about || this.rApLive?.about || '' },
    get rApLaterAbout() { return !(this.rApP.about || '').trim() && !!(this.rApLive?.about || '').trim() },
    get rApHasVideo() { return !!this.rApP.intro_video_url },
    get rApLaterVideo() { return !this.rApP.intro_video_url && !!this.rApLive?.intro_video_url },

    rStrengthLabel(p) {
      if (p <= 40) return 'Getting started'
      if (p <= 60) return 'Fair profile'
      if (p <= 80) return 'Good profile'
      if (p <= 95) return 'Strong profile'
      return 'Excellent profile'
    },

    rApPath(suffix = '') {
      const a = this.rApplicantData
      return `/recruiter/jobs/${encodeURIComponent(a.job_id)}/applicants/${encodeURIComponent(a.application_id)}${suffix}`
    },

    /** Every status change notifies the candidate server-side, hence the suffix. */
    async rSetApplicantStatus(status, message) {
      if (!this.rApplicantData || this.rStatusBusy) return
      this.rStatusBusy = true
      try {
        const res = await this.api(this.rApPath('/status'), { method: 'PATCH', body: { status } })
        this.rApplicantData = Object.assign({}, this.rApplicantData, res.data || { status })
        this.rSyncApplicant()
        this.showToast(message + ' — candidate notified')
      } catch (e) { this.showToast(e.message, true) }
      this.rStatusBusy = false
    },

    rShortlist() { return this.rSetApplicantStatus('shortlisted', 'Candidate Shortlisted ✓') },
    rMarkSelected() { return this.rSetApplicantStatus('selected', 'Candidate marked as Selected') },
    rReopen() { return this.rSetApplicantStatus('shortlisted', 'Application reopened') },
    rChangeStatus(s) { this.rStatusSheet = false; return this.rSetApplicantStatus(s, 'Status updated to ' + APP_LABEL[s]) },

    async rConfirmReject() {
      this.rMoreOpen = false
      const ok = await this.ask({ title: 'Reject Candidate?', message: 'Are you sure you want to reject this application?', ok: 'Reject', danger: true })
      if (ok) this.rSetApplicantStatus('rejected', 'Application rejected')
    },

    /** Keeps the cached list row in step, so going back shows the new status. */
    rSyncApplicant() {
      const a = this.rApplicantData
      const i = this.rAppl.list.findIndex((x) => x.application_id === a.application_id)
      if (i !== -1) this.rAppl.list.splice(i, 1, Object.assign({}, this.rAppl.list[i], { status: a.status, interview: a.interview }))
    },

    rOpenInterview() {
      this.rMoreOpen = false
      const i = this.rApplicantData?.interview
      this.rInterview = { open: true, date: '', time: '', type: i?.type === 'inPerson' ? 'inPerson' : 'online', location_or_link: '', notes: '', busy: false }
    },

    get rInterviewMinDate() { return new Date().toISOString().slice(0, 10) },
    get rInterviewMaxDate() { return new Date(Date.now() + 180 * 864e5).toISOString().slice(0, 10) },
    get rInterviewReady() { const v = this.rInterview; return !!(v.date && v.time && v.location_or_link.trim()) },

    /**
     * The time goes as the app's picker writes it (`11:30 AM`). The date goes
     * as `yyyy-MM-dd` (API_REFERENCE §9): the app's `12 Nov 2026` passes the
     * `date` rule but the column rejects it with a 500.
     */
    async rScheduleInterview() {
      const v = this.rInterview
      if (!this.rInterviewReady || v.busy) return
      const [hh, mm] = v.time.split(':').map(Number)
      const body = {
        date: v.date,
        time: `${hh % 12 === 0 ? 12 : hh % 12}:${String(mm).padStart(2, '0')} ${hh < 12 ? 'AM' : 'PM'}`,
        type: v.type,
        location_or_link: v.location_or_link.trim(),
        notes: v.notes.trim() || null,
      }
      v.busy = true
      try {
        const res = await this.api(this.rApPath('/interview'), { method: 'POST', body })
        this.rApplicantData = Object.assign({}, this.rApplicantData, res.data || {})
        this.rSyncApplicant()
        this.rInterview.open = false
        this.showToast('Interview scheduled with ' + this.rApName(this.rApplicantData))
      } catch (e) { this.showToast(e.message, true) }
      v.busy = false
    },

    /** A resume link minted at the tap — the one on the payload expires in minutes. */
    async rResumeUrl() {
      const res = await this.api(this.rApPath('/resume'))
      return res.data || {}
    },

    async rViewResume() {
      if (this.rResumeBusy) return
      // Opened before the await, or the browser treats it as a popup.
      const win = window.open('', '_blank')
      this.rResumeBusy = true
      try {
        const { url } = await this.rResumeUrl()
        if (!url) throw new Error("Couldn't open the resume")
        if (win) win.location.href = url
        else window.location.href = url
      } catch (e) {
        if (win) win.close()
        this.showToast(e.message, true)
      }
      this.rResumeBusy = false
    },

    async rDownloadResume() {
      this.rMoreOpen = false
      if (this.rResumeBusy) return
      this.rResumeBusy = true
      try {
        const { url, name } = await this.rResumeUrl()
        if (!url) throw new Error("Couldn't download the resume")
        const a = document.createElement('a')
        a.href = url
        a.download = name || 'resume.pdf'
        a.rel = 'noopener'
        document.body.appendChild(a)
        a.click()
        a.remove()
      } catch (e) { this.showToast(e.message, true) }
      this.rResumeBusy = false
    },

    async rPlayIntro() {
      if (this.rIntroBusy) return
      this.rIntroBusy = true
      try {
        const res = await this.api(this.rApPath('/intro-video'))
        if (!res.data?.url) throw new Error("Couldn't open the intro video")
        this.rIntroUrl = res.data.url
      } catch (e) { this.showToast(e.message, true) }
      this.rIntroBusy = false
    },

    rCall() {
      this.rMoreOpen = false
      const phone = this.rApP.phone
      if (phone) window.location.href = 'tel:' + phone
    },

    rEmail() {
      this.rMoreOpen = false
      const email = this.rApP.email
      if (!email) return
      const subject = 'Regarding your application for ' + (this.rApplicantJob?.title || 'this role')
      window.location.href = 'mailto:' + email + '?subject=' + encodeURIComponent(subject)
    },

    rMessage() {
      this.rMoreOpen = false
      this.go('/chat/' + encodeURIComponent(this.rApplicantData.application_id))
    },

    // ── Post a Job ────────────────────────────────────────────────────
    rPost: blankRun(),

    get rSteps() { return this.rPost.editingId ? STEP_TITLES.slice(0, 3) : STEP_TITLES },
    get rIsPlanStep() { return !this.rPost.editingId && this.rPost.step === 3 },
    get rNeedsPhone() { const m = this.rPost.f.contact_mode; return m === 'calls' || m === 'calls_whatsapp' },
    get rCities() { return this.citiesOf(this.rPost.f.state) },
    get rExperienceOptions() { return [...this.opt('experience_bands'), ANY_EXPERIENCE] },
    get rContactModes() { return this.options?.contact_modes || {} },
    get rHiringTypes() { return this.options?.hiring_types || {} },
    get rCompanySizes() { const s = this.opt('organisation_sizes'); return s.length ? s : ['Small', 'Mid', 'Large'] },

    async onRPost(params, query) {
      // The company prefill and the contact number need both of these.
      await Promise.all([
        this.rOrgsLoaded ? null : this.rLoadOrgs().catch(() => {}),
        this.rContactLoaded ? null : this.rLoadContact().catch(() => {}),
        this.rLoadMine().catch(() => {}),
      ])

      if (params.id) {
        const job = this.rJobById(params.id)
        if (!job) { this.showToast('This posting is no longer available.', true); this.go('/r/jobs', true); return }
        const toPlan = query.plan === '1'
        if (this.rPost.runFor !== params.id || toPlan) await this.rBeginEdit(job, toPlan)
        return
      }

      // The Post tab: a blank form, unless a new post is part-way through.
      if (!this.rPost.ready || this.rPost.editingId) { this.rFinishRun(); return }
      const sub = this.rPost.submitted
      if (sub) this.rPost.submitted = this.rJobById(sub.id) || sub
    },

    /** Back to a blank form (PostJobController._finishRun / _reset). */
    rFinishRun() {
      FILES.post = { doc: null, logo: null }
      const run = blankRun()
      run.ready = true
      this.rPost = run
      this.rPrefillContact()
      this.rPrefillCompany()
    },

    rPrefillContact() {
      const phone = this.rContact.contact_phone || this.user?.phone || this.me?.phone || ''
      this.rPost.f.contact_phone = tenDigits(phone)
    },

    /** The employer's own company, filled in for a repeat post. */
    rPrefillCompany() {
      const orgs = this.rOrgs
      if (orgs.length) this.rPickOrganisation(orgs[orgs.length - 1])
      else { this.rPost.org = null; this.rPost.co = blankCompany() }
    },

    rPickOrganisation(org) {
      FILES.post = { doc: null, logo: null }
      this.rPost.org = org
      this.rPost.co = companyFrom(org)
    },

    /** Seeds the form from a posting: a saved, unpaid one continues as a draft; anything else is an edit. */
    async rBeginEdit(job, toPlan) {
      this.rFinishRun()
      const run = this.rPost
      run.runFor = job.id
      if (this.rAwaitingPayment(job)) run.draftId = job.id
      else run.editingId = job.id

      const f = run.f
      Object.assign(f, {
        role: job.role || '', title: job.title || '', about: job.about || '', show_description: !!job.about,
        openings: String(job.openings ?? 1), type: job.type || '', shift: job.shift || '',
        charges_fee: job.charges_fee ?? false,
        salary_min: job.salary_min == null ? '' : String(job.salary_min),
        salary_max: job.salary_max == null ? '' : String(job.salary_max),
        state: job.state || '', city: job.city || '', locality: job.locality || '', pincode: job.pincode || '',
        latitude: job.latitude ?? null, longitude: job.longitude ?? null,
        min_education: job.min_education || '',
        experience: job.experience || '',
        skills: (job.skills || []).slice(), qualifications: (job.qualifications || []).slice(),
      })
      if (job.preferred_gender) f.preferred_gender = job.preferred_gender
      if (job.contact_mode) f.contact_mode = job.contact_mode
      if (job.contact_phone) f.contact_phone = job.contact_phone
      if (job.whatsapp_phone && job.whatsapp_phone !== job.contact_phone) {
        f.same_whatsapp = false
        f.whatsapp_phone = job.whatsapp_phone
      }
      if ((job.required_fields || []).length) f.required_fields = job.required_fields.slice()

      // The job's own company — never another one standing in for it.
      const org = this.rOrgs.find((o) => o.id === job.organisation_id) || this.rOrgs.find((o) => o.name === job.organisation)
      if (org) this.rPickOrganisation(org)
      else { run.org = null; run.co = blankCompany(); run.co.name = job.organisation || '' }

      run.step = 0
      if (run.draftId && toPlan) await this.rToPlanStep()
    },

    async rCancelEdit() {
      this.rFinishRun()
      this.go('/r/post', true)
    },

    rPickJobState(value) {
      this.rPost.f.state = value
      if (!this.citiesOf(value).includes(this.rPost.f.city)) this.rPost.f.city = ''
    },

    rSalaryProblem(requireBoth = false) {
      const f = this.rPost.f
      const min = f.salary_min === '' ? null : parseInt(f.salary_min, 10)
      const max = f.salary_max === '' ? null : parseInt(f.salary_max, 10)
      if (requireBoth && (min == null || max == null)) return 'Enter the minimum and maximum monthly salary'
      if (min != null && min < 1) return 'Enter a minimum salary above ₹0'
      if (min != null && max != null && max <= min) return 'Maximum salary must be more than the minimum salary'
      return ''
    },

    rOnSalary() { this.rPost.salaryError = this.rSalaryProblem() },

    /** Digits only, as the app's input formatters allow. */
    rDigits(obj, key, max) { obj[key] = String(obj[key] || '').replace(/\D/g, '').slice(0, max) },

    rPhoneProblem(label, value) {
      const v = String(value || '').trim()
      if (!v) return null
      return /^\d{10}$/.test(v) ? null : `${label} must be 10 digits`
    },

    /** What each step refuses to advance without (PostJobController.validateStep). */
    rValidateStep(index) {
      const f = this.rPost.f
      if (index === 0) {
        if (!f.title.trim()) return 'Select a job title'
        if (!f.role.trim()) return 'Select a job category'
        const n = parseInt(f.openings, 10)
        if (!n || n < 1) return 'Enter the number of openings'
        if (n > MAX_OPENINGS) return `Openings can be at most ${MAX_OPENINGS}`
        const salary = this.rSalaryProblem(true)
        if (salary) return salary
        if (!f.shift) return 'Select the job shift'
        if (!f.type) return 'Select the job type'
        if (!f.state) return 'Select the state'
        if (!f.city) return 'Select the city'
        if (f.charges_fee === null) return 'Say whether you charge candidates a fee'
        return null
      }
      if (index === 1) {
        if (!f.min_education) return 'Select the minimum education'
        if (!f.experience) return 'Select the experience needed'
        if (!f.contact_mode) return 'Choose how candidates reach you'
        if (this.rNeedsPhone) {
          if (!f.contact_phone.trim()) return 'Add the number candidates should call'
          const p = this.rPhoneProblem('Contact number', f.contact_phone)
          if (p) return p
          if (f.contact_mode === 'calls_whatsapp' && !f.same_whatsapp) {
            if (!f.whatsapp_phone.trim()) return 'Add your WhatsApp number'
            const w = this.rPhoneProblem('WhatsApp number', f.whatsapp_phone)
            if (w) return w
          }
        }
        return null
      }
      if (index === 2) return companyProblem(this.rPost.co)
      return null
    },

    async rNextStep() {
      const run = this.rPost
      if (run.busy) return
      const error = this.rValidateStep(run.step)
      if (error) { this.showToast(error, true); return }
      if (run.step === 2) {
        if (run.editingId) await this.rSaveEdit()
        else await this.rSaveDraft()
        return
      }
      if (run.step < this.rSteps.length - 1) { run.step += 1; window.scrollTo({ top: 0 }) }
    },

    rPrevStep() {
      if (this.rPost.submitted || this.rPost.step === 0) return
      this.rPost.step -= 1
      window.scrollTo({ top: 0 })
    },

    /** Backwards only, via the step indicator. */
    rGoToStep(i) {
      if (this.rPost.submitted) return
      if (i >= 0 && i < this.rPost.step) { this.rPost.step = i; window.scrollTo({ top: 0 }) }
    },

    /** "Use current location": coordinates, and the nearest listed city when one is close. */
    rUseLocation() {
      if (!navigator.geolocation) { this.showToast('Could not fetch current location', true); return }
      this.rPost.locating = true
      navigator.geolocation.getCurrentPosition((pos) => {
        const { latitude, longitude } = pos.coords
        const f = this.rPost.f
        f.latitude = Math.round(latitude * 1e6) / 1e6
        f.longitude = Math.round(longitude * 1e6) / 1e6
        // No reverse geocoder on the web — the option list's own city
        // coordinates stand in, and only when one is genuinely nearby.
        let best = null
        for (const [city, c] of Object.entries(this.options?.city_coordinates || {})) {
          const d = Math.hypot(c.lat - latitude, (c.lng - longitude) * Math.cos(latitude * Math.PI / 180)) * 111
          if (!best || d < best.d) best = { city, d }
        }
        const states = this.options?.state_cities || {}
        const state = best && best.d < 50 ? Object.keys(states).find((s) => states[s].includes(best.city)) : null
        if (state) { f.state = state; f.city = best.city } else this.showToast('Pick your city from the list', true)
        this.rPost.locating = false
      }, (err) => {
        this.rPost.locating = false
        this.showToast(err.code === 1 ? 'Location permission denied' : 'Could not fetch current location', true)
      }, { enableHighAccuracy: true, timeout: 15000 })
    },

    // ── the company form (Post a Job ▸ Company details and Companies) ─
    rCo(ctx) { return ctx === 'post' ? this.rPost.co : this.rOrgForm.co },

    /** PDF/JPG/PNG up to 10 MB for the document; JPG/PNG up to 3 MB for the logo — the API's own limits. */
    rPickFile(ctx, kind, ev) {
      const file = ev.target.files && ev.target.files[0]
      ev.target.value = ''
      if (!file) return
      const ext = (file.name.split('.').pop() || '').toLowerCase()
      const co = this.rCo(ctx)
      if (kind === 'doc') {
        if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) { this.showToast('Upload your company document (GST certificate, shop licence or registration) as a PDF, JPG, or PNG.', true); return }
        if (file.size > 10 * 1024 * 1024) { this.showToast('The document must be smaller than 10 MB.', true); return }
        FILES[ctx].doc = file
        co.doc_name = file.name
        co.doc_new = true
      } else {
        if (!['jpg', 'jpeg', 'png'].includes(ext)) { this.showToast('Upload a JPG or PNG image.', true); return }
        if (file.size > 3 * 1024 * 1024) { this.showToast('The logo must be smaller than 3 MB.', true); return }
        FILES[ctx].logo = file
        if (co.logo_preview) URL.revokeObjectURL(co.logo_preview)
        co.logo_preview = URL.createObjectURL(file)
        co.logo_new = true
      }
    },

    /** Takes back a file picked in this form, restoring what the saved company had. */
    rRemoveFile(ctx, kind) {
      const co = this.rCo(ctx)
      const saved = ctx === 'post' ? this.rPost.org : this.rOrgForm.initial
      if (kind === 'doc') {
        FILES[ctx].doc = null
        co.doc_new = false
        co.doc_name = saved?.document_name || ''
      } else {
        FILES[ctx].logo = null
        if (co.logo_preview) URL.revokeObjectURL(co.logo_preview)
        co.logo_preview = ''
        co.logo_new = false
      }
    },

    rHasLogo(co) { return co.logo_new || co.logo },

    /** Uploads the files picked in a form onto a saved company; returns the company with them. */
    async rUploadCompanyFiles(ctx, org) {
      const files = FILES[ctx]
      const out = Object.assign({}, org)
      if (files.doc) {
        const res = await this.upload(`/recruiter/organisations/${org.id}/document`, files.doc)
        out.document_name = res.data?.document_name || files.doc.name
        out.document_url = res.data?.document_url || null
        out.review_state = 'pending'
        out.verified = false
        files.doc = null
      }
      if (files.logo) {
        const res = await this.upload(`/recruiter/organisations/${org.id}/logo`, files.logo)
        out.logo = true
        out.logo_url = res.data?.logo_url || null
        files.logo = null
      }
      return out
    },

    /** Keeps the file fields a PATCH response is silent about (Organisation.withFilesFrom). */
    rWithFilesFrom(org, before) {
      if (!before) return org
      const out = Object.assign({}, org)
      if (!out.document_name && before.document_name) {
        out.document_name = before.document_name
        out.document_url = out.document_url || before.document_url
        if (out.review_state === 'no_document') out.review_state = before.review_state
      }
      if (!out.logo && before.logo) { out.logo = true; out.logo_url = before.logo_url }
      return out
    },

    rStoreOrg(org) {
      const i = this.rOrgs.findIndex((o) => o.id === org.id)
      if (i === -1) this.rOrgs.unshift(org)
      else this.rOrgs.splice(i, 1, Object.assign({}, this.rOrgs[i], org))
    },

    /** Creates the company, or updates the picked one if anything changed (PostJobController._saveCompany). */
    async rSaveCompany() {
      const run = this.rPost
      const existing = run.org
      const body = companyBody(run.co)
      let saved = existing
      const unchanged = existing && Object.keys(body).every((k) => (existing[k] ?? null) === body[k])
      if (!unchanged) {
        const res = existing
          ? await this.api(`/recruiter/organisations/${existing.id}`, { method: 'PATCH', body })
          : await this.api('/recruiter/organisations', { method: 'POST', body })
        saved = this.rWithFilesFrom(res.data, existing)
        // Remembered before the files go up: a failed upload's retry updates
        // this company instead of creating a second one.
        run.org = saved
        run.co.id = saved.id
        this.rStoreOrg(saved)
      }
      if (FILES.post.doc || FILES.post.logo) {
        saved = await this.rUploadCompanyFiles('post', saved)
        run.org = saved
        run.co.doc_new = false
        run.co.doc_name = saved.document_name || ''
        if (saved.logo) { run.co.logo = true; run.co.logo_url = saved.logo_url || '' }
        this.rStoreOrg(saved)
      }
      return saved
    },

    /** The body of both POST and PATCH /recruiter/jobs (JobDraft.toApi). */
    rDraftBody(organisationId, asDraft = false) {
      const f = this.rPost.f
      const orNull = (v) => (String(v ?? '').trim() ? String(v).trim() : null)
      const int = (v) => (String(v).trim() === '' ? null : parseInt(v, 10))
      const whatsapp = f.contact_mode === 'calls_whatsapp' ? (f.same_whatsapp ? f.contact_phone : f.whatsapp_phone) : ''
      const body = {
        role: f.role.trim(), title: f.title.trim(), organisation_id: organisationId,
        city: f.city, type: f.type, shift: f.shift,
        state: orNull(f.state), locality: orNull(f.locality), pincode: orNull(f.pincode),
        latitude: f.latitude, longitude: f.longitude,
        salary_min: int(f.salary_min), salary_max: int(f.salary_max), openings: int(f.openings),
        charges_fee: f.charges_fee,
        about: orNull(f.show_description ? f.about : ''),
        experience: orNull(f.experience), min_education: orNull(f.min_education),
        preferred_gender: orNull(f.preferred_gender),
        qualifications: f.qualifications.slice(), skills: f.skills.slice(),
        contact_mode: orNull(f.contact_mode),
        contact_phone: orNull(this.rNeedsPhone ? f.contact_phone : ''),
        whatsapp_phone: orNull(whatsapp),
        required_fields: f.required_fields.slice(),
      }
      for (const k of Object.keys(body)) {
        if (body[k] == null && !CLEARABLE.includes(k)) delete body[k]
      }
      return asDraft ? Object.assign({ save_as_draft: true }, body) : body
    },

    /** Saves the company and the post, then moves on to the plan step. */
    async rSaveDraft() {
      const run = this.rPost
      run.busy = true
      let job = null
      try {
        const org = await this.rSaveCompany()
        const res = run.draftId
          ? await this.api(`/recruiter/jobs/${run.draftId}`, { method: 'PATCH', body: this.rDraftBody(org.id) })
          : await this.api('/recruiter/jobs', { method: 'POST', body: this.rDraftBody(org.id, true) })
        job = res.data
      } catch (e) {
        this.showToast(e.message, true)
      }
      run.busy = false
      if (!job) return
      run.draftId = job.id
      run.runFor = job.id
      this.rReplaceJob(job)
      await this.rToPlanStep()
    },

    /**
     * Moves on to paying — unless postings from an earlier pack are left, in
     * which case the post goes straight to review on one (PostJobController._toPlanStep).
     */
    async rToPlanStep() {
      const run = this.rPost
      run.busy = true
      try {
        await this.rLoadPlans()
        const credit = (run.plans?.credits || []).find((c) => c.remaining > 0)
        if (credit) {
          await this.rUseCredit(credit)
          if (run.submitted) {
            const left = credit.remaining - 1
            this.showToast(left > 0 ? `Posted on your existing plan — ${left} posting${left === 1 ? '' : 's'} left` : 'Posted on your existing plan')
            return
          }
        }
        run.step = 3
        window.scrollTo({ top: 0 })
      } finally {
        run.busy = false
      }
    },

    async rSaveEdit() {
      const run = this.rPost
      run.busy = true
      let job = null
      try {
        const org = await this.rSaveCompany()
        const res = await this.api(`/recruiter/jobs/${run.editingId}`, { method: 'PATCH', body: this.rDraftBody(org.id) })
        job = res.data
      } catch (e) {
        this.showToast(e.message, true)
      }
      run.busy = false
      if (!job) return
      this.rReplaceJob(job)
      this.showToast(job.posting_status === 'pending_approval'
        ? 'Saved — your changes need approval, so the post is paused for candidates until it is reviewed'
        : 'Changes saved')
      this.rFinishRun()
      this.go('/r/jobs', true)
    },

    // ── step 4 · choose plan ──────────────────────────────────────────
    async rLoadPlans() {
      const run = this.rPost
      run.plansLoading = true
      try {
        const res = await this.api('/recruiter/job-plans')
        run.plans = res.data || null
        const tiers = run.plans?.tiers || []
        if (!run.tier && tiers.length) run.tier = (tiers.find((t) => t.popular) || tiers[0]).id
      } catch (e) {
        this.showToast(e.message || "Couldn't load the plans. Check your connection and try again.", true)
      }
      run.plansLoading = false
    },

    get rLargestBucket() { const q = this.rPost.plans?.quantities || [1]; return q[q.length - 1] || 1 },

    rUnitPrice(tier) {
      const q = this.rPost.quantity
      const bucket = q > this.rLargestBucket ? this.rLargestBucket : q
      return this.rPost.plans?.prices?.[tier]?.[REACH]?.[String(bucket)] || null
    },

    get rTotalPaise() {
      const unit = this.rUnitPrice(this.rPost.tier)
      return unit ? unit.price_paise * this.rPost.quantity : null
    },

    get rPayLabel() {
      const t = this.rTotalPaise
      return t == null ? 'Proceed to pay' : 'Proceed to pay ' + this.rupees(t)
    },

    rQuantityOn(q) {
      const last = this.rLargestBucket
      return q === last ? this.rPost.quantity >= last : this.rPost.quantity === q
    },

    rChangeQuantity(delta) {
      const max = this.rPost.plans?.max_quantity || 20
      this.rPost.quantity = Math.min(max, Math.max(this.rLargestBucket, this.rPost.quantity + delta))
    },

    async rPay() {
      const run = this.rPost
      const total = this.rTotalPaise
      if (!run.draftId || total == null || run.paying) return
      const method = await this.choosePaymentMethod(this.rupees(total))
      if (!method) return
      run.paying = true
      try {
        const res = await this.api(`/recruiter/jobs/${run.draftId}/orders`, {
          method: 'POST',
          body: { tier: run.tier, reach: REACH, quantity: run.quantity, method },
        })
        const order = res.data?.order
        if (!order?.id) throw new Error("Couldn't reach the payment service. Please try again.")
        await this.confirmOrder(order.id)
        const id = run.draftId
        await this.rLoadMine()
        run.submitted = this.rJobById(id) || { id }
        run.draftId = null
        window.scrollTo({ top: 0 })
      } catch (e) {
        this.showToast(e.message || "That payment didn't go through. Please try again.", true)
      }
      run.paying = false
    },

    /** Posts on a posting left over from an earlier pack — no payment. */
    async rUseCredit(credit) {
      const run = this.rPost
      if (!run.draftId || run.paying) return
      run.paying = true
      try {
        const res = await this.api(`/recruiter/jobs/${run.draftId}/use-credit`, { method: 'POST', body: { credit_id: credit.id } })
        run.submitted = res.data
        this.rReplaceJob(res.data)
        run.draftId = null
        window.scrollTo({ top: 0 })
      } catch (e) { this.showToast(e.message, true) }
      run.paying = false
    },

    get rSubmittedLive() { return this.rPost.submitted?.hiring_stage === 'live' },
    get rSubmittedNeedsDoc() { return !!this.rPost.submitted && this.rDocMissing(this.rPost.submitted) },

    rGoToMyJobs() { this.rFinishRun(); this.go('/r/jobs') },
    rPostAnother() { this.rFinishRun(); window.scrollTo({ top: 0 }) },

    // ── Companies ─────────────────────────────────────────────────────
    rOrgForm: { open: false, initial: null, co: blankCompany(), busy: false },

    async onROrgs() { await this.rLoadOrgs() },

    rOrgStateLabel(org) {
      return { verified: 'Verified', pending: 'Under review' }[org.review_state] || 'Document required'
    },

    /** The one-line verification notice under each company (VerificationNotice, compact). */
    rOrgNotice(org) {
      const hidden = org.hidden_postings || 0
      const postings = hidden === 1 ? '1 posting' : `${hidden} postings`
      if (org.review_state === 'verified') return { text: 'Verified company', icon: 'badgeCheck', cls: 'text-success' }
      if (org.review_state === 'pending') {
        return { text: hidden > 0 ? `Your ${postings} ${hidden === 1 ? 'is' : 'are'} not being shown yet` : 'Verification in progress', icon: 'clock', cls: 'text-warning' }
      }
      return { text: hidden > 0 ? `Your ${postings} ${hidden === 1 ? 'is' : 'are'} not being shown` : 'Upload your company document', icon: 'alert', cls: 'text-primary-dark' }
    },

    rOpenOrgForm(org = null) {
      FILES.org = { doc: null, logo: null }
      this.rOrgForm = { open: true, initial: org, co: companyFrom(org), busy: false }
    },

    rCloseOrgForm() {
      FILES.org = { doc: null, logo: null }
      this.rOrgForm.open = false
    },

    async rSaveOrgForm() {
      const form = this.rOrgForm
      if (form.busy) return
      const problem = companyProblem(form.co)
      if (problem) { this.showToast(problem, true); return }
      form.busy = true
      const isNew = !form.initial
      try {
        const body = companyBody(form.co)
        const res = isNew
          ? await this.api('/recruiter/organisations', { method: 'POST', body })
          : await this.api(`/recruiter/organisations/${form.initial.id}`, { method: 'PATCH', body })
        let saved = this.rWithFilesFrom(res.data, form.initial)
        this.rStoreOrg(saved)
        saved = await this.rUploadCompanyFiles('org', saved)
        this.rStoreOrg(saved)
        form.open = false
        this.showToast(isNew ? 'Company added' : 'Company updated')
        // The list's counts and review states come from the server.
        this.rLoadOrgs().catch(() => {})
      } catch (e) {
        this.showToast(e.message, true)
      }
      form.busy = false
    },

    async rRemoveOrg(org) {
      const ok = await this.ask({
        title: 'Remove company?',
        message: "Jobs already posted for this company stay live — you just won't be able to pick it for new posts.",
        ok: 'Remove', danger: true,
      })
      if (!ok) return
      try {
        await this.api(`/recruiter/organisations/${org.id}`, { method: 'DELETE' })
        this.rOrgs = this.rOrgs.filter((o) => o.id !== org.id)
        this.showToast('Company removed')
      } catch (e) { this.showToast(e.message, true) }
    },

    /** A fresh signed link each time — the one in the list may be old. */
    async rViewDocument(org) {
      const win = window.open('', '_blank')
      try {
        const list = await this.rLoadOrgs()
        const url = (list.find((o) => o.id === org.id) || org).document_url
        if (!url) throw new Error('No document uploaded yet')
        if (win) win.location.href = url
        else window.location.href = url
      } catch (e) {
        if (win) win.close()
        this.showToast(e.message, true)
      }
    },

    // ── Contact details ───────────────────────────────────────────────
    rContactForm: { contact_person_name: '', contact_email: '', contact_phone: '' },
    rContactSaving: false,

    async onRContact() {
      await this.rLoadContact()
      this.rContactForm = this.clone(this.rContact)
    },

    async rSaveContact() {
      if (this.rContactSaving) return
      this.rContactSaving = true
      try {
        const f = this.rContactForm
        const res = await this.api('/recruiter/profile', {
          method: 'PATCH',
          body: { contact_person_name: f.contact_person_name.trim(), contact_email: f.contact_email.trim(), contact_phone: f.contact_phone.trim() },
        })
        this.rContact = Object.assign({}, this.rContact, res.data || {})
        this.showToast('Contact details saved')
        this.back('/r/profile')
      } catch (e) { this.showToast(e.message, true) }
      this.rContactSaving = false
    },

    // ── Profile tab ───────────────────────────────────────────────────
    async onRProfile() {
      await Promise.all([this.rLoadOrgs(), this.rLoadContact()])
      try {
        const res = await this.api('/subscription')
        const active = res.data?.active?.recruiter?.plan_id
        const plan = (res.data?.plans?.recruiter || []).find((p) => p.id === active)
        this.rPlanName = plan?.name || ''
      } catch (e) { this.rPlanName = '' }
    },

    get rPrimaryOrg() { return this.rOrgs[0] || null },
    get rHasContact() { const p = this.rContact; return !!(p.contact_person_name || p.contact_email || p.contact_phone) },

    get rProfileName() { return this.rPrimaryOrg?.name || 'Your companies' },

    get rProfileSubLine() {
      const orgs = this.rOrgs
      if (!orgs.length) return 'No company added yet'
      const p = orgs[0]
      if (orgs.length === 1) return `${p.industry || 'Industry not set'} · ${p.size ? p.size + ' company' : 'Size not set'}`
      return `${orgs.length} companies · ${p.industry || p.name}`
    },

    /** Company profile strength (ProfileController._recruiterCompletion). */
    get rCompletion() {
      const org = this.rPrimaryOrg
      let total = 0
      if (org) total += 30
      if (org?.logo) total += 15
      if (org?.about) total += 20
      if (this.rHasContact) total += 15
      if (org?.website) total += 10
      if (org?.gst_number) total += 10
      return total
    },

    get rVisibilityLabel() {
      const p = this.rCompletion
      if (p <= 40) return 'Candidate visibility: low'
      if (p <= 60) return 'Candidate visibility: fair'
      if (p <= 80) return 'Candidate visibility: good'
      return 'Candidate visibility: high'
    },

    get rBoosts() {
      const org = this.rPrimaryOrg
      if (!org) return [{ label: 'Add your company', gain: '+30%' }]
      return [
        !org.logo && { label: 'Add a company logo', gain: '+15%' },
        !org.about && { label: 'Add an about-company summary', gain: '+20%' },
        !org.website && { label: 'Add your website', gain: '+10%' },
        !org.gst_number && { label: 'Add GST / registration number', gain: '+10%' },
      ].filter(Boolean).slice(0, 3)
    },

    R_SUPPORT_LINKS: [
      { label: 'About Us', slug: 'about' },
      { label: 'Help & Support', slug: 'help' },
      { label: 'Contact Us', slug: 'contact' },
      { label: 'Terms & Conditions', slug: 'terms' },
      { label: 'Privacy Policy', slug: 'privacy' },
    ],
  })
})()
