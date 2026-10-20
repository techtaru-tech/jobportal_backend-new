/**
 * /employer/jobs/{id}/checkout — the app's "Choose plan" step as a checkout
 * page: plan tiers, how many postings, postings left from an earlier pack,
 * the order total, and payment (POST …/orders → Inthes.confirmOrder).
 * Every plan is bought at District reach — the app offers no other.
 */
document.addEventListener('alpine:init', () => {
  const REACH = 'district'

  Alpine.data('empCheckout', (props = {}) => ({
    ...Emp.helpers,
    jobId: props.jobId,
    loading: true,
    loadError: '',
    job: null,
    org: null,
    plans: null,
    tier: '',
    quantity: 1,
    paying: false,
    submitted: null,
    docBusy: false,

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
        const tiers = plans?.tiers || []
        if (!this.tier && tiers.length) this.tier = (tiers.find((t) => t.popular) || tiers[0]).id
        // Already paid for: nothing to buy — show where it stands.
        if (!Emp.awaitingPayment(job)) this.submitted = job
      } catch (e) { this.loadError = e.message || "Couldn't load the plans. Check your connection and try again." }
      this.loading = false
    },

    get credits() { return (this.plans?.credits || []).filter((c) => c.remaining > 0) },
    get quantities() { return this.plans?.quantities || [1] },
    get largestBucket() { const q = this.quantities; return q[q.length - 1] || 1 },
    get maxQuantity() { return this.plans?.max_quantity || 20 },

    unitPrice(tier) {
      const q = this.quantity
      const bucket = q > this.largestBucket ? this.largestBucket : q
      return this.plans?.prices?.[tier]?.[REACH]?.[String(bucket)] || null
    },
    get unit() { return this.unitPrice(this.tier) },
    get tierName() { return (this.plans?.tiers || []).find((t) => t.id === this.tier)?.name || '' },
    get totalPaise() { return this.unit ? this.unit.price_paise * this.quantity : null },
    get mrpPaise() { return this.unit ? (this.unit.mrp_paise || this.unit.price_paise) * this.quantity : null },
    get savingPaise() { return this.totalPaise == null ? 0 : Math.max(0, this.mrpPaise - this.totalPaise) },

    quantityOn(q) { return q === this.largestBucket ? this.quantity >= q : this.quantity === q },
    quantityLabel(q) { return q === this.largestBucket ? q + '+ postings' : (q === 1 ? '1 posting' : q + ' postings') },
    changeQuantity(delta) { this.quantity = Math.min(this.maxQuantity, Math.max(this.largestBucket, this.quantity + delta)) },

    /** The API refuses to take payment until the company has its document. */
    get needsDoc() { return !!this.org && !this.org.document_name },

    async uploadDoc(ev) {
      const file = ev.target.files && ev.target.files[0]
      ev.target.value = ''
      if (!file || this.docBusy) return
      const problem = Emp.fileProblem('doc', file)
      if (problem) { Inthes.toast(problem, true); return }
      if (this.org.verified) {
        const ok = await Inthes.confirm({
          title: 'Upload a new document?', message: `${this.org.name} is verified. A new document sends it back for verification, and its live jobs are hidden until it is checked.`,
          ok: 'Upload', danger: true,
        })
        if (!ok) return
      }
      this.docBusy = true
      try {
        const res = await Inthes.upload(`/recruiter/organisations/${encodeURIComponent(this.org.id)}/document`, file)
        this.org = Object.assign({}, this.org, { document_name: res.data?.document_name || file.name, review_state: 'pending', verified: false })
        Inthes.toast('Company document uploaded')
      } catch (e) { Inthes.toast(e.message, true) }
      this.docBusy = false
    },

    async pay() {
      const total = this.totalPaise
      if (total == null || this.paying) return
      if (this.needsDoc) { Inthes.toast('Upload your company document first — it is needed to post a job.', true); return }
      const method = await Inthes.pay(Inthes.rupees(total))
      if (!method) return
      this.paying = true
      try {
        const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.job.id)}/orders`, {
          method: 'POST', body: { tier: this.tier, reach: REACH, quantity: this.quantity, method },
        })
        const order = res.data?.order
        if (!order?.id) throw new Error("Couldn't reach the payment service. Please try again.")
        await Inthes.confirmOrder(order.id)
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
      if (this.needsDoc) { Inthes.toast('Upload your company document first — it is needed to post a job.', true); return }
      this.paying = true
      try {
        const res = await Inthes.api(`/recruiter/jobs/${encodeURIComponent(this.job.id)}/use-credit`, { method: 'POST', body: { credit_id: credit.id } })
        this.submitted = res.data
        const left = credit.remaining - 1
        Inthes.toast(left > 0 ? `Posted on your existing plan — ${left} posting${left === 1 ? '' : 's'} left` : 'Posted on your existing plan')
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
