/**
 * /account/setup — "Complete your profile" for a new job seeker.
 *
 * The app's own 10-step wizard (OnboardingController): one section per
 * step, in the app's order and with its titles. Each step saves only what
 * was answered (blanks never wipe an earlier answer — the app's onboarding
 * rule); every step can be skipped, and "Do it later" is always there.
 *
 * `?next=` (a site path) is where the visitor was headed — e.g. the job they
 * tried to apply to from an employer account before switching to a new job
 * seeker account. "Do this later" and the finish screen go back there.
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

  Alpine.data('accountSetup', () => K.merge(K.mixin(), {
    STEPS,
    step: 0,
    done: false,
    ready: false,
    busy: false,
    quick: '',
    /** '' (not answered) | 'experienced' | 'fresher' */
    expMode: '',
    finishing: false,
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
      const fresher = this.profFresherBand
      if ((this.me?.experiences || []).length || (this.me?.experience && this.me.experience !== fresher)) this.expMode = 'experienced'
      else if (this.me?.experience && this.me.experience === fresher) this.expMode = 'fresher'
      this.ready = true
    },

    get experiencedBands() { return Inthes.opt('experience_bands').filter((b) => b !== this.profFresherBand) },
    /** Whether the saved profile already answers [key] (OnboardingController.hasAnswer). */
    answered(key) {
      const m = this.me || {}
      const set = (v) => v !== null && v !== undefined && v !== ''
      switch (key) {
        case 'personal': return !!trim(m.name)
        case 'education': return (m.educations || []).length > 0 || !!trim(m.qualification)
        case 'experience': return !!trim(m.experience)
        case 'languages': return (m.languages || []).length > 0
        case 'preferences': return (m.location || []).length > 0 || (m.preferred_roles || []).length > 0 || (m.preferred_job_types || []).length > 0 || !!m.expected_salary
        case 'availability': return [m.currently_employed, m.immediate_joiner, m.willing_to_relocate, m.has_vehicle, m.has_driving_licence, m.notice_period].some(set)
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
      if (key === 'experience' && this.expMode === 'experienced' && this.profExpStarted) return this._profExpProblem()
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
          if (this.expMode === 'fresher') {
            if (me.experience !== this.profFresherBand) await patch({ experience: this.profFresherBand })
          } else if (this.expMode === 'experienced') {
            if (f.experience && f.experience !== me.experience && f.experience !== this.profFresherBand) await patch({ experience: f.experience })
            if (this.profExpStarted && !this._profExpProblem()) {
              await this._profExpWrite()
              this.pf.exp = K.emptyExp()
              await Inthes.loadMe()
              // The band follows the roles entered, as on the profile page.
              await this._profSyncBand()
            }
          }
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
      const problem = this._problem(this._key)
      if (problem) { Inthes.toast(problem, true); return }
      this.busy = true
      try {
        await this._save(this._key)
        if (this.step < STEPS.length - 1) {
          this.step += 1
          window.scrollTo({ top: 0, behavior: 'smooth' })
        } else {
          this.done = true
          window.scrollTo({ top: 0, behavior: 'smooth' })
          this._renderResume()
        }
      } catch (e) {
        // Stay on the step so the answer is not lost.
        Inthes.toast(e.message || "Couldn't save that. Try again, or skip for now.", true)
      }
      this.busy = false
    },

    prev() {
      if (this.step > 0) { this.step -= 1; window.scrollTo({ top: 0, behavior: 'smooth' }) }
    },
    goTo(i) { if (i < this.step && !this.busy) this.step = i },
    skip() {
      if (this.step < STEPS.length - 1) { this.step += 1; window.scrollTo({ top: 0, behavior: 'smooth' }); return }
      this.done = true
      this._renderResume()
    },

    /**
     * The resume is rendered on the way out rather than asked for. Silent: a
     * profile too sparse to render is not an error.
     */
    async _renderResume() {
      if (K.missingForResume(this.me).length || this.me?.resume) return
      this.finishing = true
      try { await Inthes.api('/candidate/profile/resume/generate', { method: 'POST' }); await Inthes.loadMe() } catch (e) { /* the profile can rebuild it */ }
      this.finishing = false
    },

    async leave(to) {
      // Waits for the resume being written, so navigating away doesn't cut it off.
      while (this.finishing) await new Promise((r) => setTimeout(r, 150))
      location.href = to
    },

    /** "Do it later" — saves the step on screen (best effort) and leaves. */
    async later() {
      if (this.busy) return
      this.busy = true
      if (!this.done && !this._problem(this._key)) { try { await this._save(this._key) } catch (e) { /* never trap someone who asked to leave */ } }
      if (!K.missingForResume(this.me).length && !this.me?.resume) {
        try { await Inthes.api('/candidate/profile/resume/generate', { method: 'POST' }) } catch (e) { /* later */ }
      }
      location.href = this.nextUrl || '/jobs'
    },

    // ── step 2 helpers ──────────────────────────────────────────────────
    async addDegree() {
      if (!this.profEduStarted) return
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
      if (!this.profExpStarted) return
      const problem = this._profExpProblem()
      if (problem) { Inthes.toast(problem, true); return }
      await this._profRun(async () => {
        await this._profExpWrite()
        await Inthes.loadMe()
        await this._profSyncBand()
        this.pf.exp = K.emptyExp()
        this.pf.experience = this.me?.experience || ''
        Inthes.toast('Experience added')
      })
    },
    setMode(m) {
      this.expMode = m
      if (m === 'fresher') this.pf.experience = this.profFresherBand
      else if (this.pf.experience === this.profFresherBand) this.pf.experience = ''
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
      if (!this.expMode && f.experience) this.expMode = f.experience === this.profFresherBand ? 'fresher' : 'experienced'
      else if (!this.expMode && trim(f.exp.designation)) this.expMode = 'experienced'
      Inthes.toast(filled === 0
        ? 'Nothing to fill from that line — try naming your role, years and city'
        : `Filled ${filled} detail${filled === 1 ? '' : 's'} — check each step`, filled === 0)
    },
  }))
})
