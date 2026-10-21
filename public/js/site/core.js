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

  async function loadMe() {
    const res = await api('/candidate/profile')
    $auth().me = res.data || null
    return $auth().me
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
  function signIn(reason = '') {
    const a = $auth()
    if (a.token) return Promise.resolve(true)
    if (pendingSignIn) pendingSignIn.resolve(false)
    return new Promise((resolve) => {
      pendingSignIn = { resolve }
      Object.assign(a.modal, { open: true, stage: 'phone', otp: '', error: '', reason, busy: false })
    })
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
      a.user = res.data.user
      store.set(TOKEN_KEY, a.token)
      store.set(USER_KEY, JSON.stringify(a.user))
      try { await loadMe() } catch (e) { /* the page will retry */ }
      m.open = false
      m.otp = ''
      clearInterval(m._timer)
      window.dispatchEvent(new CustomEvent('inthes:signed-in', { detail: { isNew: !!res.data.user?.is_new_user } }))
      toast('Signed in')
      if (pendingSignIn) { pendingSignIn.resolve(true); pendingSignIn = null }
    } catch (e) { m.error = e.message; m.otp = '' }
    m.busy = false
  }

  function signOut({ expired = false } = {}) {
    const a = $auth()
    if (a.token && !expired) api('/auth/logout', { method: 'POST' }).catch(() => {})
    a.token = ''
    a.user = null
    a.me = null
    store.set(TOKEN_KEY, null)
    store.set(USER_KEY, null)
    window.dispatchEvent(new CustomEvent('inthes:signed-out', { detail: { expired } }))
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

  /** Resolves once the session is restored (`$store.auth.ready`). Page inits await it before loading signed-in data. */
  let readyResolve
  const readyPromise = new Promise((r) => { readyResolve = r })
  const whenReady = () => readyPromise

  window.Inthes = {
    api, upload, qs, loadMe, whenReady,
    signIn, closeSignIn, sendOtp, verifyOtp, signOut, confirmSignOut,
    toast, confirm, pay, confirmOrder,
    opt, citiesOf, copy, share, toggleIn,
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
      modal: { open: false, stage: 'phone', phone: '', otp: '', role: 'candidate', verificationId: '', busy: false, error: '', resendIn: 0, reason: '', _timer: null },
      get signedIn() { return !!this.token },
      get name() { return this.me?.name || '' },
      get firstName() { return (this.me?.name || '').trim().split(/\s+/)[0] || '' },
      get phone() { return this.user?.phone || this.me?.phone || '' },
    })

    Alpine.store('ui', {
      toast: '',
      toastError: false,
      confirm: { open: false, title: '', message: '', ok: 'OK', cancel: 'Cancel', danger: false },
      pay: { open: false, amount: '', methods: [], method: '' },
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
    if (a.token) { try { await loadMe() } catch (e) { /* 401 already signed out */ } }
    a.ready = true
    readyResolve()
    window.dispatchEvent(new CustomEvent('inthes:ready'))
  })
})()
