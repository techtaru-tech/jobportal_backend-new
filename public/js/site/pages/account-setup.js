/**
 * /account/setup — "Complete your profile" for a new job seeker.
 *
 * The app's 10-step wizard as three short website steps: About you,
 * Education & experience, What you're looking for. Each step saves only what
 * was answered (blanks never wipe an earlier answer — the app's onboarding
 * rule), "Do this later" is always there, and the optional extras
 * (availability, about, skills, intro video) are offered at the end as links
 * into the profile page.
 */
document.addEventListener('alpine:init', () => {
  const K = window.ProfileKit
  const { trim, orNull, compact } = K

  const STEPS = [
    { title: 'About you', subtitle: 'Employers see this on every application you send.' },
    { title: 'Education & experience', subtitle: 'Most postings filter on qualification and experience.' },
    { title: "What you're looking for", subtitle: 'We use this to pick the jobs we recommend to you.' },
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
    get progressPct() { return this.done ? 100 : Math.round(((this.step) / STEPS.length) * 100) },

    /** The typed address, or failing that "City, State" — null when neither. */
    get _address() {
      const typed = orNull(this.pf.address)
      if (typed) return typed
      const parts = [this.pf.home_city, this.pf.home_state].filter((s) => trim(s))
      return parts.length ? parts.join(', ') : null
    },

    // ── saving a step (only what was answered) ──────────────────────────
    _problem(i) {
      if (i === 0 && !trim(this.pf.name)) return 'Enter your full name'
      if (i === 1) {
        if (this.profEduStarted) { const p = this._profEduProblem(); if (p) return p }
        if (this.expMode === 'experienced' && this.profExpStarted) { const p = this._profExpProblem(); if (p) return p }
      }
      return null
    },

    async _save(i) {
      const f = this.pf
      const patch = (body) => Inthes.api('/candidate/profile', { method: 'PATCH', body })
      if (i === 0) {
        await patch(compact({
          name: orNull(f.name), email: orNull(f.email), gender: orNull(f.gender), dob: orNull(f.dob), address: this._address,
          home_city: orNull(f.home_city), home_pincode: orNull(f.home_pincode), home_latitude: f.home_latitude, home_longitude: f.home_longitude,
          home_state: orNull(f.home_state),
        }))
      }
      if (i === 1) {
        if (this.profEduStarted && !this._profEduProblem()) {
          await patch({ qualification: f.edu.qualification })
          await this._profEduWrite()
          this.pf.edu = K.emptyEdu()
        }
        if (this.expMode === 'fresher') {
          if (this.me?.experience !== this.profFresherBand) await patch({ experience: this.profFresherBand })
        } else if (this.expMode === 'experienced') {
          if (f.experience && f.experience !== this.me?.experience && f.experience !== this.profFresherBand) await patch({ experience: f.experience })
          if (this.profExpStarted && !this._profExpProblem()) {
            await this._profExpWrite()
            this.pf.exp = K.emptyExp()
            await Inthes.loadMe()
            // The band follows the roles entered, as on the profile page.
            await this._profSyncBand()
          }
        }
      }
      if (i === 2) {
        if (f.location.length) await patch({ location: f.location })
        if (f.roles.length || f.job_types.length || f.shifts.length || f.salary) {
          await Inthes.api('/candidate/profile/preferences', {
            method: 'PATCH',
            body: compact({
              preferred_roles: f.roles.length ? f.roles : null, preferred_job_types: f.job_types.length ? f.job_types : null,
              preferred_shifts: f.shifts.length ? f.shifts : null, expected_salary: f.salary || null,
            }),
          })
        }
        if (f.languages.length) {
          await Inthes.api('/candidate/profile/languages', { method: 'PUT', body: { languages: f.languages, language_levels: this._profLangLevels() } })
        }
        // Skills and a summary only come from "Fill my profile" here.
        const skillsChanged = JSON.stringify(f.skills) !== JSON.stringify(this.me?.skills || [])
        if ((trim(f.about) && trim(f.about) !== trim(this.me?.about)) || skillsChanged) {
          await Inthes.api('/candidate/profile/about', { method: 'PATCH', body: this._profAboutBody() })
        }
      }
      await Inthes.loadMe()
      this.pf.experience = this.me?.experience || this.pf.experience
    },

    async next() {
      if (this.busy) return
      const problem = this._problem(this.step)
      if (problem) { Inthes.toast(problem, true); return }
      this.busy = true
      try {
        await this._save(this.step)
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

    /** "Do this later" — saves the step on screen (best effort) and leaves. */
    async later() {
      if (this.busy) return
      this.busy = true
      if (!this.done && !this._problem(this.step)) { try { await this._save(this.step) } catch (e) { /* never trap someone who asked to leave */ } }
      if (!K.missingForResume(this.me).length && !this.me?.resume) {
        try { await Inthes.api('/candidate/profile/resume/generate', { method: 'POST' }) } catch (e) { /* later */ }
      }
      location.href = '/jobs'
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
