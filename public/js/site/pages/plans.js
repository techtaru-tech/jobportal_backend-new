/**
 * Plans — /account/plan (audience jobSeeker) and /employer/plan (recruiter).
 *
 * The app's subscription screen as a pricing page: GET /subscription carries
 * both sides' plans and the active one; a paid plan is bought with the
 * two-step payment flow (POST /payments/orders → confirm), Free is a direct
 * switch (POST /subscription accepts free plans only). The employer page also
 * shows the job-posting plan prices and the credits left from earlier
 * purchases (GET /recruiter/job-plans) — read-only; they are bought while
 * posting a job.
 */
;(function () {
  const enc = encodeURIComponent

  document.addEventListener('alpine:init', () => {
    Alpine.data('plansPage', ({ audience }) => ({
      audience, // 'jobSeeker' | 'recruiter'
      sub: null,
      loadError: '',
      busy: '', // the plan id being bought / switched to
      orders: [],
      ordersLoaded: false,
      jobPlans: null,

      async init() {
        await Inthes.whenReady()
        window.addEventListener('inthes:signed-in', () => this.load())
        if (Alpine.store('auth').token) this.load()
      },

      async load() {
        this.loadError = ''
        try {
          const res = await Inthes.api('/subscription')
          this.sub = res.data || null
        } catch (e) { this.loadError = e.message }
        this.loadOrders()
        if (this.audience === 'recruiter') {
          Inthes.api('/recruiter/job-plans').then((r) => { this.jobPlans = r.data || null }).catch(() => { this.jobPlans = null })
        }
      },

      async loadOrders() {
        try {
          const res = await Inthes.api('/payments/orders')
          const all = res.data?.orders || res.data || []
          this.orders = all.filter((o) => !o.audience || o.audience === this.audience)
        } catch (e) { this.orders = [] }
        this.ordersLoaded = true
      },

      get plans() { return this.sub?.plans?.[this.audience] || [] },
      get active() { return this.sub?.active?.[this.audience] || null },
      get currentPlan() { return this.plans.find((p) => p.id === this.active?.plan_id) || this.plans.find((p) => p.is_free) || null },
      isCurrent(plan) { return plan.id === (this.active?.plan_id || this.plans.find((p) => p.is_free)?.id) },

      buttonLabel(plan) {
        if (this.isCurrent(plan)) return 'Current plan'
        if (plan.is_free) return 'Switch to Free'
        return 'Upgrade to ' + plan.name
      },

      async choose(plan) {
        if (this.isCurrent(plan) || this.busy) return
        if (!(await Inthes.signIn('to change your plan'))) return
        const audience = this.audience
        if (plan.is_free) {
          const cur = this.currentPlan
          const ok = await Inthes.confirm({
            title: 'Switch to Free?',
            message: cur && !cur.is_free ? `You will lose the ${cur.name} plan's features straight away. Payments already made are not refunded.` : '',
            ok: 'Switch to Free',
            danger: true,
          })
          if (!ok) return
        }
        this.busy = plan.id
        try {
          if (plan.is_free) {
            await Inthes.api('/subscription', { method: 'POST', body: { audience, plan_id: plan.id } })
          } else {
            const method = await Inthes.pay(plan.price_label || Inthes.rupees(plan.price_paise))
            if (!method) { this.busy = ''; return }
            const res = await Inthes.api('/payments/orders', { method: 'POST', body: { audience, plan_id: plan.id, method } })
            const order = res.data?.order || res.data
            if (!order?.id) throw new Error('Could not start the payment. Please try again.')
            await Inthes.confirmOrder(order.id)
          }
          await this.load()
          Inthes.toast(plan.is_free ? 'You are on the Free plan' : `${plan.name} plan activated`)
        } catch (e) {
          Inthes.toast(e.message, true)
          this.load()
        }
        this.busy = ''
      },

      // ── order history ────────────────────────────────────────────────
      /** One-off purchases come back named by their key; make those readable. */
      orderName(o) {
        const n = o.plan_name || o.plan_id || 'Payment'
        if (/^[a-z0-9_]+$/.test(n)) return (n.charAt(0).toUpperCase() + n.slice(1)).replace(/_/g, ' ')
        return n
      },

      statusClass(o) {
        if (o.status === 'paid') return 'bg-success-bg text-success'
        if (o.status === 'failed' || o.status === 'cancelled') return 'bg-danger-bg text-danger'
        return 'bg-surface-muted text-ink-secondary'
      },

      methodLabel(m) {
        return ({ upi: 'UPI', card: 'Card', netbanking: 'Net banking' })[m] || m || '—'
      },

      // ── job-posting plans (employer) ─────────────────────────────────
      get tiers() { return this.jobPlans?.tiers || [] },
      get reaches() { return this.jobPlans?.reaches || [] },
      get credits() { return (this.jobPlans?.credits || []).filter((c) => c.remaining > 0) },
      get creditsTotal() { return this.credits.reduce((n, c) => n + c.remaining, 0) },

      price(tier, reach, qty = 1) {
        return this.jobPlans?.prices?.[tier]?.[reach]?.[String(qty)] || null
      },

      /** The best per-job price at the largest bundle, when buying more is cheaper. */
      bulkPrice(tier, reach) {
        const q = this.jobPlans?.quantities || [1]
        const last = q[q.length - 1]
        const one = this.price(tier, reach, 1)
        const many = this.price(tier, reach, last)
        if (!one || !many || many.price_paise >= one.price_paise) return ''
        return `${many.price_label}/job for ${last}+`
      },

      tierName(id) { return this.tiers.find((t) => t.id === id)?.name || id },
      reachName(id) { return this.reaches.find((r) => r.id === id)?.name || id },

      postUrl: '/employer/jobs/new',
      jobUrl: (id) => '/employer/jobs/' + enc(id),
    }))
  })
})()
