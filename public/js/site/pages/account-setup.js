/**
 * /account/setup — "Complete your profile" for a new job seeker.
 *
 * The app's own 10-step wizard (OnboardingController): one section per
 * step, in the app's order and with its titles. Each step saves only what
 * was answered (blanks never wipe an earlier answer — the app's onboarding
 * rule); every step can be skipped, and "Do it later" is always there —
 * except in an apply run (below).
 *
 * `?next=` (a site path) is where the visitor was headed — e.g. the job they
 * tried to apply to from an employer account before switching to a new job
 * seeker account. "Do it later" and Finish go back there. When it is a job's
 * Apply (`/jobs/{code}?apply=1`) the run is gated as the app's is: the steps
 * holding what that job still needs cannot be skipped, and the way out is
 * "Not now" (back to the job without applying).
 */
document.addEventListener('alpine:init', () => {
  const K = window.ProfileKit
  const { trim, orNull, compact } = K

  // OnboardingStep order, titleFor / subtitleFor.
  const STEPS = [
    { key: 'personal', icon: 'user', title: 'About you', subtitle: 'Employers see this on every application you send.' },
    { key: 'education', icon: 'graduation', title: 'Your education', subtitle: 'Most postings filter on qualification. Where you studied goes on your profile too.' },
    { key: 'experience', icon: 'briefcase', title: 'Your experience', subtitle: 'The band is what postings filter on; your latest role is what an employer reads.' },
    { key: 'languages', icon: 'languages', title: 'Languages', subtitle: 'Useful wherever a role deals with patients or customers.' },
    { key: 'preferences', icon: 'target', title: 'What are you looking for?', subtitle: 'We use this to pick what shows up on your home feed.' },
    { key: 'availability', icon: 'calendar', title: 'Your availability', subtitle: 'When can you start? Everything here is a tap — nothing to type.' },
    { key: 'skills', icon: 'award', title: 'Skills & interests', subtitle: 'What you are good at, and what you enjoy. Pick up to 3 hobbies.' },
    { key: 'about', icon: 'sparkles', title: 'A line about you', subtitle: 'Two or three sentences is plenty.' },
    { key: 'photo', icon: 'camera', title: 'Your photo', subtitle: 'A face makes a shortlist. Optional, and changeable any time.' },
    { key: 'video', icon: 'video', title: 'Your intro video', subtitle: 'Optional, and the single fastest way to stand out — 60 seconds is enough.' },
  ]

  /**
   * OnboardingController._stepFields — which job requirements each step can
   * satisfy. `resume` is rendered from the personal, education and experience
   * steps, so a job wanting one asks all three (each for its own input).
   */
  const STEP_FIELDS = {
    personal: ['name', 'gender', 'dob', 'address', 'resume'],
    education: ['qualification', 'resume'],
    experience: ['experience', 'resume'],
    preferences: ['location'],
  }
  const RESUME_INPUT = { personal: 'name', education: 'qualification', experience: 'experience' }
  /** OnboardingController.gateLabel — the question as it reads on screen. */
  const GATE_LABEL = {
    name: 'Full name', gender: 'Gender', dob: 'Date of birth', address: 'Where you live',
    qualification: 'Highest qualification', experience: 'Total experience', location: 'Preferred cities', resume: 'Resume details',
  }

  Alpine.data('accountSetup', () => K.merge(K.mixin(), {
    STEPS,
    step: 0,
    ready: false,
    busy: false,
    quick: '',
    /** The team answer is kept in the form and saved with the step, as in the app. */
    profTeamDeferred: true,
    /**
     * Reached from an Apply: what the job was missing when this run started.
     * A snapshot (the app's `_outstanding`) — the bar must not move as it is answered.
     */
    outstanding: [],
    /** Where to carry on afterwards — a same-site path only. */
    nextUrl: (() => {
      const n = new URLSearchParams(location.search).get('next') || ''
      return n.startsWith('/') && !n.startsWith('//') ? n : ''
    })(),
    get nextIsApply() { return /^\/jobs\/[^/?]+\?apply=1/.test(this.nextUrl) },

    async init() {
      await Inthes.whenReady()
      if (!this.$store.auth.signedIn) {
        window.addEventListener('inthes:signed-in', () => this.load(), { once: true })
        return
      }
      this.load()
    },

    async load() {
      try { await Inthes.loadMe() } catch (e) { /* signed out */ }
      this.profFill()
      // With no saved degree the form starts from the flat qualification.
      if (!(this.me?.educations || []).length) this.pf.edu.qualification = this.me?.qualification || ''
      await this._loadGate()
      this.ready = true
    },

    // ── the Apply gate (OnboardingController, gated mode) ───────────────
    /** The job in `?next=/jobs/{code}?apply=1`, and what it needs that the profile lacks. */
    async _loadGate() {
      if (!this.nextIsApply || !Inthes.jobsKit) return
      const code = decodeURIComponent(this.nextUrl.match(/^\/jobs\/([^/?]+)/)[1])
      try {
        const job = (await Inthes.api('/jobs/' + encodeURIComponent(code))).data
        this.outstanding = job ? Inthes.jobsKit.missingFor(job, this.me) : []
      } catch (e) { this.outstanding = [] /* the job page re-checks on the way back */ }
    },
    get isGated() { return this.outstanding.length > 0 },
    _requiredOf(key) { return (STEP_FIELDS[key] || []).filter((f) => this.outstanding.includes(f)) },
    /** A step this job needs something from: no Skip, and Save waits until it is filled. */
    stepRequired(key) { return this.isGated && this._requiredOf(key).length > 0 },
    /** Read from the form, not the saved profile — satisfied the moment it is filled in. */
    _fieldAnswered(field) {
      const f = this.pf
      switch (field) {
        case 'name': return !!trim(f.name)
        case 'gender': return !!f.gender
        case 'dob': return !!f.dob
        case 'address': return this._address != null
        case 'qualification': return !!f.edu.qualification || (this.me?.educations || []).length > 0 || !!trim(this.me?.qualification)
        case 'experience': return !!f.experience
        case 'location': return f.location.length > 0
        case 'resume': return ['name', 'qualification', 'experience'].every((x) => this._fieldAnswered(x))
      }
      return false
    },
    /** "To continue, fill in: …" — what [key] still owes this job, in on-screen words. */
    gateMissingOn(key) {
      if (!this.isGated) return []
      const out = []
      for (const f of this._requiredOf(key)) {
        const field = f === 'resume' ? RESUME_INPUT[key] : f
        if (!field || this._fieldAnswered(field) || out.includes(field)) continue
        out.push(field)
      }
      return out.map((f) => GATE_LABEL[f])
    },
    stepSatisfied(key) { return this.gateMissingOn(key).length === 0 },
    /** The question carries a `*` when this job makes it compulsory. */
    profGateReq(field) {
      if (!this.isGated) return false
      return STEPS.some((s) => this._requiredOf(s.key).some((f) => (f === 'resume' ? RESUME_INPUT[s.key] : f) === field))
    },
    get gateSummary() {
      const left = this.outstanding.filter((f) => !this._fieldAnswered(f)).length
      return left === 0 ? 'Everything this job needs is filled in' : `This job needs ${left} more detail${left === 1 ? '' : 's'}`
    },
    /** Leaves a gated run without applying — back to the job, not into Quick Apply. */
    abandonGate() {
      if (this.busy) return
      location.href = this.nextUrl.replace(/([?&])apply=1(&|$)/, (m, a, b) => (b ? a : '')).replace(/[?&]$/, '')
    },

    /**
     * Whether [key] has an answer — on screen or saved (OnboardingController.hasAnswer
     * reads the form, which starts from the saved profile). Drives the bar, the
     * count, and Skip (hidden once answered).
     */
    answered(key) {
      const f = this.pf
      const set = (v) => v !== null && v !== undefined && v !== ''
      const draft = {
        personal: [f.name, f.email, f.gender, f.dob, f.address, f.home_city, f.home_state, f.marital_status,
          f.father_name, f.mother_name, f.native_place, f.nationality, f.alternate_phone, f.whatsapp_number].some((v) => !!trim(v)),
        education: this.profEduStarted,
        experience: !!f.experience || this.profExpStarted || f.managed_team != null,
        languages: f.languages.length > 0,
        preferences: f.location.length > 0 || f.roles.length > 0 || f.job_types.length > 0 || f.shifts.length > 0 || (f.org_types || []).length > 0 || !!f.salary,
        availability: [f.currently_employed, f.immediate_joiner, f.willing_to_relocate, f.has_vehicle, f.has_driving_licence, f.notice_period, f.last_working_date, f.earliest_joining_date].some(set),
        skills: f.skills.length > 0 || f.hobbies.length > 0,
        about: !!trim(f.about),
        video: !!this.profVideoDraft,
      }[key]
      return !!draft || this._savedAnswer(key)
    },
    _savedAnswer(key) {
      const m = this.me || {}
      const set = (v) => v !== null && v !== undefined && v !== ''
      switch (key) {
        case 'personal': return [m.name, m.email, m.gender, m.dob, m.address, m.home_city, m.home_state, m.marital_status,
          m.father_name, m.mother_name, m.native_place, m.nationality, m.alternate_phone, m.whatsapp_number].some((v) => !!trim(v))
        case 'education': return (m.educations || []).length > 0 || !!trim(m.qualification)
        case 'experience': return !!trim(m.experience) || (m.experiences || []).length > 0 || m.managed_team != null
        case 'languages': return (m.languages || []).length > 0
        case 'preferences': return (m.location || []).length > 0 || (m.preferred_roles || []).length > 0 || (m.preferred_job_types || []).length > 0
          || (m.preferred_shifts || []).length > 0 || (m.preferred_organisation_types || []).length > 0 || !!m.expected_salary
        case 'availability': return [m.currently_employed, m.immediate_joiner, m.willing_to_relocate, m.has_vehicle, m.has_driving_licence, m.notice_period, m.last_working_date, m.earliest_joining_date].some(set)
        case 'skills': return (m.skills || []).length > 0 || (m.hobbies || []).length > 0
        case 'about': return !!trim(m.about)
        case 'photo': return !!m.photo || !!m.photo_url
        case 'video': return !!m.intro_video_url
      }
      return false
    },
    get answeredCount() { return STEPS.filter((s) => this.answered(s.key)).length },

    /** The typed address, or failing that "City, State" — null when neither. */
    get _address() {
      const typed = orNull(this.pf.address)
      if (typed) return typed
      const parts = [this.pf.home_city, this.pf.home_state].filter((s) => trim(s))
      return parts.length ? parts.join(', ') : null
    },

    // ── saving a step (only what was answered) ──────────────────────────
    get _key() { return STEPS[this.step].key },

    _problem(key) {
      if (key === 'personal') {
        if (!trim(this.pf.name)) return 'Enter your full name'
        return this._profPhoneProblem()
      }
      if (key === 'education' && this.profEduStarted) return this._profEduProblem()
      if (key === 'experience' && this.profExpStarted) return this._profExpProblem()
      return null
    },

    async _save(key) {
      const f = this.pf
      const me = this.me || {}
      const patch = (body) => Inthes.api('/candidate/profile', { method: 'PATCH', body })
      const same = (a, b) => JSON.stringify(a || []) === JSON.stringify(b || [])
      switch (key) {
        case 'personal':
          await patch(compact({
            name: orNull(f.name), email: orNull(f.email), gender: orNull(f.gender), dob: orNull(f.dob), address: this._address,
            home_city: orNull(f.home_city), home_pincode: orNull(f.home_pincode), home_latitude: f.home_latitude, home_longitude: f.home_longitude,
            home_state: orNull(f.home_state), marital_status: orNull(f.marital_status), father_name: orNull(f.father_name),
            mother_name: orNull(f.mother_name), native_place: orNull(f.native_place), nationality: orNull(f.nationality),
            alternate_phone: orNull(f.alternate_phone), whatsapp_number: orNull(f.whatsapp_number),
          }))
          break
        case 'education':
          if (this.profEduStarted && !this._profEduProblem()) {
            await patch({ qualification: f.edu.qualification })
            await this._profEduWrite()
            this.pf.edu = K.emptyEdu()
          }
          break
        case 'experience':
          // The band as picked (any band, fresher included), then a role if one
          // was filled in — the app's ExperienceStep.
          if (f.experience && f.experience !== me.experience) await patch({ experience: f.experience })
          if (this.profExpStarted && !this._profExpProblem()) {
            await this._profExpWrite()
            this.pf.exp = K.emptyExp()
          }
          // The team answer, kept in the form until now; its line is already
          // in the About text, which saves with the About step.
          if (f.managed_team != null && f.managed_team !== me.managed_team) await patch({ managed_team: f.managed_team })
          break
        case 'languages':
          if (f.languages.length) {
            await Inthes.api('/candidate/profile/languages', { method: 'PUT', body: { languages: f.languages, language_levels: this._profLangLevels() } })
          }
          break
        case 'preferences':
          if (f.location.length && !same(f.location, me.location)) await patch({ location: f.location })
          if (f.roles.length || f.job_types.length || f.shifts.length || (f.org_types || []).length || f.salary) {
            await Inthes.api('/candidate/profile/preferences', {
              method: 'PATCH',
              body: compact({
                preferred_roles: f.roles.length ? f.roles : null, preferred_job_types: f.job_types.length ? f.job_types : null,
                preferred_shifts: f.shifts.length ? f.shifts : null,
                preferred_organisation_types: (f.org_types || []).length ? f.org_types : null,
                expected_salary: f.salary || null,
              }),
            })
          }
          break
        case 'availability': {
          const body = this._profAvailBody()
          if (Object.keys(body).length) await Inthes.api('/candidate/profile/availability', { method: 'PATCH', body })
          break
        }
        case 'skills':
        case 'about': {
          const changed = trim(f.about) !== trim(me.about) || !same(f.skills, me.skills) || !same(f.hobbies, me.hobbies)
          if (changed && (trim(f.about) || f.skills.length || f.hobbies.length)) {
            await Inthes.api('/candidate/profile/about', { method: 'PATCH', body: this._profAboutBody() })
          }
          break
        }
        case 'video':
          // A clip picked but not yet sent goes out with "Finish".
          if (this.profVideoDraft) await this.profUploadVideo()
          break
        // 'photo' uploads the moment it is taken or chosen.
      }
      await Inthes.loadMe()
      this.pf.experience = this.me?.experience || this.pf.experience
    },

    async next() {
      if (this.busy) return
      // A step the job needs waits until it holds what the job asks for.
      const owed = this.gateMissingOn(this._key)
      if (owed.length) { Inthes.toast('To continue, fill in: ' + owed.join(', '), true); return }
      const problem = this._problem(this._key)
      if (problem) { Inthes.toast(problem, true); return }
      this.busy = true
      try {
        await this._save(this._key)
        if (this.step < STEPS.length - 1) {
          this.step += 1
          window.scrollTo({ top: 0, behavior: 'smooth' })
        } else {
          await this._finish()
        }
      } catch (e) {
        // Stay on the step so the answer is not lost.
        Inthes.toast(e.message || "Couldn't save that. Try again, or skip for now.", true)
      }
      this.busy = false
    },

    prev() {
      if (this.step > 0) { this.step -= 1; window.scrollTo({ top: 0, behavior: 'smooth' }); return }
      // In an apply run Back on the first step goes back to the job.
      if (this.isGated) this.abandonGate()
    },
    goTo(i) { if (i < this.step && !this.busy) this.step = i },
    async skip() {
      if (this.busy || this.stepRequired(this._key)) return
      if (this.step < STEPS.length - 1) { this.step += 1; window.scrollTo({ top: 0, behavior: 'smooth' }); return }
      this.busy = true
      await this._finish()
    },

    /** Writes the resume (silently) and moves on, as the app's finish does. */
    async _finish() {
      await this._renderResume()
      try { sessionStorage.setItem('inthes.flash', 'Profile saved') } catch (e) { /* private mode */ }
      location.href = this.nextUrl || '/jobs?sort=relevant'
    },

    /**
     * The resume is re-rendered on the way out rather than asked for — whenever
     * the profile can fill one, as the app's `_generateResumeQuietly` does.
     * Silent: a profile too sparse to render is not an error.
     */
    async _renderResume() {
      if (K.missingForResume(this.me).length) return
      try { await Inthes.api('/candidate/profile/resume/generate', { method: 'POST' }) } catch (e) { /* the profile can rebuild it */ }
    },

    /**
     * "Do it later" — saves the step on screen (best effort) and leaves, as
     * the app's skipAll. Not offered in an apply run, where it is "Not now".
     */
    async later() {
      if (this.busy) return
      if (this.isGated) { this.abandonGate(); return }
      this.busy = true
      if (!this._problem(this._key)) { try { await this._save(this._key) } catch (e) { /* never trap someone who asked to leave */ } }
      await this._renderResume()
      location.href = this.nextUrl || '/jobs'
    },

    // ── step 2 helpers ──────────────────────────────────────────────────
    async addDegree() {
      if (!this.profEduStarted) { Inthes.toast('Fill in a degree first', true); return }
      const problem = this._profEduProblem()
      if (problem) { Inthes.toast(problem, true); return }
      await this._profRun(async () => {
        await this._profEduWrite()
        await Inthes.loadMe()
        this.pf.edu = K.emptyEdu()
        Inthes.toast('Education added')
      })
    },
    async addRole() {
      if (!this.profExpStarted) { Inthes.toast('Fill in a role first', true); return }
      const problem = this._profExpProblem()
      if (problem) { Inthes.toast(problem, true); return }
      await this._profRun(async () => {
        await this._profExpWrite()
        await Inthes.loadMe()
        this.pf.exp = K.emptyExp()
        Inthes.toast('Experience added')
      })
    },

    // ── "Describe yourself in one line" ─────────────────────────────────
    /** Fills what it recognises from the app's own lists; nothing guessed, nothing already answered overwritten. */
    quickFill() {
      const line = trim(this.quick)
      if (!line) { Inthes.toast('Write a line about yourself first', true); return }
      const text = line.toLowerCase()
      const f = this.pf
      const opt = (k) => Inthes.opt(k)
      let filled = 0
      const mentions = (o) => new RegExp('\\b' + K.esc(o.toLowerCase()) + '\\b').test(text)
      const longestIn = (opts) => {
        const hits = opts.filter((o) => trim(o) && mentions(o))
        return hits.length ? hits.reduce((a, b) => (a.length >= b.length ? a : b)) : null
      }
      const role = longestIn(opt('designations')) || longestIn(opt('categories'))
      if (role && !trim(f.exp.designation)) { f.exp.designation = role; filled++ }
      const category = longestIn(opt('categories'))
      if (category && !f.roles.includes(category)) { f.roles.push(category); filled++ }
      const years = text.match(/(\d+(?:\.\d+)?)\s*\+?\s*(?:years?|yrs?)/)
      if (!f.experience) {
        const bands = opt('experience_bands')
        const band = text.includes('fresher') ? K.bandFor(0, bands) : (years ? K.bandFor(Number(years[1]), bands) : null)
        if (band) { f.experience = band; filled++ }
      }
      const city = longestIn(opt('cities'))
      if (city && !f.location.includes(city)) { f.location.push(city); filled++ }
      const degree = longestIn(opt('qualifications'))
      if (degree && !f.edu.qualification) { f.edu.qualification = degree; filled++ }
      for (const skill of opt('professional_skills')) {
        if (mentions(skill) && !f.skills.includes(skill)) { f.skills.push(skill); filled++ }
      }
      if (!trim(f.about)) { f.about = line.slice(0, K.ABOUT_MAX); filled++ }
      Inthes.toast(filled === 0
        ? 'Nothing to fill from that line — try naming your role, years and city'
        : `Filled ${filled} detail${filled === 1 ? '' : 's'} — check each step`, filled === 0)
    },
  }))
})
