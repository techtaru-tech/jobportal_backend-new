/**
 * /employer/companies — the companies an employer posts for
 * (recruiter_profile organisations_view + organisation_form_sheet): their
 * verification state, business document, and add / edit / remove. The
 * document is uploaded from a company's card only while a paid post of its
 * waits on it, as in the app (the form has no document, GST or logo field). `?doc=<organisationId>` scrolls to that company's card.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('empCompanies', () => ({
    ...Emp.companyForm(),
    loading: true,
    error: '',
    orgs: [],
    jobs: [], // only to tell which companies a paid post is waiting on
    formOpen: false,
    formBusy: false,
    docBusy: '',

    async init() {
      await Inthes.whenReady()
      if (Alpine.store('auth').signedIn) this.load(true)
      window.addEventListener('inthes:signed-in', () => this.load(true))
    },

    async load(first = false) {
      if (first) this.loading = true
      this.error = ''
      try {
        const [orgs, jobs] = await Promise.all([Emp.loadOrgs(), Emp.loadMine().catch(() => this.jobs)])
        this.orgs = orgs
        this.jobs = jobs
      } catch (e) { this.error = e.message }
      this.loading = false
      if (first) {
        const id = new URLSearchParams(location.search).get('doc')
        const org = id && this.orgs.find((o) => o.id === id)
        if (org) this.$nextTick(() => setTimeout(() => document.querySelector(`[data-org="${CSS.escape(org.id)}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 250))
      }
    },

    /** The document is asked for only once a paid post of the company's waits on it (organisations_view). */
    needsDocument(org) { return Emp.orgNeedsDocument(org, this.jobs) },
    stateLabel(org) { return Emp.orgStateLabel(org, this.needsDocument(org)) },
    statePill: Emp.orgStatePill,
    notice(org) { return Emp.orgNotice(org, this.needsDocument(org)) },

    openForm(org = null) {
      this.coReset(org)
      this.formOpen = true
    },

    closeForm() {
      if (this.formBusy) return
      this.formOpen = false
      const url = new URL(location.href)
      if (url.searchParams.has('doc')) { url.searchParams.delete('doc'); history.replaceState(null, '', url) }
    },

    async saveForm() {
      if (this.formBusy) return
      if (!this.coValidate()) {
        this.$nextTick(() => document.querySelector('[data-company-dialog] .wa-error:not([style*="display: none"])')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
        return
      }
      const isNew = !this.coSaved
      this.formBusy = true
      try {
        await this.coSave()
        this.formBusy = false
        this.closeForm()
        Inthes.toast(isNew ? 'Company added' : 'Company updated')
        // The list's counts and review states come from the server.
        await this.load()
      } catch (e) {
        Inthes.toast(e.message, true)
        this.formBusy = false
      }
    },

    async remove(org) {
      const ok = await Inthes.confirm({
        title: 'Remove company?',
        message: "Jobs already posted for this company stay live — you just won't be able to pick it for new posts.",
        ok: 'Remove', danger: true,
      })
      if (!ok) return
      try {
        await Inthes.api(`/recruiter/organisations/${encodeURIComponent(org.id)}`, { method: 'DELETE' })
        this.orgs = this.orgs.filter((o) => o.id !== org.id)
        Inthes.toast('Company removed')
      } catch (e) { Inthes.toast(e.message, true) }
    },

    /** A fresh signed link each time — the one in the list may be old. */
    async viewDocument(org) {
      const win = window.open('', '_blank')
      try {
        const list = await Emp.loadOrgs()
        const url = (list.find((o) => o.id === org.id) || org).document_url
        if (!url) throw new Error('No document uploaded yet')
        if (win) win.location.href = url
        else window.location.href = url
      } catch (e) {
        if (win) win.close()
        Inthes.toast(e.message, true)
      }
    },

    /** "Upload business document" on a card a paid post is waiting on. */
    async uploadDoc(org, ev) {
      const file = ev.target.files && ev.target.files[0]
      ev.target.value = ''
      if (!file || this.docBusy) return
      const problem = Emp.fileProblem('doc', file)
      if (problem) { Inthes.toast(problem, true); return }
      this.docBusy = org.id
      try {
        await Inthes.upload(`/recruiter/organisations/${encodeURIComponent(org.id)}/document`, file)
        Inthes.toast("Document received — we'll verify it and then make your job live")
        await this.load()
      } catch (e) { Inthes.toast(e.message, true) }
      this.docBusy = ''
    },
  }))
})
