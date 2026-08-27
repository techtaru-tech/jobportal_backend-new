/**
 * The employer area — post and manage job postings from the web.
 *
 * Client-rendered against `/api/v1/recruiter/*`, the same shape as the admin
 * panel and for the same reason: it sits behind a sign-in, so nothing here needs
 * indexing, and those endpoints already own every rule that matters — ownership,
 * the plan's active-posting ceiling, and the approval queue every new or edited
 * posting passes through. This is a UI over them, not a second implementation.
 *
 * Sign-in is phone + OTP, exactly as in the app. One account, one identity,
 * whichever surface it is used from — `users.role` is the side somebody signed
 * up on, never a permission, so a candidate account can post a job here without
 * anything special being done to it.
 */
(function () {
  const API = '/api/v1'
  const TOKEN_KEY = 'inthes.employer.token'
  const USER_KEY = 'inthes.employer.user'

  /** Post-a-job is long enough to deserve steps rather than one wall of fields. */
  const STEPS = ['Job details', 'Requirements', 'Employer']

  /**
   * The Smart Apply gate: which profile fields a posting demands before a
   * candidate may apply. Labels rather than raw enum values, because these are
   * read by a recruiter choosing them, not by code.
   */
  const REQUIRED_FIELD_OPTIONS = [
    { value: 'name', label: 'Full name' },
    { value: 'qualification', label: 'Qualification' },
    { value: 'experience', label: 'Experience' },
    { value: 'skills', label: 'Skills' },
    { value: 'location', label: 'Location' },
    { value: 'specialization', label: 'Specialization' },
    { value: 'certification_bls', label: 'BLS certification' },
    { value: 'resume', label: 'Resume' },
  ]

  const STATUS_TONE = {
    pending_approval: 'bg-warning-bg text-warning',
    active: 'bg-success-bg text-success',
    paused: 'bg-warning-bg text-warning',
    draft: 'bg-surface-muted text-ink-secondary',
    closed: 'bg-surface-muted text-ink-secondary',
    expired: 'bg-danger-bg text-danger',
    rejected: 'bg-danger-bg text-danger',
  }

  const STATUS_LABEL = {
    pending_approval: 'Awaiting approval',
    active: 'Live',
    paused: 'Paused',
    draft: 'Draft',
    closed: 'Closed',
    expired: 'Expired',
    rejected: 'Not approved',
  }

  const APPLICATION_TONE = {
    applied: 'bg-info-bg text-info',
    shortlisted: 'bg-primary-light text-primary-dark',
    selected: 'bg-success-bg text-success',
    rejected: 'bg-danger-bg text-danger',
  }

  /** The product's own wording — never "rejected" where a person reads it. */
  const APPLICATION_LABEL = {
    applied: 'Applied',
    shortlisted: 'Shortlisted',
    selected: 'Selected',
    rejected: 'Not selected',
  }

  const fmtNumber = (v) => (v ?? 0).toLocaleString('en-IN')

  function timeAgo(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    const seconds = Math.floor((Date.now() - d.getTime()) / 1000)
    if (seconds < 60) return 'just now'
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days === 1) return 'yesterday'
    if (days < 30) return `${days}d ago`
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
  }

  function initials(name) {
    const parts = (name ?? '').trim().split(/\s+/).filter(Boolean)
    if (parts.length === 0) return '?'
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }

  function employerApp() {
    return {
      ICONS: window.ICONS || {},
      STEPS,
      REQUIRED_FIELD_OPTIONS,

      // ── session ─────────────────────────────────────────────────────
      token: '',
      user: null,
      isRestoring: true,

      /** 'phone' while asking for the number, 'otp' once one has been sent. */
      authStage: 'phone',
      authPhone: '',
      authOtp: '',
      verificationId: '',
      authBusy: false,
      authError: '',
      /** Seconds until "Resend code" becomes available. */
      resendIn: 0,
      _resendTimer: null,

      // ── shell ───────────────────────────────────────────────────────
      view: 'jobs',
      toast: '',
      toastError: false,
      _toastTimer: null,

      // ── data ────────────────────────────────────────────────────────
      options: { categories: [], cities: [], job_types: [], shifts: [], experience_bands: [], qualifications: [], skills: [], salary_steps: [] },
      organisations: [],

      jobs: { data: [], busy: false, error: false },
      applicants: { job: null, data: [], busy: false, error: false },

      /** The posting wizard. */
      step: 0,
      form: null,
      posting: false,

      /** Add-an-employer sheet, for a recruiter with none yet. */
      orgOpen: false,
      orgBusy: false,
      orgForm: { name: '', city: '', gst_number: '', website: '', about: '' },

      // ── lifecycle ───────────────────────────────────────────────────
      init() {
        this.form = this.blankForm()
        this.token = localStorage.getItem(TOKEN_KEY) || ''

        const cached = localStorage.getItem(USER_KEY)
        if (cached) {
          try { this.user = JSON.parse(cached) } catch (e) { /* ignore */ }
        }

        // Reference data is public and every screen needs it, so it is fetched
        // regardless of whether anybody is signed in yet.
        this.loadOptions()

        if (!this.token) {
          this.user = null
          this.isRestoring = false
          return
        }

        // A cached user is not proof of a live token — confirm before trusting
        // it, or the first real request fails after the UI has already drawn.
        this.api('/recruiter/organisations')
          .then((res) => {
            this.organisations = res.data || []
            this.loadJobs()
          })
          .catch(() => this.signOut())
          .finally(() => { this.isRestoring = false })
      },

      // ── fetch ───────────────────────────────────────────────────────
      async api(path, opts = {}) {
        const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {})
        let body = opts.body
        if (body !== undefined && !(body instanceof FormData)) {
          headers['Content-Type'] = 'application/json'
          body = JSON.stringify(body)
        }
        if (this.token) headers.Authorization = 'Bearer ' + this.token

        const res = await fetch(API + path, { ...opts, headers, body })

        let json = null
        try { json = await res.json() } catch (e) { /* empty body */ }

        if (res.status === 401 && this.token) {
          this.signOut()
          throw new Error('Your session expired — please sign in again.')
        }

        if (!res.ok) {
          throw new Error(
            (json && json.message) ||
            (json && json.errors ? Object.values(json.errors).flat().join(' ') : null) ||
            'Something went wrong.',
          )
        }
        return json
      },

      showToast(message, isError = false) {
        this.toast = message
        this.toastError = isError
        clearTimeout(this._toastTimer)
        this._toastTimer = setTimeout(() => { this.toast = '' }, 4000)
      },

      loadOptions() {
        this.api('/config/options')
          .then((res) => { this.options = Object.assign(this.options, res.data || {}) })
          .catch(() => { /* the pickers fall back to empty lists */ })
      },

      // ── sign in ─────────────────────────────────────────────────────
      get phoneValid() { return /^[0-9]{10,15}$/.test(this.authPhone.trim()) },

      async sendOtp() {
        if (!this.phoneValid || this.authBusy) return
        this.authBusy = true
        this.authError = ''
        try {
          const res = await this.api('/auth/otp/send', {
            method: 'POST',
            // `recruiter` is the side to open the app on for a brand-new
            // account. It is not a permission and does not restrict anything.
            body: { phone: this.authPhone.trim(), role: 'recruiter' },
          })
          this.verificationId = res.data.verification_id
          this.authStage = 'otp'
          this.authOtp = ''
          this._startResendCountdown()
        } catch (e) {
          this.authError = e.message
        }
        this.authBusy = false
      },

      async verifyOtp() {
        if (this.authOtp.trim().length < 4 || this.authBusy) return
        this.authBusy = true
        this.authError = ''
        try {
          const res = await this.api('/auth/otp/verify', {
            method: 'POST',
            body: {
              phone: this.authPhone.trim(),
              otp: this.authOtp.trim(),
              verification_id: this.verificationId,
              role: 'recruiter',
            },
          })

          this.token = res.data.token
          this.user = res.data.user
          localStorage.setItem(TOKEN_KEY, this.token)
          localStorage.setItem(USER_KEY, JSON.stringify(this.user))

          this.authOtp = ''
          this.verificationId = ''
          this.authStage = 'phone'

          const orgs = await this.api('/recruiter/organisations')
          this.organisations = orgs.data || []
          this.loadJobs()

          // A recruiter with no employer on file cannot post anything, so the
          // gap is put in front of them rather than discovered at step three.
          if (this.organisations.length === 0) {
            this.view = 'post'
            this.orgOpen = true
          }
        } catch (e) {
          this.authError = e.message
        }
        this.authBusy = false
      },

      _startResendCountdown() {
        // The server allows a limited number of sends per phone per window, so
        // the button is held shut rather than letting somebody burn their
        // allowance on impatience and then be locked out.
        this.resendIn = 30
        clearInterval(this._resendTimer)
        this._resendTimer = setInterval(() => {
          this.resendIn -= 1
          if (this.resendIn <= 0) clearInterval(this._resendTimer)
        }, 1000)
      },

      editPhone() {
        this.authStage = 'phone'
        this.authOtp = ''
        this.authError = ''
        this.verificationId = ''
        clearInterval(this._resendTimer)
        this.resendIn = 0
      },

      signOut() {
        if (this.token) this.api('/auth/logout', { method: 'POST' }).catch(() => {})
        this.token = ''
        this.user = null
        this.isRestoring = false
        this.organisations = []
        this.jobs = { data: [], busy: false, error: false }
        this.view = 'jobs'
        localStorage.removeItem(TOKEN_KEY)
        localStorage.removeItem(USER_KEY)
      },

      // ── navigation ──────────────────────────────────────────────────
      go(view) {
        this.view = view
        if (view === 'jobs') this.loadJobs()
      },

      // ── my jobs ─────────────────────────────────────────────────────
      loadJobs() {
        this.jobs.busy = true
        this.jobs.error = false
        this.api('/recruiter/jobs/mine?per_page=50')
          .then((res) => { this.jobs.data = res.data || [] })
          .catch((e) => { this.jobs.error = true; this.showToast(e.message, true) })
          .finally(() => { this.jobs.busy = false })
      },

      statusTone(status) { return STATUS_TONE[status] || 'bg-surface-muted text-ink-secondary' },
      statusLabel(status) { return STATUS_LABEL[status] || status },
      applicationTone(status) { return APPLICATION_TONE[status] || 'bg-surface-muted text-ink-secondary' },
      applicationLabel(status) { return APPLICATION_LABEL[status] || status },

      /**
       * Only the transitions the server will accept.
       *
       * A posting awaiting approval has no live/paused state to toggle, and
       * offering the control anyway turns "waiting on an admin" into an error
       * message the recruiter cannot act on.
       */
      canToggle(job) {
        return job.posting_status === 'active' || job.posting_status === 'paused'
      },

      setStatus(job, status) {
        this.api(`/recruiter/jobs/${job.id}/status`, { method: 'PATCH', body: { status } })
          .then((res) => {
            this.showToast(res.message || 'Updated.')
            this.loadJobs()
          })
          .catch((e) => this.showToast(e.message, true))
      },

      // ── applicants ──────────────────────────────────────────────────
      openApplicants(job) {
        this.applicants = { job, data: [], busy: true, error: false }
        this.view = 'applicants'
        this.api(`/recruiter/jobs/${job.id}/applicants?per_page=50`)
          .then((res) => { this.applicants.data = res.data || [] })
          .catch((e) => { this.applicants.error = true; this.showToast(e.message, true) })
          .finally(() => { this.applicants.busy = false })
      },

      /**
       * Moving an applicant is left to the app.
       *
       * Deliberate: a status change notifies the candidate and writes their
       * Track timeline, and doing it well needs the applicant's full profile in
       * front of you — the resume, the intro video, the snapshot. That screen is
       * in the app, so the web sends the recruiter there rather than offering a
       * thinner version of a decision that cannot be taken back.
       */
      applicantNote() {
        return 'Shortlist, select and schedule interviews from the Inthes app, where the full profile and resume are.'
      },

      // ── post a job ──────────────────────────────────────────────────
      blankForm() {
        return {
          organisation_id: '',
          role: '',
          title: '',
          city: '',
          pincode: '',
          type: '',
          shift: '',
          experience: '',
          salary_min: '',
          salary_max: '',
          about: '',
          organisation_note: '',
          qualifications: [],
          skills: [],
          duties: [],
          benefits: [],
          // The app's own defaults, so a posting made here gates the same way.
          required_fields: ['qualification', 'experience', 'location'],
          dutyDraft: '',
          benefitDraft: '',
        }
      },

      startPost() {
        this.form = this.blankForm()
        if (this.organisations.length === 1) {
          this.form.organisation_id = this.organisations[0].id
        }
        this.step = 0
        this.view = 'post'
      },

      toggleIn(list, value) {
        const i = list.indexOf(value)
        if (i === -1) list.push(value)
        else list.splice(i, 1)
      },

      addTo(list, draftKey) {
        const value = (this.form[draftKey] || '').trim()
        if (!value) return
        if (!list.includes(value)) list.push(value)
        this.form[draftKey] = ''
      },

      removeAt(list, index) { list.splice(index, 1) },

      /**
       * Per-step validation, so an error is raised on the screen the field is
       * on rather than at the end after the recruiter has scrolled past it.
       */
      stepError(index) {
        const f = this.form
        if (index === 0) {
          if (!f.title.trim()) return 'Give the posting a title.'
          if (!f.role) return 'Pick which role this is.'
          if (!f.city.trim()) return 'Add the city.'
          if (!f.type) return 'Pick a job type.'
          if (!f.shift) return 'Pick a shift.'
          if (f.salary_min && f.salary_max && Number(f.salary_max) < Number(f.salary_min)) {
            return 'The maximum salary must be at least the minimum.'
          }
        }
        if (index === 2 && !f.organisation_id) return 'Choose which employer this is for.'
        return null
      },

      nextStep() {
        const error = this.stepError(this.step)
        if (error) { this.showToast(error, true); return }
        if (this.step < STEPS.length - 1) this.step += 1
      },

      previousStep() { if (this.step > 0) this.step -= 1 },

      get isLastStep() { return this.step === STEPS.length - 1 },

      async submitPost() {
        for (let i = 0; i < STEPS.length; i++) {
          const error = this.stepError(i)
          if (error) { this.step = i; this.showToast(error, true); return }
        }

        this.posting = true
        try {
          const f = this.form
          const res = await this.api('/recruiter/jobs', {
            method: 'POST',
            body: {
              organisation_id: f.organisation_id,
              role: f.role,
              title: f.title.trim(),
              city: f.city.trim(),
              pincode: f.pincode.trim() || null,
              type: f.type,
              shift: f.shift,
              experience: f.experience || null,
              // Sent as integers or not at all — the API takes rupees and
              // formats the display range itself.
              salary_min: f.salary_min === '' ? null : Number(f.salary_min),
              salary_max: f.salary_max === '' ? null : Number(f.salary_max),
              about: f.about.trim() || null,
              organisation_note: f.organisation_note.trim() || null,
              qualifications: f.qualifications,
              skills: f.skills,
              duties: f.duties,
              benefits: f.benefits,
              required_fields: f.required_fields,
            },
          })

          // "Submitted", never "live": every posting is created
          // `pending_approval` and reaches candidates only once an admin
          // approves it. Telling a recruiter their job is live when nobody can
          // see it yet is the one thing this must not do.
          this.showToast(res.message || 'Submitted for approval — you will be notified once it is live.')
          this.form = this.blankForm()
          this.step = 0
          this.go('jobs')
        } catch (e) {
          this.showToast(e.message, true)
        }
        this.posting = false
      },

      // ── add an employer ─────────────────────────────────────────────
      async saveOrganisation() {
        if (!this.orgForm.name.trim() || this.orgBusy) return
        this.orgBusy = true
        try {
          const res = await this.api('/recruiter/organisations', {
            method: 'POST',
            body: {
              name: this.orgForm.name.trim(),
              city: this.orgForm.city.trim() || null,
              gst_number: this.orgForm.gst_number.trim() || null,
              website: this.orgForm.website.trim() || null,
              about: this.orgForm.about.trim() || null,
            },
          })

          this.organisations.push(res.data)
          this.form.organisation_id = res.data.id
          this.orgForm = { name: '', city: '', gst_number: '', website: '', about: '' }
          this.orgOpen = false

          // Said now rather than at approval time: an unverified employer's
          // postings do not reach candidates, and that is a document upload
          // away — which only the app can do.
          this.showToast('Employer added. Upload its GST document in the app to get verified.')
        } catch (e) {
          this.showToast(e.message, true)
        }
        this.orgBusy = false
      },

      // ── display helpers ─────────────────────────────────────────────
      fmtNumber, timeAgo, initials,

      salaryLabel(job) {
        return job.salary || job.salary_display || '—'
      },
    }
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('employerApp', employerApp)
  })
})()
