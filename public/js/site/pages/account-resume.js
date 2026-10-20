/**
 * /account/resume — the resume built from the profile: preview, download,
 * share, "Create my resume" when there is none, and the ₹20 watermark-free
 * one-off (method sheet → order → confirm → re-read).
 */
document.addEventListener('alpine:init', () => {
  const K = window.ProfileKit

  Alpine.data('accountResume', () => K.merge(K.mixin(), {
    oneOffs: [],
    buying: false,
    /** A blob: copy of the PDF for the preview — the file itself is served sandboxed, which Chrome won't frame. */
    previewUrl: '',
    previewFor: '',
    previewFailed: false,
    loaded: false,

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
      this.loaded = true
      // Priced server-side; failing quietly leaves the free download working.
      Inthes.api('/payments/one-off').then((r) => { this.oneOffs = r.data?.purchases || [] }).catch(() => {})
      this.$watch('me', () => this.preview())
      this.preview()
    },

    async preview() {
      const url = this.me?.resume_url
      if (!url || url === this.previewFor) return
      this.previewFor = url
      this.previewFailed = false
      try {
        const res = await fetch(url)
        if (!res.ok) throw new Error()
        const blob = await res.blob()
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl)
        this.previewUrl = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' })) + '#toolbar=0&navpanes=0&view=FitH'
      } catch (e) {
        this.previewFailed = true
      }
    },

    get cleanOffer() {
      if (!this.me || this.me.resume_watermark_free) return null
      return this.oneOffs.find((o) => o.id === 'resume_watermark_free') || null
    },

    /** Where each missing piece is fixed on the profile page. */
    fixLink(m) {
      if (m === 'your name') return { href: '/account#personal', label: 'Add name' }
      if (m === 'your qualification') return { href: '/account#education', label: 'Add education' }
      return { href: '/account#experience', label: 'Add experience' }
    },

    async buyClean() {
      const offer = this.cleanOffer
      if (!offer || this.buying) return
      const method = await Inthes.pay(offer.price_label || Inthes.rupees(offer.price_paise))
      if (!method) return
      this.buying = true
      try {
        const res = await Inthes.api('/payments/orders', { method: 'POST', body: { audience: 'jobSeeker', plan_id: offer.id, method } })
        const order = res.data?.order || res.data
        await Inthes.confirmOrder(order.id)
        // The entitlement is the server's answer — nothing changes until re-read.
        await Inthes.loadMe()
        Inthes.toast('Your resume downloads without the INTHES mark.')
      } catch (e) {
        Inthes.toast(e.message || "That payment didn't go through.", true)
      }
      this.buying = false
    },
  }))
})
