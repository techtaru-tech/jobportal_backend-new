{{-- Every field of a company (core/widgets/company_form_fields.dart), bound to
     the `co` / `coErrors` / `coPick` / `coRemove` of Emp.companyForm(), which the
     page's component spreads in. Used inline on Post a job and in the
     Companies add/edit dialog. Each block carries `data-field` so a failed
     submit can scroll to the first problem.
     @include('site.employer.partials.company-form', ['compact' => false]) --}}
<div class="space-y-lg">
  <div x-show="Object.keys(coHiringTypes()).length">
    <span class="wa-label">Company type<span class="text-primary"> *</span></span>
    <div class="grid gap-sm sm:grid-cols-2">
      <template x-for="[key, t] in Object.entries(coHiringTypes())" :key="key">
        <button type="button" @click="co.hiring_type = key"
                :class="co.hiring_type === key ? 'border-primary bg-primary-light' : 'border-hairline bg-surface-muted hover:bg-hairline/60'"
                class="flex w-full items-center gap-md rounded-field border-chip px-lg py-md text-left transition-colors">
          <span x-html="key === 'consultancy' ? ICONS.users : ICONS.building"
                :class="co.hiring_type === key ? 'text-primary' : 'text-ink-secondary'" class="shrink-0 [&>svg]:h-5 [&>svg]:w-5"></span>
          <span class="min-w-0 flex-1">
            <span class="block text-bodysm font-semibold text-ink" x-text="t.label"></span>
            <span class="block text-caption text-ink-secondary" x-show="t.description" x-text="t.description"></span>
          </span>
        </button>
      </template>
    </div>
  </div>

  <div class="grid gap-lg sm:grid-cols-2">
    <div data-field="co.name">@include('site.ui.input', ['label' => 'Company name', 'model' => 'co.name', 'placeholder' => 'Enter your company name', 'required' => true, 'attrs' => 'maxlength=150', 'error' => 'coErrors.name'])</div>
    <div data-field="co.email">@include('site.ui.input', ['label' => 'Company email', 'model' => 'co.email', 'type' => 'email', 'placeholder' => 'Enter your company email', 'required' => true, 'attrs' => 'autocomplete=email', 'error' => 'coErrors.email'])</div>
  </div>

  <div class="grid gap-lg sm:grid-cols-2">
    @include('site.ui.select', ['label' => 'Industry', 'model' => 'co.industry', 'items' => "opt('organisation_industries')", 'placeholder' => 'Select your industry'])
    <div data-field="co.size">
      <span class="wa-label">Company size<span class="text-primary"> *</span></span>
      <div class="grid grid-cols-3 gap-sm">
        <template x-for="s in coSizes()" :key="s">
          <button type="button" @click="co.size = s; coErrors.size = ''"
                  :class="co.size === s ? 'border-primary bg-primary-light text-primary-dark' : 'border-hairline bg-surface-muted text-ink hover:bg-hairline/60'"
                  class="rounded-field border-chip px-xs py-[7px] text-center transition-colors">
            <span class="block text-bodysm font-semibold" x-text="s"></span>
            <span class="block text-[10.5px] leading-tight text-ink-secondary" x-text="SIZE_HINTS[s] || ''"></span>
          </button>
        </template>
      </div>
      <p x-show="coErrors.size" x-text="coErrors.size" class="wa-error"></p>
    </div>
  </div>

  @include('site.ui.textarea', ['label' => 'About the company', 'model' => 'co.about', 'rows' => 3, 'max' => 400, 'placeholder' => 'What you do, and why candidates should join…'])

  <div class="grid gap-lg sm:grid-cols-2">
    @include('site.ui.input', ['label' => 'Address', 'model' => 'co.address', 'placeholder' => 'Building, area, city, state', 'attrs' => 'maxlength=255'])
    <div data-field="co.website">@include('site.ui.input', ['label' => 'Website', 'model' => 'co.website', 'placeholder' => 'www.yourcompany.com', 'attrs' => 'inputmode=url', 'error' => 'coErrors.website'])</div>
  </div>
  @include('site.ui.input', ['label' => 'GST / registration number', 'model' => 'co.gst_number', 'placeholder' => 'Builds trust with candidates', 'attrs' => 'maxlength=30'])

  <div class="grid gap-lg sm:grid-cols-2">
    {{-- Company document — required; what gets checked before the company is verified. --}}
    <div data-field="co.doc">
      <span class="wa-label">Company document<span class="text-primary"> *</span></span>
      <label :class="coErrors.doc ? 'border-danger bg-danger-bg' : (co.doc_name ? 'border-success/40 bg-success-bg' : 'border-hairline bg-surface-muted hover:bg-hairline/50')"
             class="flex cursor-pointer items-center gap-md rounded-field border-hair p-md transition-colors">
        <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="coPick('doc', $event)">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center [&>svg]:h-6 [&>svg]:w-6" :class="co.doc_name ? 'text-success' : 'text-primary'"
              x-html="co.doc_name ? ICONS.fileText : ICONS.upload"></span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-bodysm font-semibold text-ink" x-text="co.doc_name || 'Upload company document'"></span>
          <span class="block text-caption text-ink-secondary" x-text="co.doc_name ? (co.doc_new ? 'Click to change' : 'On file · click to replace') : 'PDF, JPG or PNG · up to 10 MB'"></span>
        </span>
        <button type="button" x-show="co.doc_new" @click.prevent.stop="coRemove('doc')" aria-label="Remove"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-ink-muted hover:bg-surface">
          @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-4 w-4'])
        </button>
      </label>
      <p class="mt-xs text-caption text-ink-muted">GST certificate, shop licence or company registration. This is what gets checked before your company is verified.</p>
      <p x-show="co.doc_new && co.has_doc && co.verified" class="mt-xs text-caption text-warning">A new document sends this company back for verification.</p>
      <p x-show="coErrors.doc" x-text="coErrors.doc" class="wa-error"></p>
    </div>

    <div>
      <span class="wa-label">Company logo</span>
      <label :class="coHasLogo() ? 'border-success/40 bg-success-bg' : 'border-hairline bg-surface-muted hover:bg-hairline/50'"
             class="flex cursor-pointer items-center gap-md rounded-field border-hair p-md transition-colors">
        <input type="file" class="sr-only" accept=".jpg,.jpeg,.png,image/jpeg,image/png" @change="coPick('logo', $event)">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-[10px] bg-primary-light text-primary">
          <template x-if="coLogoSrc()"><img :src="coLogoSrc()" alt="" class="h-full w-full object-cover"></template>
          <template x-if="!coLogoSrc()"><span x-html="ICONS.image" class="[&>svg]:h-5 [&>svg]:w-5"></span></template>
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-bodysm font-semibold text-ink" x-text="coHasLogo() ? 'Logo added' : 'Upload company logo'"></span>
          <span class="block text-caption text-ink-secondary" x-text="coHasLogo() ? 'Click to change' : 'Optional · JPG or PNG · up to 3 MB'"></span>
        </span>
        <button type="button" x-show="co.logo_new" @click.prevent.stop="coRemove('logo')" aria-label="Remove"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-ink-muted hover:bg-surface">
          @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-4 w-4'])
        </button>
      </label>
      <p class="mt-xs text-caption text-ink-muted">Shown on every job you post.</p>
    </div>
  </div>
</div>
