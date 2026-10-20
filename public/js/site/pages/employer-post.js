/**
 * /employer/jobs/new and /employer/jobs/{id}/edit — Post a job on one page.
 *
 * The app's four-step wizard (Job details → Candidate requirements →
 * Company details → Choose plan) as one form with a live preview. Same
 * fields, defaults, option lists and messages (PostJobController /
 * JobDraft / CompanyFormFields); the plan step is its own checkout page.
 *
 * Submit: save the company (create or update, then document and logo) →
 * save the job as a draft (or PATCH it) → post it on a plan credit if one
 * is left, otherwise go to checkout. Editing a posted job PATCHes it, and a
 * live one goes back for approval — said before saving.
 */
document.addEventListener('alpine:init', () => {
  const ANY_EXPERIENCE = 'Any experience'
  /** Every new posting asks for these before Apply goes out (PostJobController.defaultRequiredFields). */
  const DEFAULT_REQUIRED_FIELDS = ['qualification', 'experience', 'resume']
  /** Optional fields an employer can empty on an edit — sent as null (JobDraft.clearable). */
  const CLEARABLE = ['about', 'locality', 'contact_phone', 'whatsapp_phone']
  const MAX_OPENINGS = 1000
  const tenDigits = (v) => String(v || '').replace(/\D/g, '').slice(-10)

  const blankForm = () => ({
    title: '', role: '', about: '', openings: '1',
    salary_min: '', salary_max: '', shift: '', type: '',
    state: '', city: '', locality: '', pincode: '', latitude: null, longitude: null,
    charges_fee: false,
    min_education: '', experience: '', preferred_gender: 'Any', skills: [], qualifications: [],
    contact_mode: 'calls_whatsapp', contact_phone: '', same_whatsapp: true, whatsapp_phone: '',
    required_fields: DEFAULT_REQUIRED_FIELDS.slice(),
  })

  Alpine.data('empPost', (props = {}) => ({
    ...Emp.helpers,
    ...Emp.companyForm(),
    jobId: props.jobId || null,
    loading: true,
    loadError: '',
    job: null,
    mode: 'new', // new | draft (saved, unpaid) | edit (posted)
    f: blankForm(),
    showDescription: false,
    showMoreReqs: false,
    errors: {},
    orgs: [],
    orgChoice: '', // an organisation id, or 'new'
    coEditing: false,
    busy: false,
    busyText: '',
    locating: false,
    submitted: null,
    credit: null,

    async init() {
      await Inthes.whenReady()
      if (Alpine.store('auth').signedIn) this.load()
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.loading = true
      this.loadError = ''
      try {
        const [orgs, contact, jobs] = await Promise.all([
          Emp.loadOrgs(),
          Inthes.api('/recruiter/profile').then((r) => r.data || {}).catch(() => ({})),
          this.jobId ? Emp.loadMine() : Promise.resolve([]),
        ])
        this.orgs = orgs
        this.f = blankForm()
        this.f.contact_phone = tenDigits(contact.contact_phone || Alpine.store('auth').phone || '')
        if (this.jobId) {
          const job = jobs.find((j) => j.id === this.jobId)
          if (!job) {
            Inthes.toast('This posting is no longer available.', true)
            location.replace('/employer/jobs')
            return
          }
          if (['closed', 'expired'].includes(job.posting_status)) {
            Inthes.toast('A closed job can’t be edited.', true)
            location.replace(this.jobUrl(job))
            return
          }
          this.beginEdit(job)
        } else {
          // The employer's own company, filled in for a repeat post.
          this.pickOrg(orgs.length ? orgs[orgs.length - 1].id : 'new')
        }
      } catch (e) { this.loadError = e.message }
      this.loading = false
    },

    /** Seeds the form from a posting: a saved, unpaid one continues as a draft; anything else is an edit. */
    beginEdit(job) {
      this.job = job
      this.mode = Emp.awaitingPayment(job) ? 'draft' : 'edit'
      const f = this.f
      Object.assign(f, {
        role: job.role || '', title: job.title || '', about: job.about || '',
        openings: String(job.openings ?? 1), type: job.type || '', shift: job.shift || '',
        charges_fee: job.charges_fee ?? false,
        salary_min: job.salary_min == null ? '' : String(job.salary_min),
        salary_max: job.salary_max == null ? '' : String(job.salary_max),
        state: job.state || '', city: job.city || '', locality: job.locality || '', pincode: job.pincode || '',
        latitude: job.latitude ?? null, longitude: job.longitude ?? null,
        min_education: job.min_education || '', experience: job.experience || '',
        skills: (job.skills || []).slice(), qualifications: (job.qualifications || []).slice(),
      })
      this.showDescription = !!job.about
      this.showMoreReqs = !!(f.skills.length || f.qualifications.length)
      if (job.preferred_gender) f.preferred_gender = job.preferred_gender
      if (job.contact_mode) f.contact_mode = job.contact_mode
      if (job.contact_phone) f.contact_phone = job.contact_phone
      if (job.whatsapp_phone && job.whatsapp_phone !== job.contact_phone) {
        f.same_whatsapp = false
        f.whatsapp_phone = job.whatsapp_phone
      }
      if ((job.required_fields || []).length) f.required_fields = job.required_fields.slice()
      // Older postings carry a city but no state — find it, so the city select shows it.
      if (!f.state && f.city) {
        const states = Alpine.store('opts').data?.state_cities || {}
        f.state = Object.keys(states).find((s) => states[s].includes(f.city)) || ''
      }

      // The job's own company — never another one standing in for it.
      const org = this.orgs.find((o) => o.id === job.organisation_id) || this.orgs.find((o) => o.name === job.organisation)
      if (org) this.pickOrg(org.id)
      else { this.pickOrg('new'); this.co.name = job.organisation || '' }
    },

    pickOrg(id) {
      this.orgChoice = id
      const org = id === 'new' ? null : this.orgs.find((o) => o.id === id) || null
      this.coReset(org)
      this.coEditing = !org
    },

    get pickedOrg() { return this.orgChoice === 'new' ? null : this.orgs.find((o) => o.id === this.orgChoice) || null },

    // ── option lists ───────────────────────────────────────────────────
    get cities() { return Inthes.citiesOf(this.f.state) },
    get experienceOptions() { return [...Inthes.opt('experience_bands'), ANY_EXPERIENCE] },
    get contactModes() { return Alpine.store('opts').data?.contact_modes || {} },
    get needsPhone() { const m = this.f.contact_mode; return m === 'calls' || m === 'calls_whatsapp' },
    shiftLabel(s) { return s.toLowerCase().includes('shift') ? s : s + ' shift' },

    pickState(value) {
      this.f.state = value
      if (!Inthes.citiesOf(value).includes(this.f.city)) this.f.city = ''
      this.errors.state = ''
    },

    /** Digits only, as the app's input formatters allow. */
    digits(key, max) { this.f[key] = String(this.f[key] || '').replace(/\D/g, '').slice(0, max) },

    salaryProblem(requireBoth = false) {
      const f = this.f
      const min = f.salary_min === '' ? null : parseInt(f.salary_min, 10)
      const max = f.salary_max === '' ? null : parseInt(f.salary_max, 10)
      if (requireBoth && (min == null || max == null)) return 'Enter the minimum and maximum monthly salary'
      if (min != null && min < 1) return 'Enter a minimum salary above ₹0'
      if (min != null && max != null && max <= min) return 'Maximum salary must be more than the minimum salary'
      return ''
    },
    onSalary() { this.errors.salary = this.salaryProblem() },

    phoneProblem(label, value) {
      const v = String(value || '').trim()
      if (!v) return null
      return /^\d{10}$/.test(v) ? null : `${label} must be 10 digits`
    },

    /** "Use current location": coordinates, and the nearest listed city when one is close. */
    useLocation() {
      if (!navigator.geolocation) { Inthes.toast('Could not fetch current location', true); return }
      this.locating = true
      navigator.geolocation.getCurrentPosition((pos) => {
        const { latitude, longitude } = pos.coords
        const f = this.f
        f.latitude = Math.round(latitude * 1e6) / 1e6
        f.longitude = Math.round(longitude * 1e6) / 1e6
        // No reverse geocoder on the web — the option list's own city
        // coordinates stand in, and only when one is genuinely nearby.
        const opts = Alpine.store('opts').data || {}
        let best = null
        for (const [city, c] of Object.entries(opts.city_coordinates || {})) {
          const d = Math.hypot(c.lat - latitude, (c.lng - longitude) * Math.cos(latitude * Math.PI / 180)) * 111
          if (!best || d < best.d) best = { city, d }
        }
        const states = opts.state_cities || {}
        const state = best && best.d < 50 ? Object.keys(states).find((s) => states[s].includes(best.city)) : null
        if (state) { f.state = state; f.city = best.city; this.errors.state = ''; this.errors.city = '' } else Inthes.toast('Pick your city from the list', true)
        this.locating = false
      }, (err) => {
        this.locating = false
        Inthes.toast(err.code === 1 ? 'Location permission denied' : 'Could not fetch current location', true)
      }, { enableHighAccuracy: true, timeout: 15000 })
    },

    // ── validation ─────────────────────────────────────────────────────
    /** What the wizard's steps refused to advance without (PostJobController.validateStep), by field. */
    jobErrors() {
      const f = this.f
      const e = {}
      if (!f.title.trim()) e.title = 'Select a job title'
      if (!f.role.trim()) e.role = 'Select a job category'
      const n = parseInt(f.openings, 10)
      if (!n || n < 1) e.openings = 'Enter the number of openings'
      else if (n > MAX_OPENINGS) e.openings = `Openings can be at most ${MAX_OPENINGS}`
      const salary = this.salaryProblem(true)
      if (salary) e.salary = salary
      if (!f.shift) e.shift = 'Select the job shift'
      if (!f.type) e.type = 'Select the job type'
      if (!f.state) e.state = 'Select the state'
      if (!f.city) e.city = 'Select the city'
      if (f.charges_fee === null) e.charges_fee = 'Say whether you charge candidates a fee'
      if (!f.min_education) e.min_education = 'Select the minimum education'
      if (!f.experience) e.experience = 'Select the experience needed'
      if (!f.contact_mode) e.contact_mode = 'Choose how candidates reach you'
      if (this.needsPhone) {
        if (!f.contact_phone.trim()) e.contact_phone = 'Add the number candidates should call'
        else {
          const p = this.phoneProblem('Contact number', f.contact_phone)
          if (p) e.contact_phone = p
        }
        if (f.contact_mode === 'calls_whatsapp' && !f.same_whatsapp) {
          if (!f.whatsapp_phone.trim()) e.whatsapp_phone = 'Add your WhatsApp number'
          else {
            const w = this.phoneProblem('WhatsApp number', f.whatsapp_phone)
            if (w) e.whatsapp_phone = w
          }
        }
      }
      return e
    },

    /** Re-checks one field once the employer starts fixing it. */
    recheck(key) {
      if (!this.errors[key]) return
      this.errors[key] = this.jobErrors()[key] || ''
    },

    /** The checklist beside the preview: required blocks and whether each is done. */
    get checklist() {
      const e = this.jobErrors()
      const c = Emp.companyErrors(this.co)
      return [
        { label: 'Job title & category', ok: !e.title && !e.role },
        { label: 'Openings & salary', ok: !e.openings && !e.salary },
        { label: 'Shift & job type', ok: !e.shift && !e.type },
        { label: 'Location', ok: !e.state && !e.city },
        { label: 'Education & experience', ok: !e.min_education && !e.experience },
        { label: 'Contact preferences', ok: !e.contact_mode && !e.contact_phone && !e.whatsapp_phone },
        { label: 'Company details', ok: !c.name && !c.email && !c.size && !c.website },
        { label: 'Company document', ok: !c.doc },
      ]
    },
    get doneCount() { return this.checklist.filter((c) => c.ok).length },

    validate() {
      this.errors = this.jobErrors()
      const coOk = this.coValidate()
      const coFormProblem = Object.keys(this.coErrors).some((k) => k !== 'doc')
      if (coFormProblem) this.coEditing = true
      if (Object.keys(this.errors).length === 0 && coOk) return true
      this.$nextTick(() => {
        const first = [...document.querySelectorAll('[data-field]')].find((el) => {
          const k = el.dataset.field
          return k.startsWith('co.') ? this.coErrors[k.slice(3)] : this.errors[k]
        })
        if (first) {
          first.scrollIntoView({ behavior: 'smooth', block: 'center' })
          const input = first.querySelector('input:not([type=file]),select,textarea')
          if (input) setTimeout(() => input.focus({ preventScroll: true }), 350)
        }
      })
      Inthes.toast('Please fix the highlighted fields', true)
      return false
    },

    // ── saving ─────────────────────────────────────────────────────────
    /** The body of both POST and PATCH /recruiter/jobs (JobDraft.toApi). */
    body(organisationId, asDraft = false) {
      const f = this.f
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
        about: orNull(this.showDescription ? f.about : ''),
        experience: orNull(f.experience), min_education: orNull(f.min_education),
        preferred_gender: orNull(f.preferred_gender),
        qualifications: f.qualifications.slice(), skills: f.skills.slice(),
        contact_mode: orNull(f.contact_mode),
        contact_phone: orNull(this.needsPhone ? f.contact_phone : ''),
        whatsapp_phone: orNull(whatsapp),
        required_fields: f.required_fields.slice(),
      }
      for (const k of Object.keys(body)) {
        if (body[k] == null && !CLEARABLE.includes(k)) delete body[k]
      }
      return asDraft ? Object.assign({ save_as_draft: true }, body) : body
    },

    /** Server-side field errors land on the field they name. */
    applyServerErrors(e) {
      const map = e.errors || {}
      let placed = false
      for (const [k, v] of Object.entries(map)) {
        const msg = Array.isArray(v) ? v[0] : v
        if (k in this.f || ['salary_min', 'salary_max'].includes(k)) {
          this.errors[k.startsWith('salary') ? 'salary' : k] = msg
          placed = true
        }
      }
      if (placed) this.$nextTick(() => document.querySelector('.wa-error:not([style*="display: none"])')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
    },

    async submit() {
      if (this.busy || !this.validate()) return
      const editingLive = this.mode === 'edit' && this.job?.posting_status === 'active'
      if (editingLive) {
        const ok = await Inthes.confirm({
          title: 'Edit this live job post?',
          message: 'Changes need approval, so the post will be paused for candidates until an admin reviews it.',
          ok: 'Save & send for approval', danger: true,
        })
        if (!ok) return
      }
      this.busy = true
      let job = null
      try {
        this.busyText = 'Saving company…'
        const org = await this.coSave()
        this.storeOrg(org)
        this.orgChoice = org.id
        this.busyText = 'Saving job…'
        if (this.mode === 'new') {
          const res = await Inthes.api('/recruiter/jobs', { method: 'POST', body: this.body(org.id, true) })
          job = res.data
          // A retry or reload continues this draft instead of starting another.
          this.job = job
          this.mode = 'draft'
          this.jobId = job.id
          history.replaceState(null, '', `/employer/jobs/${encodeURIComponent(job.id)}/edit`)
        } else {
          const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.job.id)}`, { method: 'PATCH', body: this.body(org.id) })
          job = res.data
          this.job = Object.assign({}, this.job, job)
        }
      } catch (e) {
        Inthes.toast(e.message, true)
        this.applyServerErrors(e)
        this.busy = false
        this.busyText = ''
        return
      }

      if (this.mode === 'edit') {
        const msg = job.posting_status === 'pending_approval'
          ? 'Saved — your changes need approval, so the post is paused for candidates until it is reviewed'
          : 'Changes saved'
        Emp.flash(msg)
        location.href = '/employer/jobs'
        return
      }
      await this.toPlan(job)
    },

    /**
     * Moves on to paying — unless postings from an earlier pack are left, in
     * which case the post goes straight to review on one (PostJobController._toPlanStep).
     */
    async toPlan(job) {
      this.busyText = 'Checking your plan…'
      let credit = null
      try {
        const res = await Inthes.api('/recruiter/job-plans')
        credit = (res.data?.credits || []).find((c) => c.remaining > 0) || null
      } catch (e) { /* checkout loads them again */ }
      if (credit) {
        try {
          this.busyText = 'Posting on your plan…'
          const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(job.id)}/use-credit`, { method: 'POST', body: { credit_id: credit.id } })
          this.submitted = res.data
          this.credit = credit
          // A reload now lands on the job itself, not back in its editor.
          history.replaceState(null, '', `/employer/jobs/${encodeURIComponent(job.id)}`)
          const left = credit.remaining - 1
          Inthes.toast(left > 0 ? `Posted on your existing plan — ${left} posting${left === 1 ? '' : 's'} left` : 'Posted on your existing plan')
          window.scrollTo({ top: 0 })
          this.busy = false
          return
        } catch (e) { Inthes.toast(e.message, true) }
      }
      location.href = `/employer/jobs/${encodeURIComponent(job.id)}/checkout`
    },

    storeOrg(org) {
      const i = this.orgs.findIndex((o) => o.id === org.id)
      if (i === -1) this.orgs.push(org)
      else this.orgs.splice(i, 1, Object.assign({}, this.orgs[i], org))
    },

    postAnother() { location.href = '/employer/jobs/new' },

    // ── preview ────────────────────────────────────────────────────────
    get previewSalary() { return Emp.salary(this.f.salary_min, this.f.salary_max) },
    get previewCompany() { return this.co.name.trim() || 'Your company' },
    get submitLabel() {
      if (this.mode === 'edit') return this.job?.posting_status === 'active' ? 'Save & send for approval' : 'Save changes'
      return 'Save & continue'
    },
  }))
})
