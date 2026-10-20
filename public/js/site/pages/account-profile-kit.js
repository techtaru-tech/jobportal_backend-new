/**
 * The job seeker's profile form model, shared by /account (the profile page)
 * and /account/setup (Complete your profile).
 *
 * Mirrors the Flutter app (lib/app/modules/onboarding, profile and the shared
 * field widgets) and is verified against the API: the same fields, option keys, validation
 * messages, payloads and save rules. Only the flow is the website's own.
 *
 * Two ways of saving, exactly as in the app:
 *  - a profile card writes its whole section (a blank clears the value);
 *  - a setup step writes only what was answered, so a blank never wipes an
 *    earlier answer.
 *
 * `ProfileKit.mixin()` returns the shared state + methods; a page merges it
 * into its own Alpine component with `ProfileKit.merge(base, mixin)` (getters
 * survive, which an object spread would flatten).
 */
;(function () {
  const MAX_HOBBIES = 3
  const ABOUT_MAX = 300
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const ABILITIES = ['Read', 'Write', 'Speak']

  // ── pure helpers (one for one with the app) ─────────────────────────────
  const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const trim = (v) => String(v ?? '').trim()
  const orNull = (v) => (trim(v) === '' ? null : trim(v))
  /** ApiJson.compact — nulls dropped, empty strings kept. */
  const compact = (o) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== null && v !== undefined))

  /** ExperienceEntry.parseMonth — `Mar 2023`, `03/2023`, `2023`; null for Present. */
  function parseMonth(raw) {
    const text = trim(raw).toLowerCase()
    if (!text || text === 'present' || text === 'current') return null
    const ym = text.match(/(19|20)\d{2}/)
    if (!ym) return null
    const y = Number(ym[0])
    const i = MONTHS.findIndex((m) => text.includes(m.toLowerCase()))
    if (i >= 0) return { y, m: i + 1 }
    const rest = text.replace(ym[0], ' ')
    const num = rest.match(/\b(0?[1-9]|1[0-2])\b/)
    if (num) return { y, m: Number(num[0]) }
    return { y, m: 7 }
  }
  const nowMonth = () => { const d = new Date(); return { y: d.getFullYear(), m: d.getMonth() + 1 } }
  const monthIndex = (d) => d.y * 12 + d.m

  /** ExperienceEntry.duration — inclusive of both months, "1 yr 9 mos". */
  function durationOf(e) {
    const start = parseMonth(e.start_date)
    if (!start) return ''
    const end = e.currently_working ? nowMonth() : parseMonth(e.end_date)
    if (!end) return ''
    const months = monthIndex(end) - monthIndex(start) + 1
    if (months < 1) return ''
    const years = Math.floor(months / 12)
    const rest = months % 12
    return [years > 0 ? (years === 1 ? '1 yr' : `${years} yrs`) : '', rest > 0 ? (rest === 1 ? '1 mo' : `${rest} mos`) : ''].filter(Boolean).join(' ')
  }

  /** ExperienceBand.totalYears — overlapping roles counted once. */
  function totalYears(entries) {
    const spans = []
    for (const e of entries) {
      const start = parseMonth(e.start_date)
      if (!start) continue
      const end = e.currently_working ? nowMonth() : (parseMonth(e.end_date) || nowMonth())
      if (monthIndex(end) < monthIndex(start)) continue
      spans.push([monthIndex(start), monthIndex(end)])
    }
    if (!spans.length) return null
    spans.sort((a, b) => a[0] - b[0])
    const merged = []
    for (const [s, e] of spans) {
      const last = merged[merged.length - 1]
      if (last && s <= last[1]) last[1] = Math.max(last[1], e)
      else merged.push([s, e])
    }
    return merged.reduce((sum, [s, e]) => sum + e - s + 1, 0) / 12
  }
  function totalLabel(entries) {
    const years = totalYears(entries)
    if (years == null) return ''
    const months = Math.round(years * 12)
    if (months < 12) return months === 1 ? '1 mo' : `${months} mos`
    const r = Math.round(years * 10) / 10
    return (Number.isInteger(r) ? String(r) : r.toFixed(1)) + ' yrs'
  }

  /** ExperienceBand.bandFor — the band [years] falls in, parsed from the band text. */
  function bandFor(years, bands) {
    if (!bands.length) return null
    const rangeOf = (band) => {
      const n = (band.match(/\d+/g) || []).map(Number)
      if (!n.length) return { from: 0, to: 0 }
      if (n.length === 1) return band.includes('+') ? { from: n[0], to: Infinity } : { from: n[0], to: n[0] }
      return { from: n[0], to: n[1] }
    }
    if (years < 1 / 12) return bands.find((b) => rangeOf(b).to === 0) || bands[0]
    for (const b of bands) {
      const r = rangeOf(b)
      if (r.to === 0) continue
      if (years >= r.from && years < r.to) return b
    }
    return [...bands].reverse().find((b) => rangeOf(b).to !== 0) || bands[bands.length - 1]
  }

  /** TeamSummary — the one About line the team answer becomes. */
  const TEAM_PREFIX = 'Managed a team of'
  const teamLine = (size, org) => `${TEAM_PREFIX} ${size} ${size === 1 ? 'person' : 'people'}${trim(org) ? ' at ' + trim(org) : ''}.`
  const isTeamLine = (l) => l.trimStart().startsWith(TEAM_PREFIX)
  function teamApply(about, managed, size, org) {
    const lines = about ? about.split('\n') : []
    const at = lines.findIndex(isTeamLine)
    if (managed !== true) {
      if (at < 0) return about
      return lines.filter((l) => !isTeamLine(l)).join('\n').trimEnd()
    }
    const next = teamLine(size, org)
    if (at < 0) {
      const body = (about || '').trimEnd()
      return body ? `${body}\n${next}` : next
    }
    return lines.map((l, i) => (i === at ? next : l)).filter((l, i) => i === at || !isTeamLine(l)).join('\n')
  }
  function teamParse(about) {
    const m = String(about || '').match(/^\s*Managed a team of (\d+) (?:people|person)(?: at (.+?))?\.?\s*$/m)
    if (!m) return null
    return { size: Math.min(100, Math.max(1, Number(m[1]) || 1)), org: trim(m[2]) }
  }

  /** LanguageAbilityChips' compose — three ticks to one of the server's seven values. */
  function composeAbility(parts) {
    const o = ABILITIES.filter((a) => parts.includes(a))
    if (o.length === 0) return ''
    if (o.length === 1) return o[0]
    if (o.length === 2) return `${o[0]} & ${o[1]}`
    return `${o[0]}, ${o[1]} & ${o[2]}`
  }

  /** YearRange — "2018 – 2022", or one year when they match. */
  function yearParse(year) {
    const f = String(year || '').match(/\d{4}/g) || []
    if (!f.length) return ['', '']
    return f.length === 1 ? [f[0], f[0]] : [f[0], f[1]]
  }
  function yearCompose(start, end) {
    if (!start) return end || ''
    if (!end || end === start) return start
    return `${start} – ${end}`
  }

  // ProfileWriter — "Suggest for me" and the intro-video script, written only
  // from what the profile already says.
  const pwRole = (p) => (p.experiences?.[0]?.designation?.trim()) || (p.preferred_roles?.[0]) || ''
  const pwYears = (p) => {
    const band = trim(p.experience)
    if (!band) return ''
    return band.toLowerCase().includes('fresher') ? 'a fresher' : `${band} of experience`
  }
  const pwPlace = (p) => trim(p.home_city) || (p.location?.[0] || '')
  const pwList = (items, max = 3) => {
    const picked = (items || []).filter((s) => trim(s)).slice(0, max)
    if (!picked.length) return ''
    if (picked.length === 1) return picked[0]
    return picked.slice(0, -1).join(', ') + ' and ' + picked[picked.length - 1]
  }
  function suggestAbout(p) {
    const role = pwRole(p)
    const years = pwYears(p)
    const first = [role, years ? (role ? `with ${years}` : years) : ''].filter(Boolean).join(' ')
    const sentences = []
    if (first || p.qualification) sentences.push([first, p.qualification].filter(Boolean).join(', '))
    if (pwList(p.skills)) sentences.push(`Skilled in ${pwList(p.skills)}`)
    sentences.push(pwPlace(p) ? `Based in ${pwPlace(p)}, looking for a role where I can keep growing` : 'Looking for a role where I can keep growing')
    return sentences.join('. ') + '.'
  }
  function introScript(p) {
    const name = trim(p.name)
    const role = pwRole(p)
    const parts = [name ? `Hello, I am ${name}.` : 'Hello.']
    if (role || p.qualification) {
      parts.push([role ? `I work as a ${role}` : '', p.qualification ? `${role ? 'with' : 'I have'} ${p.qualification}` : ''].filter(Boolean).join(' ') + (pwYears(p) ? `, ${pwYears(p)}.` : '.'))
    }
    if (pwList(p.skills)) parts.push(`I am good at ${pwList(p.skills)}.`)
    if (pwList(p.languages)) parts.push(`I speak ${pwList(p.languages)}.`)
    parts.push('I am looking for a role where I can do my best work. Thank you.')
    return parts.join(' ')
  }

  /** CandidateProfile.sectionParts — what each profile section is made of. */
  function sectionParts(p) {
    const has = (v) => Array.isArray(v) ? v.length > 0 : !!trim(v)
    const set = (v) => v !== null && v !== undefined
    return {
      personal: { name: has(p.name), email: has(p.email), gender: has(p.gender), dob: has(p.dob), address: has(p.address), currentLocation: set(p.home_latitude) && set(p.home_longitude) },
      qualification: { entry: has(p.educations), qualification: has(p.qualification) },
      experience: { entry: has(p.experiences), band: has(p.experience) },
      location: { cities: has(p.location), roles: has(p.preferred_roles), jobTypes: has(p.preferred_job_types), shifts: has(p.preferred_shifts), expectedSalary: has(p.expected_salary) },
      languages: { languages: has(p.languages) },
      resume: { resume: has(p.resume) },
      photo: { photo: !!p.photo },
      about: { about: has(p.about) },
      introVideo: { introVideo: has(p.intro_video_url) },
      availability: { currentlyEmployed: set(p.currently_employed), immediateJoiner: set(p.immediate_joiner), willingToRelocate: set(p.willing_to_relocate), hasVehicle: set(p.has_vehicle), hasDrivingLicence: set(p.has_driving_licence) },
      skills: { skills: has(p.skills), hobbies: has(p.hobbies) },
    }
  }
  function progressFor(p, section) {
    const parts = Object.values(sectionParts(p || {})[section] || {})
    const filled = parts.filter(Boolean).length
    const total = parts.length
    const complete = total > 0 && filled === total
    return { complete, filled, total, status: complete ? 'Complete' : (filled === 0 ? 'Missing' : `${filled} of ${total}`) }
  }

  /** CandidateProfile.missingForResume — what a resume is not worth rendering without. */
  function missingForResume(p) {
    if (!p) return []
    const out = []
    if (!trim(p.name)) out.push('your name')
    if (!p.qualification && !(p.educations || []).length) out.push('your qualification')
    if (!p.experience && !(p.experiences || []).length) out.push('your experience (pick Fresher if you have none yet)')
    return out
  }

  const strengthLabel = (n) => n <= 40 ? 'Getting started' : n <= 60 ? 'Fair profile' : n <= 80 ? 'Good profile' : n <= 95 ? 'Strong profile' : 'Excellent profile'
  const hiringLabel = (n) => n <= 40 ? 'Hiring chance: low' : n <= 60 ? 'Hiring chance: fair' : n <= 80 ? 'Hiring chance: good' : 'Hiring chance: high'

  const isoToday = () => { const d = new Date(); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10) }
  const isoYearsAgo = (n) => { const d = new Date(); d.setFullYear(d.getFullYear() - n); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10) }
  function ageFrom(iso) {
    const m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (!m) return null
    const now = new Date()
    let y = now.getFullYear() - Number(m[1])
    if (now.getMonth() + 1 < Number(m[2]) || (now.getMonth() + 1 === Number(m[2]) && now.getDate() < Number(m[3]))) y--
    return y < 0 ? null : y
  }

  const emptyEdu = () => ({ id: '', qualification: '', specialization: '', institute: '', start: '', end: '', percentage: '', course_type: '' })
  const emptyExp = () => ({ id: '', designation: '', organization: '', department: '', cities: [], sm: '', sy: '', em: '', ey: '', currently_working: false, description: '' })

  // The intro video picked for review. A File inside Alpine's reactive proxy
  // breaks the upload, so it lives here; the component only holds its URL.
  let videoFile = null

  const opt = (k) => window.Inthes.opt(k)
  const api = (...a) => window.Inthes.api(...a)
  const toast = (m, e) => window.Inthes.toast(m, e)

  /** Copies properties with their getters intact. */
  function merge(base, extra) {
    Object.defineProperties(base, Object.getOwnPropertyDescriptors(extra))
    return base
  }

  function mixin() {
    return {
      /** The one form model behind every card editor and setup step. */
      pf: { edu: emptyEdu(), exp: emptyExp(), languages: [], levels: {}, location: [], roles: [], job_types: [], shifts: [], org_types: [], hobbies: [], skills: [] },
      profBusy: false,
      profPhotoBusy: false,
      profVideoBusy: false,
      profVideoDraft: null,
      profVideoBroken: false,
      profLocating: false,
      _profTeamTimer: null,
      /** Setup collects the team answer into the About text; the profile saves it at once. */
      profTeamDeferred: false,

      PROF_MAX_HOBBIES: MAX_HOBBIES,
      PROF_ABOUT_MAX: ABOUT_MAX,
      PROF_MONTHS: MONTHS,
      PROF_ABILITIES: ABILITIES,

      get me() { return window.Alpine.store('auth').me },

      // ── the form model ──────────────────────────────────────────────────
      /** Prefills every field from the saved profile — an answered profile is never shown blank. */
      profFill() {
        const p = this.me || {}
        const team = teamParse(p.about)
        this.pf = {
          name: p.name || '', email: p.email || '', gender: p.gender || '', dob: p.dob || '',
          address: p.address || '', home_state: p.home_state || '', home_city: p.home_city || '', home_pincode: p.home_pincode || '',
          home_latitude: p.home_latitude ?? null, home_longitude: p.home_longitude ?? null,
          marital_status: p.marital_status || '', father_name: p.father_name || '', mother_name: p.mother_name || '',
          native_place: p.native_place || '', nationality: p.nationality || '',
          alternate_phone: p.alternate_phone || '', whatsapp_number: p.whatsapp_number || '',
          experience: p.experience || '', managed_team: p.managed_team ?? null,
          team_org: team?.org || '', team_size: team?.size || 1,
          edu: emptyEdu(), exp: emptyExp(),
          languages: [...(p.languages || [])], levels: { ...(p.language_levels || {}) },
          location: [...(p.location || [])], roles: [...(p.preferred_roles || [])], job_types: [...(p.preferred_job_types || [])],
          shifts: [...(p.preferred_shifts || [])], org_types: [...(p.preferred_organisation_types || [])], salary: p.expected_salary || '',
          currently_employed: p.currently_employed ?? null, notice_period: p.notice_period || '', last_working_date: p.last_working_date || '',
          immediate_joiner: p.immediate_joiner ?? null, earliest_joining_date: p.earliest_joining_date || '',
          willing_to_relocate: p.willing_to_relocate ?? null, has_vehicle: p.has_vehicle ?? null, has_driving_licence: p.has_driving_licence ?? null,
          about: p.about || '', hobbies: [...(p.hobbies || [])].slice(0, MAX_HOBBIES), skills: [...(p.skills || [])],
        }
        this.profDiscardVideo()
      },

      async _profRun(fn) {
        if (this.profBusy) return false
        this.profBusy = true
        try {
          await fn()
          return true
        } catch (e) {
          toast(e.message, true)
          return false
        } finally {
          this.profBusy = false
        }
      },

      /** After a section save. Pages override to close their editor. */
      async _profDone(message) {
        await window.Inthes.loadMe()
        toast(message)
      },

      // ── display helpers ─────────────────────────────────────────────────
      get profStrength() { return Number(this.me?.profile_strength ?? 0) },
      get profStrengthLabel() { return strengthLabel(this.profStrength) },
      get profHiringLabel() { return hiringLabel(this.profStrength) },
      profInitials(name) {
        const parts = trim(name).split(/\s+/).filter(Boolean)
        if (!parts.length) return ''
        return parts.length === 1 ? parts[0][0].toUpperCase() : (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
      },
      profProgress(section) { return progressFor(this.me, section) },
      profDuration: durationOf,
      profPeriod(e) {
        if (!e.start_date && !e.end_date) return ''
        return e.end_date ? `${e.start_date} – ${e.end_date}` : e.start_date
      },
      /** Latest first, as the app lists them. */
      get profEducations() {
        const last = (e) => { const y = String(e.year || '').match(/\d{4}/g); return y ? Number(y[y.length - 1]) : -1 }
        return (this.me?.educations || []).map((e, i) => [e, i]).sort((a, b) => (last(b[0]) - last(a[0])) || (a[1] - b[1])).map(([e]) => e)
      },
      get profExperiences() {
        const rank = (e) => e.currently_working ? Infinity : (() => { const d = parseMonth(e.end_date) || parseMonth(e.start_date); return d ? monthIndex(d) : -1 })()
        return (this.me?.experiences || []).map((e, i) => [e, i]).sort((a, b) => {
          const ra = rank(a[0]); const rb = rank(b[0])
          return ra === rb ? a[1] - b[1] : (rb > ra ? 1 : -1)
        }).map(([e]) => e)
      },
      get profTotalExperience() { return totalLabel(this.me?.experiences || []) },
      get profMissingForResume() { return missingForResume(this.me) },

      // ── photo (ProfilePhotoMixin) ───────────────────────────────────────
      profPickPhoto(input) {
        const file = input.files && input.files[0]
        input.value = ''
        if (!file) return
        if (!/^image\/(jpe?g|png)$/i.test(file.type) && !/\.(jpe?g|png)$/i.test(file.name)) { toast('Upload a JPG or PNG image.', true); return }
        if (file.size > 3 * 1024 * 1024) { toast('Your photo must be smaller than 3 MB.', true); return }
        this.profUploadPhoto(file)
      },
      async profUploadPhoto(file) {
        if (this.profPhotoBusy) return
        const replacing = !!this.me?.photo
        this.profPhotoBusy = true
        try {
          await window.Inthes.upload('/candidate/profile/photo', file)
          await window.Inthes.loadMe()
          toast(replacing ? 'Photo updated' : 'Photo added')
        } catch (e) { toast(e.message, true) }
        this.profPhotoBusy = false
      },
      async profDeletePhoto() {
        if (this.profPhotoBusy || !this.me?.photo) return
        if (!(await window.Inthes.confirm({ title: 'Remove photo?', message: 'Recruiters will see your initials instead.', ok: 'Remove', danger: true }))) return
        this.profPhotoBusy = true
        try {
          await api('/candidate/profile/photo', { method: 'DELETE' })
          await window.Inthes.loadMe()
          toast('Photo removed')
        } catch (e) { toast(e.message, true) }
        this.profPhotoBusy = false
      },

      // ── personal ────────────────────────────────────────────────────────
      get profAge() { return ageFrom(this.pf.dob) },
      profAgeOf: ageFrom,
      get profDobMin() { return isoYearsAgo(80) },
      get profDobMax() { return isoYearsAgo(16) },
      get profToday() { return isoToday() },
      get profHasPin() { return this.pf.home_latitude != null && this.pf.home_longitude != null },
      profPickState() {
        // "Jaipur, Maharashtra" is not a place — a city from the old state goes.
        if (!window.Inthes.citiesOf(this.pf.home_state).includes(this.pf.home_city)) this.pf.home_city = ''
      },
      /** Typing by hand drops a pin taken earlier — it no longer describes the text. */
      profAddressTyped() {
        if (!this.profHasPin) return
        this.pf.home_latitude = null
        this.pf.home_longitude = null
        this.pf.home_pincode = ''
      },
      /** "Use current location" — the coordinates sort the job feed by distance. */
      profUseLocation() {
        if (this.profLocating) return
        if (!navigator.geolocation) { toast('Could not fetch current location', true); return }
        this.profLocating = true
        navigator.geolocation.getCurrentPosition(
          (pos) => {
            this.pf.home_latitude = Math.round(pos.coords.latitude * 1e6) / 1e6
            this.pf.home_longitude = Math.round(pos.coords.longitude * 1e6) / 1e6
            this.profLocating = false
            toast('Location pinned')
          },
          (err) => {
            this.profLocating = false
            toast(err.code === 1 ? 'Location permission denied' : 'Could not fetch current location', true)
          },
          { enableHighAccuracy: true, timeout: 15000 },
        )
      },
      _profPhoneProblem() {
        const bad = (v) => trim(v) !== '' && !/^\d{10}$/.test(trim(v))
        if (bad(this.pf.alternate_phone)) return 'Alternate mobile number must be 10 digits'
        if (bad(this.pf.whatsapp_number)) return 'WhatsApp number must be 10 digits'
        return null
      },
      async profSavePersonal() {
        const f = this.pf
        if (!trim(f.name)) { toast('Enter your full name', true); return }
        const problem = this._profPhoneProblem()
        if (problem) { toast(problem, true); return }
        await this._profRun(async () => {
          await api('/candidate/profile', {
            method: 'PATCH',
            body: compact({
              name: trim(f.name), email: trim(f.email), gender: f.gender, dob: f.dob, address: trim(f.address),
              marital_status: f.marital_status, father_name: trim(f.father_name), mother_name: trim(f.mother_name),
              home_city: f.home_city, home_pincode: f.home_pincode, home_latitude: f.home_latitude, home_longitude: f.home_longitude,
              home_state: f.home_state, native_place: trim(f.native_place), nationality: trim(f.nationality),
              alternate_phone: trim(f.alternate_phone), whatsapp_number: trim(f.whatsapp_number),
            }),
          })
          await this._profDone('Personal information saved')
        })
      },

      // ── education ───────────────────────────────────────────────────────
      get profYearsDesc() { return opt('passing_years') },
      profEndYears(start) {
        const years = opt('passing_years')
        return start ? years.filter((y) => Number(y) >= Number(start)) : years
      },
      profEduStart(v) {
        // A passing year before the new joining year no longer fits.
        if (v && this.pf.edu.end && Number(v) > Number(this.pf.edu.end)) this.pf.edu.end = ''
      },
      _profEduProblem() {
        if (!this.pf.edu.qualification) return 'Select your highest qualification'
        if (!trim(yearCompose(this.pf.edu.start, this.pf.edu.end))) return 'Select the year of passing'
        const pct = trim(this.pf.edu.percentage)
        if (pct !== '' && !(Number(pct) >= 0 && Number(pct) <= 100)) return 'Percentage / CGPA must be between 0 and 100'
        return null
      },
      get profEduCanSave() { return !this._profEduProblem() },
      get profEduStarted() {
        const e = this.pf.edu || {}
        return !!(e.qualification || e.specialization || trim(e.institute) || e.start || e.end || e.course_type || trim(e.percentage))
      },
      profEduEdit(e) {
        const [start, end] = yearParse(e.year)
        const pct = e.percentage == null ? '' : String(Number(e.percentage))
        this.pf.edu = { id: e.id, qualification: e.qualification || '', specialization: e.specialization || '', institute: e.institute || '', start, end, percentage: pct, course_type: e.course_type || '' }
      },
      _profEduBody() {
        const e = this.pf.edu
        const pct = trim(e.percentage) === '' ? null : Number(e.percentage)
        return {
          qualification: e.qualification,
          specialization: orNull(e.specialization),
          institute: orNull(e.institute),
          year: orNull(yearCompose(e.start, e.end)),
          course_type: orNull(e.course_type),
          percentage: Number.isFinite(pct) ? pct : null,
        }
      },
      async _profEduWrite() {
        const id = this.pf.edu.id
        await api('/candidate/profile/educations' + (id ? '/' + encodeURIComponent(id) : ''), { method: id ? 'PATCH' : 'POST', body: this._profEduBody() })
      },
      async profEduRemove(e, ask = true) {
        if (ask && !(await window.Inthes.confirm({ title: 'Remove education?', message: 'This entry will be removed from your profile.', ok: 'Remove', danger: true }))) return
        await this._profRun(async () => {
          await api('/candidate/profile/educations/' + encodeURIComponent(e.id), { method: 'DELETE' })
          await window.Inthes.loadMe()
          if (this.pf.edu.id === e.id) this.pf.edu = emptyEdu()
          toast('Education removed')
        })
      },

      // ── experience ──────────────────────────────────────────────────────
      get profExpYears() {
        const now = new Date().getFullYear()
        return opt('passing_years').filter((y) => Number(y) <= now)
      },
      _profExpStart() { const e = this.pf.exp; return e.sm && e.sy ? `${e.sm} ${e.sy}` : '' },
      _profExpEnd() { const e = this.pf.exp; return e.em && e.ey ? `${e.em} ${e.ey}` : '' },
      get profExpDraft() {
        const e = this.pf.exp || emptyExp()
        return {
          id: e.id, designation: trim(e.designation), organization: trim(e.organization), department: trim(e.department),
          cities: [...(e.cities || [])], start_date: this._profExpStart(),
          end_date: e.currently_working ? 'Present' : this._profExpEnd(),
          currently_working: !!e.currently_working, description: trim(e.description),
        }
      },
      get profExpDuration() { return durationOf(this.profExpDraft) },
      _profExpProblem() {
        const e = this.pf.exp
        if (!trim(e.organization)) return 'Enter the company / hospital / laboratory'
        if (!trim(e.designation)) return 'Select or add your designation'
        if (!this._profExpStart()) return 'Select the joining month and year'
        // No role ends before it began, nor in a month still ahead.
        const s = parseMonth(this._profExpStart())
        const en = parseMonth(this._profExpEnd())
        if (!e.currently_working && en && monthIndex(en) < monthIndex(s)) return 'The last working date cannot be before the joining date'
        if (monthIndex(s) > monthIndex(nowMonth()) || (en && monthIndex(en) > monthIndex(nowMonth()))) return 'Pick a month that has already started'
        return null
      },
      get profExpCanSave() { return !this._profExpProblem() },
      get profExpStarted() {
        const e = this.pf.exp || {}
        return !!(trim(e.designation) || trim(e.organization) || trim(e.department) || trim(e.description) || this._profExpStart())
      },
      profExpEdit(x) {
        const s = parseMonth(x.start_date)
        const en = x.currently_working ? null : parseMonth(x.end_date)
        this.pf.exp = {
          id: x.id, designation: x.designation || '', organization: x.organization || '', department: x.department || '',
          cities: [...(x.cities || [])], sm: s ? MONTHS[s.m - 1] : '', sy: s ? String(s.y) : '',
          em: en ? MONTHS[en.m - 1] : '', ey: en ? String(en.y) : '', currently_working: !!x.currently_working, description: x.description || '',
        }
      },
      _profExpBody() {
        const d = this.profExpDraft
        return {
          designation: d.designation, organization: d.organization, department: orNull(d.department), cities: d.cities,
          start_date: orNull(d.start_date), end_date: orNull(d.end_date), currently_working: d.currently_working, description: orNull(d.description),
        }
      },
      async _profExpWrite() {
        const id = this.pf.exp.id
        await api('/candidate/profile/experiences' + (id ? '/' + encodeURIComponent(id) : ''), { method: id ? 'PATCH' : 'POST', body: this._profExpBody() })
      },
      /** Keeps the experience band in step with the roles entered — derived from the dates, never defaulted. */
      async _profSyncBand() {
        const band = (() => { const y = totalYears(this.me?.experiences || []); return y == null ? null : bandFor(y, opt('experience_bands')) })()
        if (!band || band === this.me?.experience) return
        await api('/candidate/profile', { method: 'PATCH', body: { experience: band } })
        await window.Inthes.loadMe()
      },
      async profExpRemove(x, ask = true) {
        if (ask && !(await window.Inthes.confirm({ title: 'Remove experience?', message: 'This entry will be removed from your profile.', ok: 'Remove', danger: true }))) return
        await this._profRun(async () => {
          await api('/candidate/profile/experiences/' + encodeURIComponent(x.id), { method: 'DELETE' })
          await window.Inthes.loadMe()
          await this._profSyncBand()
          if (this.pf.exp.id === x.id) this.pf.exp = emptyExp()
          toast('Experience removed')
        })
      },
      get profFresherBand() { return bandFor(0, opt('experience_bands')) },
      get profIsFresher() {
        const f = this.profFresherBand
        return !(this.me?.experiences || []).length && !!f && this.me?.experience === f
      },
      /** "I'm a fresher" — ticking it sets the fresher band; unticking is not an answer. */
      async profSetFresher(value) {
        const f = this.profFresherBand
        if (!value || !f) return
        await this._profRun(async () => {
          if (this.me?.experience !== f) await api('/candidate/profile', { method: 'PATCH', body: { experience: f } })
          await window.Inthes.loadMe()
          this.pf.experience = f
          toast('Marked as a fresher')
        })
      },

      // ── team — "Have you managed a team?" (TeamFields + TeamSummary) ────
      get profTeamOrgs() {
        const orgs = (this.me?.experiences || []).map((e) => trim(e.organization))
        if (this.profTeamDeferred) orgs.push(trim(this.pf.exp?.organization))
        return [...new Set(orgs.filter(Boolean))]
      },
      get profTeamPreview() { return teamLine(Number(this.pf.team_size) || 1, this.pf.team_org) },
      async profPickTeam(value) {
        if (this.profTeamDeferred) {
          this.pf.managed_team = this.pf.managed_team === value ? null : value
          if (this.pf.managed_team === true && !this.pf.team_org) this.pf.team_org = this.profTeamOrgs[0] || ''
          this._profTeamToAbout()
          return
        }
        await this._profRun(async () => {
          if (value !== this.me?.managed_team) {
            await api('/candidate/profile', { method: 'PATCH', body: { managed_team: value } })
            await window.Inthes.loadMe()
          }
          this.pf.managed_team = value
          if (value && !this.pf.team_org) this.pf.team_org = this.profTeamOrgs[0] || ''
          clearTimeout(this._profTeamTimer)
          await this._profSaveTeamSummary()
          toast('Saved')
        })
      },
      profTeamChanged() {
        this.pf.team_size = Math.min(100, Math.max(1, Math.round(Number(this.pf.team_size) || 1)))
        if (this.profTeamDeferred) { this._profTeamToAbout(); return }
        // A few taps on + would be a few About saves; the last one matters.
        clearTimeout(this._profTeamTimer)
        this._profTeamTimer = setTimeout(() => this._profSaveTeamSummary().catch((e) => toast(e.message, true)), 800)
      },
      profTeamStep(by) {
        this.pf.team_size = Math.min(100, Math.max(1, (Number(this.pf.team_size) || 1) + by))
        this.profTeamChanged()
      },
      _profTeamToAbout() {
        const next = teamApply(this.pf.about, this.pf.managed_team, Number(this.pf.team_size) || 1, this.pf.team_org)
        if (next !== this.pf.about) this.pf.about = next
      },
      async _profSaveTeamSummary() {
        const about = this.me?.about || ''
        const next = teamApply(about, this.me?.managed_team ?? null, Number(this.pf.team_size) || 1, this.pf.team_org)
        if (next === about) return
        // Hobbies and skills left out of the call, so they stay as they are.
        await api('/candidate/profile/about', { method: 'PATCH', body: { about: next || null } })
        await window.Inthes.loadMe()
        this.pf.about = this.me?.about || ''
      },

      // ── languages ───────────────────────────────────────────────────────
      profLangHas(lang, ability) { return String(this.pf.levels?.[lang] || '').includes(ability) },
      profLangToggle(lang, ability) {
        const parts = ABILITIES.filter((a) => this.profLangHas(lang, a))
        const next = parts.includes(ability) ? parts.filter((a) => a !== ability) : [...parts, ability]
        const value = composeAbility(next)
        const levels = { ...this.pf.levels }
        if (value) levels[lang] = value
        else delete levels[lang]
        this.pf.levels = levels
      },
      profLangRemove(lang) { this.pf.languages = this.pf.languages.filter((l) => l !== lang) },
      /** Blanks and orphans dropped — both are validated against closed sets. */
      _profLangLevels() {
        return Object.fromEntries(Object.entries(this.pf.levels || {}).filter(([l, v]) => this.pf.languages.includes(l) && trim(v)))
      },
      async profSaveLanguages() {
        await this._profRun(async () => {
          await api('/candidate/profile/languages', { method: 'PUT', body: { languages: this.pf.languages, language_levels: this._profLangLevels() } })
          await this._profDone('Languages saved')
        })
      },

      // ── preferences ─────────────────────────────────────────────────────
      /** The salary list, plus a value already on record that the list no longer has. */
      get profSalaryOptions() {
        const list = opt('salary_filters')
        return this.pf.salary && !list.includes(this.pf.salary) ? [...list, this.pf.salary] : list
      },
      async profSavePreferences() {
        const f = this.pf
        await this._profRun(async () => {
          await api('/candidate/profile', { method: 'PATCH', body: { location: f.location } })
          await api('/candidate/profile/preferences', {
            method: 'PATCH',
            body: compact({ preferred_roles: f.roles, preferred_job_types: f.job_types, preferred_shifts: f.shifts, preferred_organisation_types: f.org_types, expected_salary: f.salary }),
          })
          await this._profDone('Job preferences saved')
        })
      },

      // ── availability — tapping the picked answer clears it back to unanswered
      profTri(key, value) { this.pf[key] = this.pf[key] === value ? null : value },
      _profAvailBody() {
        const f = this.pf
        return compact({
          currently_employed: f.currently_employed, notice_period: orNull(f.notice_period), last_working_date: orNull(f.last_working_date),
          immediate_joiner: f.immediate_joiner, earliest_joining_date: orNull(f.earliest_joining_date),
          willing_to_relocate: f.willing_to_relocate, has_vehicle: f.has_vehicle, has_driving_licence: f.has_driving_licence,
        })
      },
      async profSaveAvailability() {
        await this._profRun(async () => {
          await api('/candidate/profile/availability', { method: 'PATCH', body: this._profAvailBody() })
          await this._profDone('Availability saved')
        })
      },

      // ── about, skills & hobbies ─────────────────────────────────────────
      profSuggestAbout() { this.pf.about = suggestAbout(this.me || {}).slice(0, ABOUT_MAX) },
      profToggleHobby(h) {
        const i = this.pf.hobbies.indexOf(h)
        if (i >= 0) { this.pf.hobbies.splice(i, 1); return }
        if (this.pf.hobbies.length >= MAX_HOBBIES) { toast(`Pick up to ${MAX_HOBBIES} hobbies`, true); return }
        this.pf.hobbies.push(h)
      },
      profToggleSkill(s) {
        const i = this.pf.skills.findIndex((x) => x.toLowerCase() === s.toLowerCase())
        if (i >= 0) this.pf.skills.splice(i, 1)
        else this.pf.skills.push(s)
      },
      profHasSkill(s) { return this.pf.skills.some((x) => x.toLowerCase() === s.toLowerCase()) },
      profAddOwn(kind, value) {
        const v = trim(value)
        if (!v) return
        if (kind === 'hobby') { if (!this.pf.hobbies.some((h) => h.toLowerCase() === v.toLowerCase())) this.profToggleHobby(v) }
        else if (!this.pf.skills.some((s) => s.toLowerCase() === v.toLowerCase())) this.pf.skills.push(v)
      },
      profChipList(options, selected) {
        const extra = (selected || []).filter((s) => !options.some((o) => o.toLowerCase() === s.toLowerCase()))
        return [...options, ...extra]
      },
      _profAboutBody() {
        const about = trim(this.pf.about)
        return { about: about || null, hobbies: this.pf.hobbies, skills: this.pf.skills }
      },
      async profSaveAbout() {
        await this._profRun(async () => {
          await api('/candidate/profile/about', { method: 'PATCH', body: this._profAboutBody() })
          await this._profDone('About me saved')
        })
      },

      // ── intro video — picked, measured, reviewed, then sent ─────────────
      get profIntroScript() { return introScript(this.me || {}) },
      async profPickVideo(input) {
        const file = input.files && input.files[0]
        input.value = ''
        if (!file) return
        if (!/\.(mp4|mov)$/i.test(file.name) && !/^video\/(mp4|quicktime)$/i.test(file.type)) { toast('Upload an MP4 or MOV video.', true); return }
        if (file.size > 50 * 1024 * 1024) { toast('Your intro video must be smaller than 50 MB.', true); return }
        const url = URL.createObjectURL(file)
        const seconds = await new Promise((resolve) => {
          const v = document.createElement('video')
          v.preload = 'metadata'
          v.onloadedmetadata = () => resolve(v.duration)
          // Unreadable here is not "too long" — the server re-measures.
          v.onerror = () => resolve(null)
          v.src = url
        })
        if (seconds != null && Number.isFinite(seconds) && seconds > 61) {
          URL.revokeObjectURL(url)
          toast('Keep it to 1 minute or less — trim the video and try again', true)
          return
        }
        this.profDiscardVideo()
        videoFile = file
        this.profVideoDraft = { url, name: file.name }
      },
      profDiscardVideo() {
        if (this.profVideoDraft) URL.revokeObjectURL(this.profVideoDraft.url)
        this.profVideoDraft = null
        videoFile = null
      },
      async profUploadVideo() {
        if (!this.profVideoDraft || !videoFile || this.profVideoBusy) return
        this.profVideoBusy = true
        try {
          await window.Inthes.upload('/candidate/profile/intro-video', videoFile)
          await window.Inthes.loadMe()
          this.profDiscardVideo()
          this.profVideoBroken = false
          toast('Intro video saved')
        } catch (e) { toast(e.message, true) }
        this.profVideoBusy = false
      },
      async profRemoveVideo() {
        if (this.profVideoBusy) return
        if (!(await window.Inthes.confirm({ title: 'Remove intro video?', message: 'Recruiters will no longer see it on your applications.', ok: 'Remove', danger: true }))) return
        this.profVideoBusy = true
        try {
          await api('/candidate/profile/intro-video', { method: 'DELETE' })
          await window.Inthes.loadMe()
          toast('Intro video removed')
        } catch (e) { toast(e.message, true) }
        this.profVideoBusy = false
      },

      // ── resume ──────────────────────────────────────────────────────────
      profResumeBusy: false,
      /** Renders the resume from the profile (the server keeps it current after that). */
      async profGenerateResume() {
        if (this.profResumeBusy || this.profMissingForResume.length) return
        this.profResumeBusy = true
        try {
          await api('/candidate/profile/resume/generate', { method: 'POST' })
          await window.Inthes.loadMe()
          toast('Resume created from your profile.')
        } catch (e) { toast(e.message, true) }
        this.profResumeBusy = false
      },
    }
  }

  window.ProfileKit = {
    mixin, merge,
    emptyEdu, emptyExp,
    trim, orNull, compact, bandFor, totalYears, teamApply, missingForResume, progressFor, strengthLabel, esc,
    MAX_HOBBIES, ABOUT_MAX,
  }
})()
