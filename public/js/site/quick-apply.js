/**
 * Quick Apply — the website's replacement for the app's gated onboarding —
 * plus the job seeker state every job list shares (saved ids, applications).
 *
 *   Inthes.quickApply(jobOrId)   — the app's `applyToJob`, in the same order:
 *     signed out → sign-in popup, then carry on; own posting → its applicant
 *     list; already applied → toast; GET /applications/requirements → either a
 *     one-screen confirm, or ONE form with only the missing fields (saved to
 *     the profile, resume rendered if needed), then POST /applications.
 *     The server re-checks every rule; this is routing, never the rule.
 *
 *   Alpine.store('jobs')         — savedIds / apps, loaded on demand with
 *                                  `ensure()`; isSaved / hasApplied /
 *                                  appFor / toggleSave.
 *   Inthes.jobsKit               — pure helpers the job pages share (match
 *                                  tone, status labels, salary floors…).
 *
 * Fires `inthes:applied` ({ job_id, application }) after a submit so a page
 * can update its own buttons.
 */
;(function () {
  const trim = (v) => String(v ?? '').trim()

  /** The `ProfileField` gate, as `isSatisfiedBy` reads the profile. */
  const SATISFIED = {
    name: (p) => !!trim(p.name),
    qualification: (p) => !!p.qualification || (p.educations || []).length > 0,
    experience: (p) => !!p.experience,
    location: (p) => Array.isArray(p.location) ? p.location.length > 0 : !!p.location,
    resume: (p) => !!p.resume,
    gender: (p) => !!p.gender,
    dob: (p) => !!p.dob,
    address: (p) => !!trim(p.address),
  }
  /** Asked by every job regardless of its own list (the server's rule too). */
  const ALWAYS_REQUIRED = ['name', 'gender', 'dob', 'address']
  /** Form order — personal first, then what the posting filters on. */
  const FORM_ORDER = ['name', 'gender', 'dob', 'address', 'qualification', 'experience', 'location']

  const FIELD_LABEL = {
    name: 'Full name', gender: 'Gender', dob: 'Date of birth', address: 'Address',
    qualification: 'Highest qualification', experience: 'Total experience', location: 'Preferred cities', resume: 'Resume',
  }

  /** Application status, the product's own words — never "rejected" to a candidate. */
  const APP_STATUS = {
    applied: { label: 'Applied', note: 'Waiting for the employer to review', tone: 'bg-info-bg text-info' },
    shortlisted: { label: 'Shortlisted', note: 'The employer is interested in you', tone: 'bg-primary-light text-primary-dark' },
    selected: { label: 'Selected', note: 'You got the job', tone: 'bg-success-bg text-success' },
    rejected: { label: 'Not selected', note: 'The employer went with someone else', tone: 'bg-surface-muted text-ink-secondary' },
  }
  const APP_TABS = [
    { key: '', label: 'All' },
    { key: 'applied', label: 'Applied' },
    { key: 'shortlisted', label: 'Shortlisted' },
    { key: 'selected', label: 'Selected' },
    { key: 'rejected', label: 'Not selected' },
  ]

  /** "₹20K+" → 20000, "₹1L+" → 100000. */
  function salaryFloor(label) {
    const m = String(label).replace(/[₹,+\s]/g, '').match(/^([\d.]+)([KL]?)$/i)
    if (!m) return null
    const n = Number(m[1])
    return Math.round(m[2].toUpperCase() === 'L' ? n * 100000 : m[2] ? n * 1000 : n)
  }

  function matchTone(p) {
    if (p >= 75) return { label: 'Great match', cls: 'bg-success-bg text-success' }
    if (p >= 50) return { label: 'Good match', cls: 'bg-warning-bg text-warning' }
    return { label: 'Partial match', cls: 'bg-surface-muted text-ink-secondary' }
  }

  /** CandidateProfile.missingForResume — the three a resume is rendered from. */
  const RESUME_INPUTS = ['name', 'qualification', 'experience']

  const isoYearsAgo = (n) => { const d = new Date(); d.setFullYear(d.getFullYear() - n); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10) }
  function ageFrom(iso) {
    const m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (!m) return null
    const now = new Date()
    let y = now.getFullYear() - Number(m[1])
    if (now.getMonth() + 1 < Number(m[2]) || (now.getMonth() + 1 === Number(m[2]) && now.getDate() < Number(m[3]))) y--
    return y < 0 ? null : y
  }

  const kit = {
    APP_TABS,
    FIELD_LABEL,
    salaryFloor,
    matchTone,
    appStatus: (s) => APP_STATUS[s] || { label: s || '', note: '', tone: 'bg-surface-muted text-ink-secondary' },
    fieldLabel: (f) => FIELD_LABEL[f] || f,
    jobUrl: (job) => '/jobs/' + encodeURIComponent(job?.code || job?.id || ''),
    salary: (job) => job?.salary_display || job?.salary || '',
    experience: (job) => job?.experience_display || job?.experience || '',
    isNew: (job) => !!job?.posted_at && (Date.now() - new Date(job.posted_at).getTime()) <= 14 * 86400000,
    share(job) { Inthes.share(job.title + ' at ' + job.organisation, location.origin + kit.jobUrl(job)) },
    canCall: (job) => !!job?.contact_phone && job.contact_mode !== 'apply_only',
    canWhatsapp: (job) => !!(job?.whatsapp_phone || job?.contact_phone) && job.contact_mode === 'calls_whatsapp',
    whatsappLink(job) {
      const phone = (job.whatsapp_phone || job.contact_phone || '').replace(/\D/g, '')
      return `https://wa.me/91${phone}?text=${encodeURIComponent(`Hi, I saw your "${job.title}" job on INTHES and I am interested.`)}`
    },
    /** What this job needs that the saved profile does not have yet. */
    missingFor(job, me) {
      const p = me || {}
      const fields = [...new Set([...ALWAYS_REQUIRED, ...(job?.required_fields || [])])]
      return fields.filter((f) => SATISFIED[f] && !SATISFIED[f](p))
    },
    /** The line under an Apply button — what clicking it will involve. */
    readiness(job) {
      const auth = Alpine.store('auth')
      const js = Alpine.store('jobs')
      if (job?.own_posting) return 'This is your own posting'
      if (!auth.signedIn) return 'Sign in takes one OTP'
      const app = js.appFor(job)
      if (app) return 'You applied on ' + Inthes.fmtDate(app.applied_at)
      if (job?.has_applied) return 'You have applied to this job'
      const n = kit.missingFor(job, auth.me).length
      return n ? `This job needs ${n} more detail${n > 1 ? 's' : ''}` : 'Everything this job needs is already in your profile'
    },
    trackSteps(app) {
      if (!app) return []
      const at = Object.fromEntries((app.timeline || []).map((t) => [t.stage, t.at]))
      const order = ['applied', 'shortlisted', 'selected']
      const reached = app.status === 'rejected'
        ? Math.max(0, ...order.map((s, i) => (at[s] ? i : -1)))
        : order.indexOf(app.status)
      return order.map((stage, i) => ({
        stage, label: APP_STATUS[stage].label, at: at[stage] || null,
        done: i <= reached, current: i === reached && app.status !== 'rejected',
      }))
    },
    rejectedAt: (app) => (app?.timeline || []).find((t) => t.stage === 'rejected')?.at || app?.stage_updated_at,
    interviewTypeLabel: (t) => t === 'online' ? 'Online' : t === 'inPerson' ? 'In person' : (t || ''),
  }

  const $auth = () => Alpine.store('auth')
  const $jobs = () => Alpine.store('jobs')
  const $qa = () => Alpine.store('qa')

  function fieldsFor(req, me) {
    const missing = (req?.missing_fields || []).filter((f) => f in SATISFIED)
    const fields = missing.filter((f) => f !== 'resume')
    const needsResume = missing.includes('resume')
    // The resume is rendered from these three, so a missing resume asks for
    // whichever of them the profile does not have yet.
    if (needsResume) RESUME_INPUTS.forEach((f) => { if (!SATISFIED[f](me || {}) && !fields.includes(f)) fields.push(f) })
    return { fields: FORM_ORDER.filter((f) => fields.includes(f)), needsResume }
  }

  async function quickApply(jobOrId) {
    const qa = $qa()
    if (qa.open && qa.busy) return
    let job = jobOrId && typeof jobOrId === 'object' ? jobOrId : null
    const id = job ? job.id : String(jobOrId || '')
    if (!id) return
    if ($auth().signedIn && job?.own_posting) { location.href = '/employer/jobs/' + encodeURIComponent(job.id); return }
    if (!$auth().signedIn) {
      if (!(await Inthes.signIn('to apply for this job'))) return
    }
    try { await $jobs().ensure() } catch (e) { /* requirements re-checks */ }
    if (job && $jobs().appFor(job)) { Inthes.toast('You have already applied to this job'); return }

    Object.assign(qa, { open: true, stage: 'loading', job, req: null, fields: [], needsResume: false, errors: {}, error: '', app: null, busy: false })
    try {
      // The card may be a signed-out copy; the server's copy knows ownership.
      if (!job || !job.title) job = (await Inthes.api('/jobs/' + encodeURIComponent(id))).data
      qa.job = job
      await qa.check()
    } catch (e) {
      qa.stage = 'error'
      qa.error = e.status === 404 ? 'This job is no longer available.' : e.message
    }
  }

  document.addEventListener('alpine:init', () => {
    Alpine.store('jobs', {
      savedIds: [],
      saved: [],
      apps: [],
      loaded: false,
      _loading: null,

      /** Loads saved jobs + applications once per page (force to refresh). */
      ensure(force = false) {
        if (!$auth().signedIn) return Promise.resolve()
        if (this._loading && !force) return this._loading
        this._loading = Promise.all([
          Inthes.api('/candidate/saved-jobs').then((r) => { this.saved = r.data || []; this.savedIds = this.saved.map((j) => j.id) }),
          Inthes.api('/applications').then((r) => { this.apps = r.data || [] }),
        ]).then(() => { this.loaded = true }).catch((e) => { this._loading = null; throw e })
        return this._loading
      },
      reset() { Object.assign(this, { savedIds: [], saved: [], apps: [], loaded: false, _loading: null }) },

      isSaved(job) { return !!job && (this.savedIds.includes(job.id) || (!this.loaded && !!job.is_saved)) },
      appFor(job) { return job ? this.apps.find((a) => (a.job_id || a.job?.id) === job.id) : null },
      hasApplied(job) { return !!this.appFor(job) || (!this.loaded && !!job?.has_applied) },

      async toggleSave(job) {
        if (!$auth().signedIn) {
          if (!(await Inthes.signIn('to save this job'))) return
          try { await this.ensure() } catch (e) { /* carry on */ }
          if (this.isSaved(job)) { Inthes.toast('Job saved'); return }
        }
        const was = this.isSaved(job)
        // Optimistic, like the app's heart.
        this.savedIds = was ? this.savedIds.filter((x) => x !== job.id) : [...this.savedIds, job.id]
        if (was) this.saved = this.saved.filter((j) => j.id !== job.id)
        else if (!this.saved.some((j) => j.id === job.id)) this.saved = [job, ...this.saved]
        try {
          if (was) await Inthes.api('/candidate/saved-jobs/' + encodeURIComponent(job.id), { method: 'DELETE' })
          else await Inthes.api('/candidate/saved-jobs', { method: 'POST', body: { job_id: job.id } })
          Inthes.toast(was ? 'Removed from saved jobs' : 'Job saved')
        } catch (e) {
          this.savedIds = was ? [...this.savedIds, job.id] : this.savedIds.filter((x) => x !== job.id)
          if (was) this.saved = [job, ...this.saved]
          else this.saved = this.saved.filter((j) => j.id !== job.id)
          Inthes.toast(e.message, true)
        }
      },
    })

    Alpine.store('qa', {
      open: false,
      stage: 'loading', // loading | confirm | form | sending | done | error
      job: null,
      req: null,
      fields: [],
      needsResume: false,
      form: { name: '', gender: '', dob: '', address: '', qualification: '', experience: '', location: [] },
      errors: {},
      error: '',
      busy: false,
      app: null,

      get dobMax() { return isoYearsAgo(16) },
      get dobMin() { return isoYearsAgo(80) },
      label: (f) => FIELD_LABEL[f] || f,
      has(f) { return this.fields.includes(f) },

      close() {
        if (this.busy) return
        this.open = false
      },

      /** Asks the server what this job still needs, then picks the screen. */
      async check() {
        const req = (await Inthes.api('/applications/requirements/' + encodeURIComponent(this.job.id))).data
        if (req.own_posting) { this.open = false; location.href = '/employer/jobs/' + encodeURIComponent(req.job_id); return }
        if (req.already_applied) {
          this.open = false
          Inthes.toast('You have already applied to this job')
          $jobs().ensure(true).catch(() => {})
          return
        }
        this.req = req
        const me = $auth().me || (await Inthes.loadMe()) || {}
        const { fields, needsResume } = fieldsFor(req, me)
        this.fields = fields
        this.needsResume = needsResume
        if (!fields.length && !needsResume) { this.stage = 'confirm'; return }
        this.form = {
          name: me.name || '', gender: me.gender || '', dob: me.dob || '', address: me.address || '',
          qualification: me.qualification || '', experience: me.experience || '', location: [...(me.location || [])],
        }
        this.errors = {}
        this.stage = 'form'
      },

      _validate() {
        const f = this.form
        const e = {}
        if (this.has('name') && !trim(f.name)) e.name = 'Enter your full name'
        if (this.has('gender') && !f.gender) e.gender = 'Select your gender'
        if (this.has('dob')) {
          if (!f.dob) e.dob = 'Select your date of birth'
          else if ((ageFrom(f.dob) ?? 0) < 16) e.dob = 'You must be at least 16 years old'
        }
        if (this.has('address') && !trim(f.address)) e.address = 'Enter your address'
        if (this.has('qualification') && !f.qualification) e.qualification = 'Select your highest qualification'
        if (this.has('experience') && !f.experience) e.experience = 'Select your total experience'
        if (this.has('location') && !f.location.length) e.location = 'Pick one or more cities.'
        this.errors = e
        return !Object.keys(e).length
      },

      /** Saves the missing fields, renders the resume if needed, then applies. */
      async saveAndApply() {
        if (this.busy) return
        if (!this._validate()) {
          // Bring the first message into view — on a phone it may be below the fold.
          setTimeout(() => [...document.querySelectorAll('#qa-form .wa-error')].find((el) => el.offsetParent)?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 30)
          return
        }
        this.busy = true
        this.error = ''
        try {
          const f = this.form
          const body = {}
          for (const k of this.fields) body[k] = k === 'location' ? f.location : trim(f[k])
          if (Object.keys(body).length) await Inthes.api('/candidate/profile', { method: 'PATCH', body })
          if (this.needsResume) await Inthes.api('/candidate/profile/resume/generate', { method: 'POST' })
          await Inthes.loadMe()
          this.busy = false
          // Re-check rather than assume — the job's list may have changed.
          await this.check()
          if (this.stage === 'confirm') await this.submit()
          else if (this.stage === 'form') this.error = 'This job still needs a few details.'
        } catch (e) {
          this.busy = false
          const errs = {}
          for (const [k, v] of Object.entries(e.errors || {})) errs[k] = Array.isArray(v) ? v[0] : v
          this.errors = errs
          this.error = Object.keys(errs).some((k) => this.fields.includes(k)) ? '' : e.message
        }
      },

      async submit() {
        if (this.busy) return
        this.busy = true
        this.error = ''
        this.stage = 'sending'
        try {
          const res = await Inthes.api('/applications', { method: 'POST', body: { job_id: this.job.id } })
          this.app = res.data
          const js = $jobs()
          js.apps = [res.data, ...js.apps.filter((a) => a.id !== res.data.id)]
          this.stage = 'done'
          window.dispatchEvent(new CustomEvent('inthes:applied', { detail: { job_id: this.job.id, application: res.data } }))
        } catch (e) {
          this.busy = false
          // A 422 here is the server finding the profile short after all.
          if (e.status === 422 && e.errors?.profile) {
            try { await Inthes.loadMe(); await this.check(); this.error = e.message } catch (x) { this.stage = 'error'; this.error = e.message }
            return
          }
          this.stage = this.fields.length ? 'form' : 'confirm'
          this.error = e.message || "Couldn't submit your application. Please try again."
        }
        this.busy = false
      },
    })
  })

  // Saved / applied state follows the session.
  window.addEventListener('inthes:signed-out', () => { try { $jobs().reset(); $qa().open = false } catch (e) { /* not booted */ } })
  window.addEventListener('inthes:signed-in', () => { try { if ($jobs()._loading) $jobs().ensure(true).catch(() => {}) } catch (e) { /* not booted */ } })

  window.Inthes = window.Inthes || {}
  window.Inthes.quickApply = quickApply
  window.Inthes.jobsKit = kit
})()
