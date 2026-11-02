/**
 * The website's signed-in layer, shared by every page.
 *
 * The site is server-rendered (indexable) and a visitor can do everything
 * the app does without leaving it: sign in from a popup, apply, keep a
 * profile, post jobs, chat. Everything signed-in goes through `/api/v1` — the
 * same endpoints the app calls — with a Sanctum bearer token kept in
 * localStorage, so every rule (Smart Apply's gate, ownership, approvals,
 * plans) lives in exactly one place.
 *
 * What lives here
 * ---------------
 *  window.Inthes            — api(), upload(), qs(), signIn(), signOut(),
 *                             toast(), confirm(), pay(), confirmOrder(),
 *                             opt(), citiesOf(), loadMe(), helpers (fmt*)
 *  Alpine.store('auth')     — token, user, me (candidate profile), ready,
 *                             signedIn, and the sign-in popup's state
 *  Alpine.store('ui')       — toast, confirm dialog, payment sheet
 *  Alpine.store('opts')     — GET /config/options, loaded once per page
 *
 * Page scripts register their own `Alpine.data(...)` on `alpine:init` and
 * read these stores (`$store.auth.me`) or call `Inthes.*`.
 *
 * Events on window: `inthes:ready` (session restored, `me` loaded or not),
 * `inthes:signed-in`, `inthes:signed-out`.
 */
;(function () {
  // The WhatsApp logo. The shared lucide set (admin/icons.php) has no brand
  // glyphs — its `whatsapp` is a plain chat bubble that reads as SMS.
  if (window.ICONS) {
    window.ICONS.whatsapp = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>'
  }

  const API = '/api/v1'
  const TOKEN_KEY = 'inthes.token'
  const USER_KEY = 'inthes.user'

  const store = {
    get(k) { try { return localStorage.getItem(k) } catch (e) { return null } },
    set(k, v) { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v) } catch (e) { /* private mode */ } },
  }

  // ── formatting ──────────────────────────────────────────────────────
  const fmtNumber = (v) => Number(v ?? 0).toLocaleString('en-IN')
  const rupees = (paise) => '₹' + Math.round(Number(paise || 0) / 100).toLocaleString('en-IN')

  function timeAgo(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    const s = Math.floor((Date.now() - d.getTime()) / 1000)
    if (s < 60) return 'just now'
    const m = Math.floor(s / 60)
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}h ago`
    const days = Math.floor(h / 24)
    if (days === 1) return 'yesterday'
    if (days < 30) return `${days}d ago`
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
  }

  function fmtDate(iso, withTime = false) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return iso
    const o = { day: 'numeric', month: 'short', year: 'numeric' }
    if (withTime) Object.assign(o, { hour: 'numeric', minute: '2-digit' })
    return d.toLocaleString('en-IN', o)
  }

  /**
   * Two letters for an avatar. Words without a letter are skipped, so a phone
   * number never becomes "81" — callers show a person icon when this is ''.
   */
  function initials(name) {
    const p = String(name ?? '').trim().split(/\s+/).filter((w) => /\p{L}/u.test(w)).map((w) => w.replace(/[^\p{L}]/gu, ''))
    if (!p.length) return ''
    if (p.length === 1) return p[0].slice(0, 2).toUpperCase()
    return (p[0][0] + p[1][0]).toUpperCase()
  }

  const clone = (v) => JSON.parse(JSON.stringify(v ?? null))

  /** A query string with PHP-style arrays: `shift[]=Day&shift[]=Night`. */
  function qs(params) {
    const out = []
    for (const [k, v] of Object.entries(params || {})) {
      if (v === null || v === undefined || v === '') continue
      if (Array.isArray(v)) v.forEach((x) => out.push(encodeURIComponent(k + '[]') + '=' + encodeURIComponent(x)))
      else out.push(encodeURIComponent(k) + '=' + encodeURIComponent(v))
    }
    return out.length ? '?' + out.join('&') : ''
  }

  const $auth = () => window.Alpine.store('auth')
  const $ui = () => window.Alpine.store('ui')
  const $opts = () => window.Alpine.store('opts')

  // ── account roles ───────────────────────────────────────────────────
  /**
   * One account, one job. A job seeker account (`candidate`) applies for
   * jobs; an employer account (`recruiter`) posts them. The same mobile
   * number may hold one of each, but they are separate accounts with their
   * own OTP sign-in — a session is always exactly one of them.
   */
  const ROLE_LABEL = { candidate: 'Job seeker account', recruiter: 'Employer account' }
  const AUDIENCE_ROLE = { jobSeeker: 'candidate', recruiter: 'recruiter' }
  const onEmployerArea = () => /^\/employer(\/|$)/.test(location.pathname)
  const onAccountArea = () => /^\/account(\/|$)/.test(location.pathname)
  /** Pages under /account that any signed-in account may open ([data-any-role-page]). */
  const anyRolePage = () => !!document.querySelector('[data-any-role-page]')

  /** The kind of account `path` needs, or '' when either may call it. */
  function roleFor(path, opts = {}) {
    const [p, query] = String(path).split('?')
    if (/^\/recruiter(\/|$)/.test(p)) return 'recruiter'
    if (/^\/candidate(\/|$)/.test(p) || /^\/applications(\/|$)/.test(p)) return 'candidate'
    // The inbox is read as one end of the thread: `as=recruiter` is the employer's.
    if (p === '/conversations') {
      const as = new URLSearchParams(query || '').get('as')
      if (as === 'recruiter' || as === 'candidate') return as
    }
    const method = (opts.method || 'GET').toUpperCase()
    const body = opts.body && !(opts.body instanceof FormData) ? opts.body : {}
    let audience = ''
    if (p === '/notifications' || p === '/notifications/read') {
      audience = new URLSearchParams(query || '').get('audience') || body.audience || ''
    } else if ((p === '/subscription' || p === '/payments/orders') && method === 'POST') {
      audience = body.audience || ''
    }
    return AUDIENCE_ROLE[audience] || ''
  }

  /** The error a wrong-account call throws — after opening the switch dialog. */
  function wrongRole(needs, message) {
    openSwitch(needs)
    const err = new Error(message || (needs === 'recruiter'
      ? 'Posting jobs needs an employer account.'
      : 'Applying for jobs needs a job seeker account.'))
    err.status = 403
    err.code = 'wrong_account_role'
    err.requiredRole = needs
    err.errors = {}
    return err
  }

  /**
   * "Posting jobs needs an employer account…" — the switch dialog
   * (site/partials/overlays). Continue signs this session out and opens
   * sign-in fixed to the other role; `next` is where to carry on afterwards.
   */
  function openSwitch(role, next = '') {
    const sw = $ui().roleSwitch
    if (sw.open && sw.role === role) { if (next) sw.next = next; return }
    Object.assign(sw, { open: true, role, next: next || '', at: Date.now() })
  }

  function cancelSwitch() {
    const sw = $ui().roleSwitch
    // The click that opened it must not also count as a click outside it.
    if (Date.now() - (sw.at || 0) < 350) return
    sw.open = false
    sw.next = ''
    // A page that belongs to the other kind of account has nothing to show
    // this one — back to this account's own home.
    const role = $auth().role
    if (role === 'candidate' && onEmployerArea()) location.href = '/account'
    else if (role === 'recruiter' && onAccountArea() && !anyRolePage()) location.href = '/employer'
  }

  function continueSwitch() {
    const sw = $ui().roleSwitch
    const { role, next } = sw
    const phone = $auth().phone
    sw.open = false
    sw.next = ''
    signOut({ quiet: true })
    signIn('', { role, lock: true, next, switching: true, phone })
  }

  /**
   * Resolves true when the session is (or becomes) the `role` kind of
   * account. Signed out → sign-in fixed to that role, then carry on in
   * place. Signed in as the other kind → the switch dialog, and false.
   */
  async function requireRole(role, { reason = '', next = '' } = {}) {
    const a = $auth()
    if (!a.token) return signIn(reason, { role, lock: true })
    if (!a.role) await inferRole()
    if (a.role === role) return true
    openSwitch(role, next)
    return false
  }

  /**
   * Sessions stored before the role was: ask a job-seeker-only endpoint. A
   * 403 `wrong_account_role` means this is an employer account.
   */
  async function inferRole() {
    const a = $auth()
    if (!a.token || a.role) return a.role
    try {
      const res = await fetch(API + '/candidate/profile', { headers: { Accept: 'application/json', Authorization: 'Bearer ' + a.token } })
      let json = null
      try { json = await res.json() } catch (e) { /* empty body */ }
      if (res.ok) { setRole('candidate'); a.me = json?.data || null }
      else if (res.status === 403 && json?.code === 'wrong_account_role') setRole('recruiter')
    } catch (e) { /* offline — leave it unknown */ }
    return a.role
  }

  function setRole(role) {
    const a = $auth()
    a.user = Object.assign({}, a.user || {}, { role })
    store.set(USER_KEY, JSON.stringify(a.user))
  }

  // ── API ─────────────────────────────────────────────────────────────
  async function refreshToken() {
    try {
      const res = await fetch(API + '/auth/refresh', {
        method: 'POST',
        headers: { Accept: 'application/json', Authorization: 'Bearer ' + $auth().token },
      })
      if (!res.ok) return false
      const json = await res.json()
      if (!json?.data?.token) return false
      $auth().token = json.data.token
      store.set(TOKEN_KEY, json.data.token)
      return true
    } catch (e) { return false }
  }

  /**
   * fetch over `/api/v1`: JSON in and out, `FormData` passed through. A 401
   * gets one token refresh and a retry (as the app does) before the session
   * is dropped. Errors carry `.status` and `.errors` (field → messages).
   */
  async function api(path, opts = {}, _retried = false) {
    const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {})
    let body = opts.body
    if (body !== undefined && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json'
      body = JSON.stringify(body)
    }
    const token = $auth().token
    if (token) headers.Authorization = 'Bearer ' + token

    // One account, one job: a call that belongs to the other kind of account
    // is not sent at all — the switch dialog explains instead of a dead 403.
    const needs = token ? roleFor(path, opts) : ''
    const mine = $auth().role
    if (needs && mine && needs !== mine) throw wrongRole(needs)

    let res
    try {
      res = await fetch(API + path, { method: opts.method || 'GET', headers, body })
    } catch (e) {
      throw new Error('No internet connection. Check your network and try again.')
    }
    let json = null
    try { json = await res.json() } catch (e) { /* empty body */ }

    if (res.status === 401 && token && !path.startsWith('/auth/')) {
      if (!_retried && await refreshToken()) return api(path, opts, true)
      signOut({ expired: true })
      throw new Error('Your session expired — please sign in again.')
    }
    // The server's own check (e.g. a session whose role is not known yet).
    if (res.status === 403 && json && json.code === 'wrong_account_role') {
      throw wrongRole(json.required_role === 'recruiter' ? 'recruiter' : 'candidate', json.message)
    }
    if (!res.ok) {
      const err = new Error(
        (json && json.message) ||
        (json && json.errors ? Object.values(json.errors).flat().join(' ') : null) ||
        'Something went wrong. Please try again.',
      )
      err.status = res.status
      err.errors = (json && json.errors) || {}
      throw err
    }
    return json || {}
  }

  /** Multipart upload with the field name the API expects (`file`). */
  function upload(path, file, extra = {}) {
    const fd = new FormData()
    fd.append('file', file)
    for (const [k, v] of Object.entries(extra)) fd.append(k, v)
    return api(path, { method: 'POST', body: fd })
  }

  /**
   * The signed-in account's own profile: the candidate profile into `me` for
   * a job seeker, the contact card into `employer` for an employer (whose
   * `me` stays null — job seeker screens never read an employer's data).
   */
  async function loadMe() {
    const a = $auth()
    if (!a.role) await inferRole()
    if (a.role === 'recruiter') {
      try { a.employer = (await api('/recruiter/profile')).data || null } catch (e) { a.employer = null }
      a.me = null
      return null
    }
    const res = await api('/candidate/profile')
    a.me = res.data || null
    return a.me
  }

  // ── sign in ─────────────────────────────────────────────────────────
  let pendingSignIn = null

  /**
   * Open the sign-in popup. Resolves `true` once signed in (immediately if
   * already), `false` if the visitor closes it — so a caller can carry on
   * with what it was doing in place:
   *
   *   if (!(await Inthes.signIn('to apply for this job'))) return
   */
  /*
   * `opts.role` is the kind of account (default: employer on /employer pages,
   * job seeker elsewhere); `lock` fixes it, hiding the picker; `next` is
   * where to go once signed in. Already signed in as the other kind with a
   * locked role → the switch dialog instead, and false.
   */
  function signIn(reason = '', opts = {}) {
    const a = $auth()
    if (a.token) {
      if (opts.role && opts.lock) return requireRole(opts.role, { reason, next: opts.next })
      return Promise.resolve(true)
    }
    if (pendingSignIn) pendingSignIn.resolve(false)
    const role = opts.role || (onEmployerArea() ? 'recruiter' : 'candidate')
    return new Promise((resolve) => {
      pendingSignIn = { resolve }
      Object.assign(a.modal, {
        open: true, stage: 'phone', otp: '', error: '', reason, busy: false,
        role, lockRole: !!opts.lock, next: opts.next || '', switching: !!opts.switching,
      })
      if (opts.phone) a.modal.phone = opts.phone
    })
  }

  /** The sign-in popup's "Job seeker / Employer" picker. */
  function pickRole(role) {
    const m = $auth().modal
    if (m.lockRole || m.busy || m.stage !== 'phone') return
    m.role = role
    m.error = ''
  }

  function closeSignIn() {
    $auth().modal.open = false
    if (pendingSignIn) { pendingSignIn.resolve(false); pendingSignIn = null }
  }

  async function sendOtp() {
    const m = $auth().modal
    if (m.busy) return
    const phone = m.phone.trim()
    if (!phone) { m.error = 'Mobile number is required'; return }
    if (!/^[6-9]\d{9}$/.test(phone)) { m.error = 'Enter a valid 10-digit number'; return }
    m.busy = true
    m.error = ''
    try {
      const res = await api('/auth/otp/send', { method: 'POST', body: { phone, role: m.role } })
      m.verificationId = res.data.verification_id
      // Local/dev servers echo the code (OTP_EXPOSE_CODE) — prefill it there.
      m.otp = res.data.code ? String(res.data.code) : ''
      m.stage = 'otp'
      m.resendIn = 24
      clearInterval(m._timer)
      m._timer = setInterval(() => { m.resendIn -= 1; if (m.resendIn <= 0) clearInterval(m._timer) }, 1000)
    } catch (e) { m.error = e.message }
    m.busy = false
  }

  async function verifyOtp() {
    const a = $auth()
    const m = a.modal
    const otp = m.otp.replace(/\D/g, '')
    if (otp.length !== 6 || m.busy) return
    m.busy = true
    m.error = ''
    try {
      const res = await api('/auth/otp/verify', {
        method: 'POST',
        body: { phone: m.phone.trim(), otp, verification_id: m.verificationId, role: m.role },
      })
      a.token = res.data.token
      a.user = Object.assign({}, res.data.user, { role: res.data.user?.role || m.role })
      store.set(TOKEN_KEY, a.token)
      store.set(USER_KEY, JSON.stringify(a.user))
      const isNew = !!res.data.user?.is_new_user
      const dest = afterSignIn(a.user.role, isNew, m)
      if (dest) {
        // Somewhere else to be (a new employer's setup, the right area):
        // go there rather than carry on with what this page was doing.
        if (pendingSignIn) { pendingSignIn.resolve(false); pendingSignIn = null }
        location.href = dest
        return
      }
      try { await loadMe() } catch (e) { /* the page will retry */ }
      m.open = false
      m.otp = ''
      clearInterval(m._timer)
      window.dispatchEvent(new CustomEvent('inthes:signed-in', { detail: { isNew, role: a.user.role } }))
      toast('Signed in')
      if (pendingSignIn) { pendingSignIn.resolve(true); pendingSignIn = null }
    } catch (e) { m.error = e.message; m.otp = '' }
    m.busy = false
  }

  /** Where to go after signing in, or '' to stay and carry on in place. */
  function afterSignIn(role, isNew, m) {
    const here = location.pathname + location.search
    const safe = (u) => (typeof u === 'string' && u.startsWith('/') && !u.startsWith('//') ? u : '')
    const next = safe(m.next)
    let dest = ''
    if (role === 'recruiter') {
      // A first-time employer sets up their contact details, then the dashboard.
      if (isNew) dest = '/employer/settings?welcome=1'
      else if (next) dest = next
      else if (onAccountArea()) dest = '/employer'
    } else {
      // A first-time job seeker who came over from an employer account sets
      // up their profile first, then carries on (e.g. the job they wanted).
      if (isNew && m.switching) dest = '/account/setup' + (next ? '?next=' + encodeURIComponent(next) : '')
      else if (next) dest = next
      else if (onEmployerArea()) dest = '/account'
    }
    return dest && dest !== here ? dest : ''
  }

  function signOut({ expired = false, quiet = false } = {}) {
    const a = $auth()
    if (a.token && !expired) api('/auth/logout', { method: 'POST' }).catch(() => {})
    a.token = ''
    a.user = null
    a.me = null
    a.employer = null
    store.set(TOKEN_KEY, null)
    store.set(USER_KEY, null)
    window.dispatchEvent(new CustomEvent('inthes:signed-out', { detail: { expired, quiet } }))
    if (expired) { toast('Your session expired — please sign in again.', true); signIn() }
  }

  async function confirmSignOut() {
    if (await confirm({ title: 'Sign out?', message: 'You will need your mobile number to sign in again.', ok: 'Sign out', danger: true })) {
      signOut()
      // Signed-in-only pages have nothing to show a visitor; the home page does.
      if (document.body.dataset.requiresAuth) location.href = '/'
    }
  }

  // ── feedback ────────────────────────────────────────────────────────
  let toastTimer = null
  function toast(message, isError = false) {
    if (!message) return
    const u = $ui()
    // The switch dialog already says why; a red toast under it is noise.
    if (isError && u.roleSwitch.open) return
    u.toast = message
    u.toastError = isError
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => { u.toast = '' }, 4000)
  }

  /** Confirm dialog. Resolves true/false. */
  function confirm({ title = 'Are you sure?', message = '', ok = 'OK', cancel = 'Cancel', danger = false } = {}) {
    return new Promise((resolve) => {
      Object.assign($ui().confirm, { open: true, title, message, ok, cancel, danger })
      $ui()._confirmResolve = resolve
    })
  }

  // ── payments ────────────────────────────────────────────────────────
  /**
   * The payment-method sheet. Resolves with `upi` | `card` | `netbanking`, or
   * null when cancelled. Then open the order the caller needs and settle it
   * with `confirmOrder(id)`.
   */
  async function pay(amountLabel) {
    const p = $ui().pay
    if (!p.methods.length) {
      try {
        const res = await api('/payments/methods')
        p.methods = (res.data?.methods || res.data || []).map((m) => ({ key: m.key || m.id || m, label: m.label || m.name || m }))
      } catch (e) {
        p.methods = [{ key: 'upi', label: 'UPI' }, { key: 'card', label: 'Card' }, { key: 'netbanking', label: 'Net banking' }]
      }
    }
    return new Promise((resolve) => {
      Object.assign(p, { open: true, amount: amountLabel, method: p.methods[0]?.key || '' })
      $ui()._payResolve = resolve
    })
  }

  async function confirmOrder(orderId) {
    const res = await api(`/payments/orders/${orderId}/confirm`, { method: 'POST', body: { gateway_payload: {} } })
    const order = res.data?.order || res.data
    if (order?.status && order.status !== 'paid') throw new Error(order.failure_reason || 'Payment failed. You were not charged.')
    return order
  }

  // ── reference data ──────────────────────────────────────────────────
  function opt(key) {
    const v = $opts().data?.[key]
    return Array.isArray(v) ? v : []
  }
  const citiesOf = (state) => ($opts().data?.state_cities || {})[state] || []

  async function copy(text, done = 'Link copied') {
    try { await navigator.clipboard.writeText(text); toast(done) } catch (e) { toast(text) }
  }

  async function share(title, url) {
    if (navigator.share) { try { await navigator.share({ title, url }) } catch (e) { /* cancelled */ } return }
    copy(url)
  }

  function toggleIn(list, value, max) {
    const i = list.indexOf(value)
    if (i !== -1) { list.splice(i, 1); return }
    if (max && list.length >= max) { toast(`You can pick up to ${max}.`, true); return }
    list.push(value)
  }


  // ── combo pickers (site/ui/combo) ───────────────────────────────────
  /**
   * The rows a combo shows: the whole list until the user types, then the
   * entries containing what they typed. The "Add your own" row is the
   * partial's own last row, after these.
   */
  function comboList(items, value, typing) {
    const all = Array.isArray(items) ? items : []
    const q = String(value || '').trim().toLowerCase()
    if (!typing || !q) return all
    return all.filter((i) => String(i).toLowerCase().includes(q))
  }
  /** Something typed that the list doesn't already have. */
  function comboCanAdd(items, value) {
    const v = String(value || '').trim().toLowerCase()
    if (!v) return false
    return !(Array.isArray(items) ? items : []).some((i) => String(i).toLowerCase() === v)
  }

  // ── places (core/utils/geocoder.dart + location_capture.dart) ───────
  const PLUS_CODE = /^[23456789CFGHJMPQRVWX]{2,8}\+[23456789CFGHJMPQRVWX]{0,3}$/

  /** Parts split on commas, trimmed, with blanks, plus codes and repeats dropped (LocationCapture.joinAddressParts). */
  function joinAddressParts(parts) {
    const out = []
    for (const part of parts) {
      for (const token of String(part || '').split(',')) {
        const v = token.trim()
        if (!v || v.toLowerCase() === 'unnamed road' || PLUS_CODE.test(v.toUpperCase())) continue
        if (out.some((e) => e.toLowerCase() === v.toLowerCase())) continue
        out.push(v)
      }
    }
    return out.join(', ')
  }

  /**
   * The street part of an address — house/flat no., street, area — without
   * the city, state, pincode and country (LocationCapture.localityOf):
   * "21-987, Jeedimetla" out of "21-987, Jeedimetla, Hyderabad, Telangana".
   * It used to keep only the first comma part, which for a house is just its number.
   */
  function localityOf(address, city = '', state = '') {
    const drop = new Set([city, state, 'india'].map((s) => String(s || '').trim().toLowerCase()).filter(Boolean))
    return String(address || '').split(',').map((p) => p.trim()).filter(Boolean)
      .filter((p) => !drop.has(p.toLowerCase()) && !/^\d{6}$/.test(p)).join(', ')
  }

  /**
   * Coordinates → { address, city, district, state, pincode } (Geocoder.placemarks), or null.
   * OpenStreetMap's public reverse geocoder: the app's Google key is not
   * enabled for browser geocoding, and it does not belong in page source.
   */
  async function reverseGeocode(lat, lng) {
    try {
      const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&accept-language=en&lat=${lat}&lon=${lng}`)
      if (!res.ok) return null
      const a = (await res.json()).address || {}
      const city = a.city || a.town || a.village || a.municipality || ''
      const state = a.state || ''
      // Ward / zone names ("Ward 130 Subash Nagar") are administrative, not an address line.
      const area = (v) => (v && !/^ward\b/i.test(v) && !/municipal corporation|zone$/i.test(v) ? v : '')
      return {
        address: joinAddressParts([
          a.house_number, a.house_name, a.building, a.road, a.neighbourhood, a.residential, a.quarter, a.industrial,
          area(a.suburb), a.hamlet, city, state,
        ]),
        city, district: a.state_district || a.county || '', state, pincode: a.postcode || '',
      }
    } catch (e) { return null }
  }

  /** The browser's position, as a promise. Rejects with the GeolocationPositionError. */
  function currentPosition() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) { reject(Object.assign(new Error('unsupported'), { code: 2 })); return }
      navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000 })
    })
  }

  /** The entry of `list` named by any of `texts` — exact first, then as a whole word, longest first (stateIn / cityIn). */
  function findIn(list, ...texts) {
    const byLength = [...(list || [])].sort((a, b) => b.length - a.length)
    for (const t of texts) {
      const want = String(t || '').trim().toLowerCase()
      const exact = want && byLength.find((c) => c.toLowerCase() === want)
      if (exact) return exact
    }
    for (const t of texts) {
      const text = String(t || '').toLowerCase()
      if (!text) continue
      const hit = byLength.find((c) => new RegExp('\\b' + c.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b').test(text))
      if (hit) return hit
    }
    return ''
  }

  /** Resolves once the session is restored (`$store.auth.ready`). Page inits await it before loading signed-in data. */
  let readyResolve
  const readyPromise = new Promise((r) => { readyResolve = r })
  const whenReady = () => readyPromise

  window.Inthes = {
    api, upload, qs, loadMe, whenReady,
    signIn, closeSignIn, sendOtp, verifyOtp, signOut, confirmSignOut, pickRole,
    requireRole, openSwitch, cancelSwitch, continueSwitch, inferRole, ROLE_LABEL,
    /**
     * A link into the other kind of account's area:
     * `@click="Inthes.guardArea($event, 'recruiter')"` opens the switch
     * dialog instead of following it when the session is the other kind.
     */
    guardArea(event, role, next) {
      const a = $auth()
      if (!a.token || !a.role || a.role === role) return true
      if (event) event.preventDefault()
      openSwitch(role, next || (event && event.currentTarget && event.currentTarget.getAttribute('href')) || '')
      return false
    },
    toast, confirm, pay, confirmOrder,
    opt, citiesOf, copy, share, toggleIn, comboList, comboCanAdd,
    reverseGeocode, currentPosition, joinAddressParts, localityOf, findIn,
    fmtNumber, rupees, timeAgo, fmtDate, initials, clone,
    store,
    storeUrl: () => window.INTHES_CONFIG?.storeUrl || '/get-app/go',
  }

  // ── stores ──────────────────────────────────────────────────────────
  document.addEventListener('alpine:init', () => {
    Alpine.store('auth', {
      token: store.get(TOKEN_KEY) || '',
      user: (() => { try { return JSON.parse(store.get(USER_KEY) || 'null') } catch (e) { return null } })(),
      me: null,
      ready: false,
      employer: null, // an employer account's contact card (GET /recruiter/profile)
      modal: { open: false, stage: 'phone', phone: '', otp: '', role: 'candidate', lockRole: false, next: '', switching: false, verificationId: '', busy: false, error: '', resendIn: 0, reason: '', _timer: null },
      get signedIn() { return !!this.token },
      /** 'candidate' | 'recruiter' | '' (signed out, or not known yet). */
      get role() { return this.token ? (this.user?.role || '') : '' },
      get isSeeker() { return this.role === 'candidate' },
      get isEmployer() { return this.role === 'recruiter' },
      get roleLabel() { return ROLE_LABEL[this.role] || '' },
      /** Signed in as the `role` kind of account. */
      is(role) { return !!this.token && this.role === role },
      get name() { return this.isEmployer ? (this.employer?.contact_person_name || '') : (this.me?.name || '') },
      get firstName() { return (this.name || '').trim().split(/\s+/)[0] || '' },
      get phone() { return this.user?.phone || this.me?.phone || '' },
    })

    Alpine.store('ui', {
      toast: '',
      toastError: false,
      confirm: { open: false, title: '', message: '', ok: 'OK', cancel: 'Cancel', danger: false },
      pay: { open: false, amount: '', methods: [], method: '' },
      roleSwitch: { open: false, role: 'recruiter', next: '', at: 0 },
      _confirmResolve: null,
      _payResolve: null,
      closeConfirm(result) {
        this.confirm.open = false
        const r = this._confirmResolve
        this._confirmResolve = null
        if (r) r(result)
      },
      closePay(ok) {
        this.pay.open = false
        const r = this._payResolve
        this._payResolve = null
        if (r) r(ok ? this.pay.method : null)
      },
    })

    Alpine.store('opts', { data: {}, ready: false })
  })

  // Session restore + options, once Alpine is up.
  document.addEventListener('alpine:initialized', async () => {
    api('/config/options').then((r) => { $opts().data = r.data || {}; $opts().ready = true }).catch(() => {})
    const a = $auth()
    // One-time carry-over from the old employer area.
    if (!a.token && store.get('inthes.employer.token')) {
      a.token = store.get('inthes.employer.token')
      store.set(TOKEN_KEY, a.token)
      store.set('inthes.employer.token', null)
      store.set('inthes.employer.user', null)
    }
    if (a.token) {
      await inferRole()
      try { await loadMe() } catch (e) { /* 401 already signed out */ }
    }
    // The other kind of account on an area's pages: explain and offer the switch.
    if (a.token && a.role) {
      const here = location.pathname + location.search
      if (onEmployerArea() && a.role !== 'recruiter') openSwitch('recruiter', here)
      else if (onAccountArea() && a.role !== 'candidate' && !anyRolePage()) openSwitch('candidate', here)
    }
    a.ready = true
    readyResolve()
    window.dispatchEvent(new CustomEvent('inthes:ready'))
  })
})()
