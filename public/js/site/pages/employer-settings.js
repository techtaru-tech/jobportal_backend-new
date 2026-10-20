/**
 * /employer/settings — the recruiter's contact card (recruiter_contact_view):
 * who candidates should reach, shared across every company. The app checks
 * nothing client-side here; the API's own messages land on their fields.
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('empSettings', () => ({
    loading: true,
    loadError: '',
    saving: false,
    form: { contact_person_name: '', contact_email: '', contact_phone: '' },
    saved: null,
    errors: {},

    async init() {
      await Inthes.whenReady()
      if (Alpine.store('auth').signedIn) this.load()
      window.addEventListener('inthes:signed-in', () => this.load())
    },

    async load() {
      this.loading = true
      this.loadError = ''
      try {
        const res = await Inthes.api('/recruiter/profile')
        const d = res.data || {}
        // Nulls become '' — the form trims these.
        this.form = { contact_person_name: d.contact_person_name || '', contact_email: d.contact_email || '', contact_phone: d.contact_phone || '' }
        this.saved = Inthes.clone(this.form)
      } catch (e) { this.loadError = e.message }
      this.loading = false
    },

    get dirty() { return JSON.stringify(this.form) !== JSON.stringify(this.saved) },

    async save() {
      if (this.saving) return
      this.saving = true
      this.errors = {}
      try {
        const f = this.form
        const res = await Inthes.api('/recruiter/profile', {
          method: 'PATCH',
          body: { contact_person_name: f.contact_person_name.trim(), contact_email: f.contact_email.trim(), contact_phone: f.contact_phone.trim() },
        })
        const d = res.data || {}
        this.form = { contact_person_name: d.contact_person_name || '', contact_email: d.contact_email || '', contact_phone: d.contact_phone || '' }
        this.saved = Inthes.clone(this.form)
        Inthes.toast('Contact details saved')
      } catch (e) {
        for (const [k, v] of Object.entries(e.errors || {})) this.errors[k] = Array.isArray(v) ? v[0] : v
        Inthes.toast(e.message, true)
      }
      this.saving = false
    },
  }))
})
