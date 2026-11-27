/**
 * /employer/jobs/{id}/checkout — the app's "Choose plan" step as a checkout
 * page. One plan (unlimited job posts for N days, price incl. GST, both set
 * in Admin → Plans & pricing); the free job post or a running plan is used
 * instead when there is one; payment is POST …/orders → Inthes.confirmOrder.
 * The business document is not a condition of paying: it is asked for on
 * the "under review" screen that follows (partials/submitted).
 * Every plan is bought at District reach — the app offers no other.
 */
document.addEventListener('alpine:init', () => {
  const REACH = 'district'

  Alpine.data('empCheckout', (props = {}) => ({
    ...Emp.helpers,
    ...Emp.submittedDoc(),
    jobId: props.jobId,
    loading: true,
    loadError: '',
    job: null,
    org: null,
    plans: null,
    tier: 'monthly',
    paying: false,
    submitted: null,

    async init() {
      await Inthes.whenReady()
      if (Alpine.store('auth').signedIn) this.load()
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.loading = true
      this.loadError = ''
      try {
        const [jobs, orgs, plans] = await Promise.all([
          Emp.loadMine(), Emp.loadOrgs().catch(() => []),
          Inthes.api('/recruiter/job-plans').then((r) => r.data || null),
        ])
        const job = jobs.find((j) => j.id === this.jobId)
        if (!job) { this.loadError = 'This posting is no longer available.'; this.loading = false; return }
        this.job = job
        this.org = orgs.find((o) => o.id === job.organisation_id) || null
        this.plans = plans
        this.tier = (plans?.tiers || [])[0]?.id || 'monthly'
        // Already paid for: nothing to buy — show where it stands.
        if (!Emp.awaitingPayment(job)) this.submitted = job
      } catch (e) { this.loadError = e.message || "Couldn't load the plans. Check your connection and try again." }
      this.loading = false
    },

    get credits() { return (this.plans?.credits || []).filter((c) => c.remaining > 0) },
    /** The one plan: unlimited job posts for `days` days (Admin → Plans & pricing). */
    get plan() { return this.plans?.plan || null },
    get planTier() { return (this.plans?.tiers || [])[0] || null },
    get totalPaise() { return this.plan ? this.plan.price_paise : null },
    creditTitle(c) {
      if (c.unlimited) return 'Your ' + (this.plan?.name || 'Monthly') + ' plan is active'
      if (c.tier === 'free') return c.remaining === 1 ? 'You have 1 free job post' : 'You have ' + c.remaining + ' free job posts'
      return 'You have ' + c.remaining + ' posting' + (c.remaining === 1 ? '' : 's') + ' left'
    },
    creditNote(c) {
      if (c.unlimited) return c.label + ' — post this job at no extra cost.'
      if (c.tier === 'free') return 'Post this job free of charge.'
      return c.label + ' — post this job on it at no extra cost.'
    },

    async pay() {
      const total = this.totalPaise
      if (total == null || this.paying) return
      const method = await Inthes.pay(Inthes.rupees(total))
      if (!method) return
      this.paying = true
      try {
        const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.job.id)}/orders`, {
          method: 'POST', body: { tier: this.tier, reach: REACH, quantity: 1, method },
        })
        const order = res.data?.order
        if (!order?.id) throw new Error("Couldn't reach the payment service. Please try again.")
        await Inthes.confirmOrder(order.id, res.data?.checkout)
        await this.showSubmitted()
        Inthes.toast('Payment successful')
      } catch (e) {
        Inthes.toast(e.message || "That payment didn't go through. Please try again.", true)
      }
      this.paying = false
    },

    /** Posts on a posting left over from an earlier pack — no payment. */
    async useCredit(credit) {
      if (this.paying) return
      this.paying = true
      try {
        const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.job.id)}/use-credit`, { method: 'POST', body: { credit_id: credit.id } })
        this.submitted = res.data
        Inthes.toast(credit.tier === 'free' ? 'Posted with your free job post' : 'Posted on your plan')
        window.scrollTo({ top: 0 })
      } catch (e) { Inthes.toast(e.message, true) }
      this.paying = false
    },

    async showSubmitted() {
      try {
        const jobs = await Emp.loadMine()
        this.submitted = jobs.find((j) => j.id === this.job.id) || this.job
      } catch (e) { this.submitted = this.job }
      window.scrollTo({ top: 0 })
    },

    postAnother() { location.href = '/employer/jobs/new' },
  }))
})
