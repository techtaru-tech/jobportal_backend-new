/**
 * "Complete your profile" nudge for a brand-new job seeker.
 *
 * Shown after a new account signs in anywhere on the site (the `inthes:
 * signed-in` event with `isNew`) while its profile is still empty. It never
 * blocks: a small card in the corner with one action and a close. It comes
 * back on later pages this session until the profile has something on it, the
 * visitor opens /account/setup, or they dismiss it.
 */
;(function () {
  const KEY = 'inthes.profileNudge'
  const ss = {
    get() { try { return sessionStorage.getItem(KEY) } catch (e) { return null } },
    set(v) { try { v == null ? sessionStorage.removeItem(KEY) : sessionStorage.setItem(KEY, v) } catch (e) { /* private mode */ } },
  }
  let el = null

  const me = () => window.Alpine && window.Alpine.store('auth') && window.Alpine.store('auth').me
  const empty = () => { const m = me(); return !!m && Number(m.profile_strength || 0) === 0 }
  const onSetup = () => location.pathname.replace(/\/+$/, '') === '/account/setup'

  function hide() {
    if (el) { el.remove(); el = null }
  }

  function show() {
    if (el || onSetup()) return
    el = document.createElement('div')
    el.setAttribute('role', 'status')
    el.className = 'fixed inset-x-page bottom-[88px] z-[96] mx-auto max-w-[420px] lg:bottom-xl lg:left-xl lg:right-auto lg:mx-0'
    el.innerHTML =
      '<div class="flex items-start gap-md rounded-card border-hair border-hairline bg-surface p-lg shadow-raised animate-enter-up">' +
        '<span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary [&>svg]:h-5 [&>svg]:w-5">' + ((window.ICONS && window.ICONS.sparkles) || '') + '</span>' +
        '<div class="min-w-0 flex-1">' +
          '<p class="text-h5 text-ink">Complete your profile</p>' +
          '<p class="mt-[2px] text-caption text-ink-secondary">Get better job matches — it takes about two minutes.</p>' +
          '<div class="mt-md flex items-center gap-sm">' +
            '<a href="/account/setup" data-go class="inline-flex h-9 items-center justify-center rounded-button bg-primary px-lg text-btnghost font-semibold text-ink-onPrimary shadow-button hover:bg-primary-dark">Complete profile</a>' +
            '<button type="button" data-close class="inline-flex h-9 items-center justify-center rounded-button px-md text-btnghost font-semibold text-ink-secondary hover:bg-surface-muted">Later</button>' +
          '</div>' +
        '</div>' +
        '<button type="button" data-close aria-label="Dismiss" class="-mr-xs -mt-xs inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted [&>svg]:h-4 [&>svg]:w-4">' + ((window.ICONS && window.ICONS.x) || '×') + '</button>' +
      '</div>'
    el.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => { ss.set(null); hide() }))
    el.querySelector('[data-go]').addEventListener('click', () => ss.set(null))
    document.body.appendChild(el)
  }

  window.addEventListener('inthes:signed-in', (e) => {
    if (!e.detail || e.detail.isNew !== true || !empty()) return
    ss.set('1')
    // After the sign-in popup and its toast have settled.
    setTimeout(show, 900)
  })

  window.addEventListener('inthes:ready', () => {
    if (ss.get() !== '1') return
    if (!empty() || onSetup()) { if (!empty()) ss.set(null); return }
    setTimeout(show, 600)
  })

  window.addEventListener('inthes:signed-out', () => { ss.set(null); hide() })
})()
