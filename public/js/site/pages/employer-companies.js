/**
 * /employer/companies — the companies an employer posts for
 * (recruiter_profile organisations_view + organisation_form_sheet): their
 * verification state, company document, logo, and add / edit / remove.
 * `?doc=<organisationId>` (from a "Upload document" notice) opens that
 * company's form at its document.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('empCompanies', () => ({
    ...Emp.companyForm(),
    loading: true,
    error: '',
    orgs: [],
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
      try { this.orgs = await Emp.loadOrgs() } catch (e) { this.error = e.message }
      this.loading = false
      if (first) {
        const id = new URLSearchParams(location.search).get('doc')
        const org = id && this.orgs.find((o) => o.id === id)
        if (org) {
          this.openForm(org)
          this.$nextTick(() => setTimeout(() => document.querySelector('[data-field="co.doc"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 250))
        }
      }
    },

    stateLabel: Emp.orgStateLabel,
    statePill: Emp.orgStatePill,
    notice: Emp.orgNotice,

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
      if (!isNew && this.co.doc_new && this.coSaved.verified) {
        const ok = await Inthes.confirm({
          title: 'Replace the company document?',
          message: `${this.coSaved.name} is verified. A new document sends it back for verification, and its jobs are hidden from candidates until it is checked.`,
          ok: 'Replace & save', danger: true,
        })
        if (!ok) return
      }
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

    /** Upload / replace the document straight from the card. */
    async uploadDoc(org, ev) {
      const file = ev.target.files && ev.target.files[0]
      ev.target.value = ''
      if (!file || this.docBusy) return
      const problem = Emp.fileProblem('doc', file)
      if (problem) { Inthes.toast(problem, true); return }
      if (org.verified) {
        const ok = await Inthes.confirm({
          title: 'Replace the company document?',
          message: `${org.name} is verified. A new document sends it back for verification, and its jobs are hidden from candidates until it is checked.`,
          ok: 'Replace', danger: true,
        })
        if (!ok) return
      }
      this.docBusy = org.id
      try {
        await Inthes.upload(`/recruiter/organisations/${encodeURIComponent(org.id)}/document`, file)
        Inthes.toast('Company document uploaded — we’ll verify it shortly')
        await this.load()
      } catch (e) { Inthes.toast(e.message, true) }
      this.docBusy = ''
    },
  }))
})
