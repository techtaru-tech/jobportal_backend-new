/**
 * What every employer page shares: the app's wording for posting and
 * application states, the notices under a posting, job actions (pause, close,
 * share, edit), and the company form (fields, validation, files, save).
 *
 * Mirrors the Flutter app's lib/app/modules/recruiter/*, post_job and
 * recruiter_profile.
 * Exposed as `window.Emp`; page components spread `Emp.companyForm()` in.
 */
;(function () {
  const STATUS_LABEL = {
    pending_approval: 'Pending approval', active: 'Active', paused: 'Paused', draft: 'Draft',
    closed: 'Closed', expired: 'Expired', rejected: 'Rejected',
  }
  const STATUS_PILL = {
    pending_approval: 'bg-warning-bg text-warning', active: 'bg-success-bg text-success', paused: 'bg-surface-muted text-ink-secondary',
    draft: 'bg-info-bg text-info', closed: 'bg-surface-muted text-ink-muted', expired: 'bg-danger-bg text-danger', rejected: 'bg-danger-bg text-danger',
  }
  const STATUS_DOT = {
    pending_approval: 'bg-warning', active: 'bg-success', paused: 'bg-ink-muted', draft: 'bg-info',
    closed: 'bg-ink-muted', expired: 'bg-danger', rejected: 'bg-danger',
  }
  const APP_STATUSES = ['applied', 'shortlisted', 'selected', 'rejected']
  /** The product's own wording — never "rejected" where a person reads it. */
  const APP_LABEL = { applied: 'Applied', shortlisted: 'Shortlisted', selected: 'Selected', rejected: 'Not selected' }
  const APP_PILL = {
    applied: 'bg-info-bg text-info', shortlisted: 'bg-primary-light text-primary-dark',
    selected: 'bg-success-bg text-success', rejected: 'bg-danger-bg text-danger',
  }
  const APP_DOT = { applied: 'bg-info', shortlisted: 'bg-primary', selected: 'bg-success', rejected: 'bg-danger' }
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const SIZE_HINTS = { Small: 'Up to 50 people', Mid: '51–500 people', Large: '501+ people' }

  /** `13 Oct 2026` — the app's `dd MMM yyyy`. */
  function day(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    return `${String(d.getDate()).padStart(2, '0')} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`
  }

  /** 25000 → "25K", 100000 → "1L" (App\Support\Display::amount). */
  function amount(r) {
    const t = (n) => String(Math.round(n * 10) / 10).replace(/\.0$/, '')
    if (r >= 100000) return t(r / 100000) + 'L'
    if (r >= 1000) return t(r / 1000) + 'K'
    return String(r)
  }
  function salary(min, max) {
    min = min === '' || min == null ? null : Number(min)
    max = max === '' || max == null ? null : Number(max)
    if (min == null && max == null) return ''
    if (min != null && max != null && min !== max) return '₹' + amount(min) + ' – ₹' + amount(max)
    return '₹' + amount(max ?? min)
  }

  const withScheme = (url) => {
    const t = String(url || '').trim()
    if (!t) return ''
    return /^https?:\/\//i.test(t) ? t : 'https://' + t
  }
  const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)
  const isUrl = (v) => /^https?:\/\/[^\s/.]+(\.[^\s/.]+)+(\/\S*)?$/i.test(v)

  // ── postings ──────────────────────────────────────────────────────────
  const awaitingPayment = (job) => job?.hiring_stage === 'payment_pending'
  const docMissing = (job) => job?.hiring_stage === 'paid_document_missing' || job?.hiring_stage === 'posted_document_missing'

  /**
   * The explanation under a posting whose status alone doesn't say what
   * happened or what to do (my_jobs_view.dart `_PostingNotice`), plus the
   * "not visible yet" line for a live post under an unverified employer.
   * → [{ tone, icon, message, action: 'plan'|'doc'|null, label }]
   */
  function notices(job) {
    const out = []
    const s = job.posting_status
    if (awaitingPayment(job)) {
      out.push({ tone: 'warning', icon: 'wallet', message: 'Saved — choose a plan to make it live.', action: 'plan', label: 'Choose plan' })
    } else if (s === 'rejected') {
      out.push({ tone: 'danger', icon: 'xCircle', message: job.rejection_reason ? 'Not approved: ' + job.rejection_reason : 'This job post was not approved.', action: null })
    } else if (s === 'pending_approval') {
      const doc = docMissing(job)
      out.push({
        tone: 'warning', icon: 'clock',
        message: doc ? 'Under review — upload your company document to get it live.'
          : job.hiring_stage === 'document_under_review' ? "Under review — we're checking your company document."
            : "Waiting for approval — you'll be notified once it's live.",
        action: doc ? 'doc' : null, label: 'Upload document',
      })
    }
    if (s === 'active' && !job.organisation_verified) {
      const doc = job.hiring_stage === 'posted_document_missing'
      out.push({
        tone: 'brand', icon: 'eye',
        message: doc ? 'Not visible to candidates yet — upload your company document to get verified.'
          : `Not visible to candidates yet — we're verifying ${job.organisation}.`,
        action: doc ? 'doc' : null, label: 'Upload document',
      })
    }
    return out
  }

  const NOTICE_CLASS = {
    warning: 'border-warning/25 bg-warning-bg text-warning',
    danger: 'border-danger/25 bg-danger-bg text-danger',
    brand: 'border-primary-line bg-primary-light text-primary-dark',
  }

  function noticeHref(job, action) {
    if (action === 'plan') return `/employer/jobs/${encodeURIComponent(job.id)}/checkout`
    if (action === 'doc') return '/employer/companies?doc=' + encodeURIComponent(job.organisation_id || '')
    return null
  }

  async function loadMine() {
    const res = await Inthes.api('/recruiter/jobs/mine?per_page=100')
    return res.data || []
  }
  async function loadOrgs() {
    const res = await Inthes.api('/recruiter/organisations')
    return res.data || []
  }

  /** Pause / resume server-side (MyJobsController.toggleActive). Returns the fresh job, or null. */
  async function toggleJob(job) {
    if (job.posting_status === 'pending_approval') {
      Inthes.toast("That job post is still being reviewed — you'll be notified.", true)
      return null
    }
    const next = job.posting_status === 'active' ? 'paused' : 'active'
    try {
      const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(job.id)}/status`, { method: 'PATCH', body: { status: next } })
      Inthes.toast(next === 'active' ? 'Job resumed' : 'Job paused')
      return res.data
    } catch (e) { Inthes.toast(e.message, true); return null }
  }

  async function closeJob(job) {
    const ok = await Inthes.confirm({
      title: 'Close this job?', message: 'It stops taking new applications. Applicants you already have stay here.',
      ok: 'Close job', danger: true,
    })
    if (!ok) return null
    try {
      const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(job.id)}/status`, { method: 'PATCH', body: { status: 'closed' } })
      Inthes.toast('Job closed to new applications')
      return res.data
    } catch (e) { Inthes.toast(e.message, true); return null }
  }

  function shareJob(job) {
    if (!job.share_url || job.posting_status !== 'active') { Inthes.toast("This job can't be shared yet", true); return }
    Inthes.share(job.title, job.share_url)
  }

  /** Merges a fresh posting into a list, keeping the list-only applicant count. */
  function replaceIn(list, job) {
    if (!job) return
    const i = list.findIndex((j) => j.id === job.id)
    const merged = Object.assign({}, i === -1 ? {} : list[i], job)
    if (merged.applicants_count == null && i !== -1) merged.applicants_count = list[i].applicants_count
    if (i === -1) list.unshift(merged)
    else list.splice(i, 1, merged)
    return merged
  }

  /** What the job-actions menu can offer for a posting. */
  function canToggle(job) { return ['active', 'paused'].includes(job.posting_status) }
  function canClose(job) { return ['active', 'paused', 'pending_approval'].includes(job.posting_status) }
  function canEdit(job) { return !['closed', 'expired'].includes(job.posting_status) }

  /**
   * The job-actions menu's handlers (site/employer/partials/job-menu). The
   * page defines `onJobUpdated(job)` to swap the fresh posting in.
   */
  function jobActions() {
    return {
      jobBusy: '',
      async jobToggle(job) {
        if (this.jobBusy) return
        this.jobBusy = job.id
        const fresh = await toggleJob(job)
        this.jobBusy = ''
        if (fresh) this.onJobUpdated(fresh)
      },
      async jobClose(job) {
        if (this.jobBusy) return
        this.jobBusy = job.id
        const fresh = await closeJob(job)
        this.jobBusy = ''
        if (fresh) this.onJobUpdated(fresh)
      },
      jobShare(job) { shareJob(job) },
    }
  }

  // ── the company form ──────────────────────────────────────────────────
  const blankCompany = () => ({
    id: '', hiring_type: 'company', name: '', email: '', industry: '', size: '', about: '',
    address: '', website: '', gst_number: '', doc_name: '', doc_new: false, logo: false,
    logo_url: '', logo_new: false, logo_preview: '', verified: false, has_doc: false,
  })

  function companyFrom(org) {
    const co = blankCompany()
    if (!org) return co
    Object.assign(co, {
      id: org.id, hiring_type: org.hiring_type || 'company', name: org.name || '', email: org.email || '',
      industry: org.industry || '', size: org.size || '', about: org.about || '', address: org.address || '',
      website: org.website || '', gst_number: org.gst_number || '', doc_name: org.document_name || '',
      has_doc: !!org.document_name, logo: !!org.logo, logo_url: org.logo_url || '', verified: !!org.verified,
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
      gst_number: orNull(String(co.gst_number || '').toUpperCase()),
      about: orNull(co.about),
    }
  }

  /** The company form's required fields (CompanyFormFields.problem), keyed by field. */
  function companyErrors(co) {
    const e = {}
    if (!co.name.trim()) e.name = 'Enter the company name'
    if (!co.email.trim()) e.email = 'Enter the company email'
    else if (!isEmail(co.email.trim())) e.email = 'Enter a valid company email'
    if (!co.size) e.size = 'Select the company size'
    if (co.website.trim() && !isUrl(withScheme(co.website))) e.website = 'Enter a valid website, e.g. www.yourcompany.com'
    if (!co.doc_name) e.doc = 'Upload your company document'
    return e
  }

  /** Keeps the file fields a PATCH response is silent about (Organisation.withFilesFrom). */
  function withFilesFrom(org, before) {
    if (!before) return org
    const out = Object.assign({}, org)
    if (!out.document_name && before.document_name) {
      out.document_name = before.document_name
      out.document_url = out.document_url || before.document_url
      if (out.review_state === 'no_document') out.review_state = before.review_state
    }
    if (!out.logo && before.logo) { out.logo = true; out.logo_url = before.logo_url }
    return out
  }

  function orgStateLabel(org) {
    return { verified: 'Verified', pending: 'Under review' }[org.review_state] || 'Document required'
  }
  function orgStatePill(org) {
    return { verified: 'bg-success-bg text-success', pending: 'bg-warning-bg text-warning' }[org.review_state] || 'bg-primary-light text-primary-dark'
  }

  /** The one-line verification notice under each company (VerificationNotice, compact). */
  function orgNotice(org) {
    const hidden = org.hidden_postings || 0
    const postings = hidden === 1 ? '1 posting' : `${hidden} postings`
    if (org.review_state === 'verified') return { text: 'Verified company', icon: 'badgeCheck', cls: 'text-success' }
    if (org.review_state === 'pending') {
      return { text: hidden > 0 ? `Your ${postings} ${hidden === 1 ? 'is' : 'are'} not being shown yet` : 'Verification in progress', icon: 'clock', cls: 'text-warning' }
    }
    return { text: hidden > 0 ? `Your ${postings} ${hidden === 1 ? 'is' : 'are'} not being shown` : 'Upload your company document', icon: 'alert', cls: 'text-primary-dark' }
  }

  /** PDF/JPG/PNG up to 10 MB for the document; JPG/PNG up to 3 MB for the logo — the API's own limits. Returns an error or null. */
  function fileProblem(kind, file) {
    const ext = (file.name.split('.').pop() || '').toLowerCase()
    if (kind === 'doc') {
      if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) return 'Upload your company document (GST certificate, shop licence or registration) as a PDF, JPG, or PNG.'
      if (file.size > 10 * 1024 * 1024) return 'The document must be smaller than 10 MB.'
    } else {
      if (!['jpg', 'jpeg', 'png'].includes(ext)) return 'Upload a JPG or PNG image.'
      if (file.size > 3 * 1024 * 1024) return 'The logo must be smaller than 3 MB.'
    }
    return null
  }

  /**
   * The company-form behaviour a page spreads into its component. The form
   * edits `this.co`; `this.coSaved` is the saved organisation it started
   * from (null for a new one). Picked files live in this closure — a File
   * inside Alpine's reactive proxy is not a Blob, and FormData refuses it.
   */
  function companyForm() {
    let files = { doc: null, logo: null }
    return {
      co: blankCompany(),
      coSaved: null,
      coErrors: {},
      SIZE_HINTS,

      coReset(org = null) {
        files = { doc: null, logo: null }
        if (this.co.logo_preview) URL.revokeObjectURL(this.co.logo_preview)
        this.coSaved = org
        this.co = companyFrom(org)
        this.coErrors = {}
      },

      coHiringTypes() { return Alpine.store('opts').data?.hiring_types || {} },
      coSizes() { const s = Inthes.opt('organisation_sizes'); return s.length ? s : ['Small', 'Mid', 'Large'] },
      coHasLogo() { return this.co.logo_new || this.co.logo },
      coLogoSrc() { return this.co.logo_preview || (this.co.logo ? this.co.logo_url : '') },

      coPick(kind, ev) {
        const file = ev.target.files && ev.target.files[0]
        ev.target.value = ''
        if (!file) return
        const problem = fileProblem(kind, file)
        if (problem) { Inthes.toast(problem, true); return }
        if (kind === 'doc') {
          files.doc = file
          this.co.doc_name = file.name
          this.co.doc_new = true
          this.coErrors.doc = ''
        } else {
          files.logo = file
          if (this.co.logo_preview) URL.revokeObjectURL(this.co.logo_preview)
          this.co.logo_preview = URL.createObjectURL(file)
          this.co.logo_new = true
        }
      },

      /** Takes back a picked file, restoring what the saved company had. */
      coRemove(kind) {
        if (kind === 'doc') {
          files.doc = null
          this.co.doc_new = false
          this.co.doc_name = this.coSaved?.document_name || ''
        } else {
          files.logo = null
          if (this.co.logo_preview) URL.revokeObjectURL(this.co.logo_preview)
          this.co.logo_preview = ''
          this.co.logo_new = false
        }
      },

      coValidate() {
        this.coErrors = companyErrors(this.co)
        return Object.keys(this.coErrors).length === 0
      },

      /**
       * Creates the company, or updates the saved one if anything changed
       * (PostJobController._saveCompany), then uploads any picked files.
       * The saved id is remembered before the files go up, so a failed
       * upload's retry updates this company instead of creating a second.
       */
      async coSave() {
        const existing = this.coSaved
        const body = companyBody(this.co)
        let saved = existing
        const unchanged = existing && Object.keys(body).every((k) => (existing[k] ?? null) === body[k])
        if (!unchanged) {
          const res = existing
            ? await Inthes.api(`/recruiter/organisations/${encodeURIComponent(existing.id)}`, { method: 'PATCH', body })
            : await Inthes.api('/recruiter/organisations', { method: 'POST', body })
          saved = withFilesFrom(res.data, existing)
          this.coSaved = saved
          this.co.id = saved.id
        }
        if (files.doc) {
          const res = await Inthes.upload(`/recruiter/organisations/${encodeURIComponent(saved.id)}/document`, files.doc)
          saved = Object.assign({}, saved, {
            document_name: res.data?.document_name || files.doc.name,
            document_url: res.data?.document_url || null, review_state: 'pending', verified: false,
          })
          files.doc = null
          this.co.doc_new = false
          this.co.has_doc = true
          this.co.doc_name = saved.document_name
          this.coSaved = saved
        }
        if (files.logo) {
          const res = await Inthes.upload(`/recruiter/organisations/${encodeURIComponent(saved.id)}/logo`, files.logo)
          saved = Object.assign({}, saved, { logo: true, logo_url: res.data?.logo_url || null })
          files.logo = null
          this.co.logo_new = false
          this.co.logo = true
          this.co.logo_url = saved.logo_url || ''
          this.coSaved = saved
        }
        return saved
      },
    }
  }

  // ── applicants ────────────────────────────────────────────────────────
  const apName = (a) => a?.profile?.name || 'Applicant'
  function apCurrentRole(a) {
    const ex = a?.profile?.experiences || []
    return ex.find((e) => e.currently_working) || ex[0] || null
  }
  function apDesignation(a) {
    const role = apCurrentRole(a)
    if (role) return role.designation || ''
    return (a?.profile?.preferred_roles || [])[0] || ''
  }
  function apLocation(a) {
    const p = a?.profile || {}
    return p.home_city || (p.location || [])[0] || ''
  }
  const apStrength = (a) => Number(a?.profile?.profile_strength || 0)
  function strengthLabel(p) {
    if (p <= 40) return 'Getting started'
    if (p <= 60) return 'Fair profile'
    if (p <= 80) return 'Good profile'
    if (p <= 95) return 'Strong profile'
    return 'Excellent profile'
  }
  function experienceRank(band) {
    const m = String(band || '').match(/(\d+)/)
    return m ? Number(m[1]) : 0
  }

  /** A message carried across a redirect (e.g. "Changes saved" after an edit). */
  function flash(message) {
    try { sessionStorage.setItem('emp.flash', message) } catch (e) { /* private mode */ }
  }
  window.addEventListener('inthes:ready', () => {
    let msg = null
    try { msg = sessionStorage.getItem('emp.flash'); sessionStorage.removeItem('emp.flash') } catch (e) { /* private mode */ }
    if (msg) Inthes.toast(msg)
  })

  window.Emp = {
    flash,
    STATUS_LABEL, STATUS_PILL, STATUS_DOT, APP_STATUSES, APP_LABEL, APP_PILL, APP_DOT, SIZE_HINTS, NOTICE_CLASS,
    day, salary, amount, withScheme, isEmail, awaitingPayment, docMissing, notices, noticeHref,
    loadMine, loadOrgs, toggleJob, closeJob, shareJob, replaceIn, canToggle, canClose, canEdit, jobActions,
    blankCompany, companyFrom, companyBody, companyErrors, withFilesFrom, fileProblem, companyForm,
    orgStateLabel, orgStatePill, orgNotice,
    apName, apCurrentRole, apDesignation, apLocation, apStrength, strengthLabel, experienceRank,
    /** The helpers templates call, spread into a page component. */
    helpers: {
      statusLabel: (s) => STATUS_LABEL[s] || s || '',
      statusPill: (s) => STATUS_PILL[s] || 'bg-surface-muted text-ink-secondary',
      statusDot: (s) => STATUS_DOT[s] || 'bg-ink-muted',
      appLabel: (s) => APP_LABEL[s] || s || '',
      appPill: (s) => APP_PILL[s] || 'bg-surface-muted text-ink-secondary',
      appDot: (s) => APP_DOT[s] || 'bg-ink-muted',
      day,
      salaryOf: (j) => j?.salary || salary(j?.salary_min, j?.salary_max),
      noticesOf: (j) => notices(j),
      noticeClass: (t) => NOTICE_CLASS[t] || '',
      noticeHref: (j, a) => noticeHref(j, a),
      jobUrl: (j) => '/employer/jobs/' + encodeURIComponent(j.id),
      publicUrl: (j) => '/jobs/' + encodeURIComponent(j.code || ''),
      canToggle, canClose, canEdit,
      awaitingPayment, docMissing,
    },
  }
})()
