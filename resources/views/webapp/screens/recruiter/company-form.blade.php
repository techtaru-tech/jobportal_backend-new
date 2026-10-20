{{-- Every field of a company, in one place — used by both Post a Job ▸ Company
     details and Companies ▸ Add/Edit company (core/widgets/company_form_fields.dart).
     @include('webapp.screens.recruiter.company-form', ['ctx' => 'post'])   // or 'org'
     Binds to rCo(ctx); files go through rPickFile / rRemoveFile. --}}
@php
  $co = $ctx === 'post' ? 'rPost.co' : 'rOrgForm.co';
  $sizeHints = ['Small' => 'Up to 50 people', 'Mid' => '51–500 people', 'Large' => '501+ people'];
@endphp
<div class="space-y-lg">
  <div x-show="Object.keys(rHiringTypes).length">
    <span class="wa-label">Company type<span class="text-primary"> *</span></span>
    <div class="space-y-sm">
      <template x-for="[key, t] in Object.entries(rHiringTypes)" :key="key">
        <button type="button" @click="{{ $co }}.hiring_type = key"
                :class="{{ $co }}.hiring_type === key ? 'border-primary bg-primary-light' : 'border-hairline bg-surface-muted hover:bg-hairline/60'"
                class="flex w-full items-center gap-md rounded-field border-chip px-lg py-md text-left transition-colors">
          <span x-html="key === 'consultancy' ? ICONS.users : ICONS.building"
                :class="{{ $co }}.hiring_type === key ? 'text-primary' : 'text-ink-secondary'" class="shrink-0 [&>svg]:h-5 [&>svg]:w-5"></span>
          <span class="min-w-0 flex-1">
            <span class="block text-bodysm font-semibold text-ink" x-text="t.label"></span>
            <span class="block text-caption text-ink-secondary" x-show="t.description" x-text="t.description"></span>
          </span>
          <span x-show="{{ $co }}.hiring_type === key" x-html="ICONS.checkCircle" class="shrink-0 text-primary [&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
      </template>
    </div>
  </div>

  @include('webapp.ui.input', ['label' => 'Company name', 'model' => "$co.name", 'placeholder' => 'Enter your company name', 'required' => true, 'attrs' => 'maxlength=150'])
  @include('webapp.ui.input', ['label' => 'Company email', 'model' => "$co.email", 'type' => 'email', 'placeholder' => 'Enter your company email', 'required' => true, 'attrs' => 'autocomplete=email'])
  @include('webapp.ui.select', ['label' => 'Industry', 'model' => "$co.industry", 'items' => "opt('organisation_industries')", 'placeholder' => 'Select your industry'])

  <div>
    <span class="wa-label">Company size<span class="text-primary"> *</span></span>
    <div class="grid grid-cols-3 gap-sm">
      <template x-for="s in rCompanySizes" :key="s">
        <button type="button" @click="{{ $co }}.size = s"
                :class="{{ $co }}.size === s ? 'border-primary bg-primary-light text-primary-dark' : 'border-hairline bg-surface-muted text-ink hover:bg-hairline/60'"
                class="rounded-field border-chip px-sm py-md text-center transition-colors">
          <span class="block text-bodysm font-semibold" x-text="s"></span>
          <span class="block text-[11px] text-ink-secondary" x-text="(@js($sizeHints))[s] || ''"></span>
        </button>
      </template>
    </div>
  </div>

  @include('webapp.ui.textarea', ['label' => 'About the company', 'model' => "$co.about", 'rows' => 3, 'max' => 400, 'placeholder' => 'What you do, and why candidates should join…'])
  @include('webapp.ui.input', ['label' => 'Address', 'model' => "$co.address", 'placeholder' => 'Building, area, city, state', 'attrs' => 'maxlength=255'])
  @include('webapp.ui.input', ['label' => 'Website', 'model' => "$co.website", 'placeholder' => 'www.yourcompany.com', 'attrs' => 'inputmode=url'])
  @include('webapp.ui.input', ['label' => 'GST / registration number', 'model' => "$co.gst_number", 'placeholder' => 'Builds trust with candidates', 'attrs' => 'maxlength=30'])

  {{-- Company document — required; what gets checked before the company is verified. --}}
  <div>
    <span class="wa-label">Company document<span class="text-primary"> *</span></span>
    <p class="-mt-[2px] mb-sm text-caption text-ink-muted">GST certificate, shop licence or company registration — PDF or photo. This is what gets checked before your company is verified.</p>
    <label :class="{{ $co }}.doc_name ? 'border-success/40 bg-success-bg' : 'border-hairline bg-surface-muted hover:bg-hairline/50'"
           class="flex cursor-pointer items-center gap-md rounded-field border-hair p-md transition-colors">
      <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="rPickFile('{{ $ctx }}', 'doc', $event)">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center [&>svg]:h-6 [&>svg]:w-6" :class="{{ $co }}.doc_name ? 'text-success' : 'text-primary'"
            x-html="{{ $co }}.doc_name ? ICONS.fileText : ICONS.upload"></span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-bodysm font-semibold text-ink" x-text="{{ $co }}.doc_name || 'Upload company document'"></span>
        <span class="block text-caption text-ink-secondary" x-text="{{ $co }}.doc_name ? 'Tap to change' : 'PDF, JPG or PNG'"></span>
      </span>
      <button type="button" x-show="{{ $co }}.doc_new" @click.prevent.stop="rRemoveFile('{{ $ctx }}', 'doc')" aria-label="Remove"
              class="inline-flex h-8 w-8 items-center justify-center rounded-full text-ink-muted hover:bg-surface">
        @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-4 w-4'])
      </button>
    </label>
  </div>

  <div>
    <span class="wa-label">Company logo</span>
    <p class="-mt-[2px] mb-sm text-caption text-ink-muted">Shown on every job you post.</p>
    <label :class="rHasLogo({{ $co }}) ? 'border-success/40 bg-success-bg' : 'border-hairline bg-surface-muted hover:bg-hairline/50'"
           class="flex cursor-pointer items-center gap-md rounded-field border-hair p-md transition-colors">
      <input type="file" class="sr-only" accept=".jpg,.jpeg,.png,image/jpeg,image/png" @change="rPickFile('{{ $ctx }}', 'logo', $event)">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-[10px] bg-primary-light text-primary">
        <template x-if="{{ $co }}.logo_preview || ({{ $co }}.logo && {{ $co }}.logo_url)">
          <img :src="{{ $co }}.logo_preview || {{ $co }}.logo_url" alt="" class="h-full w-full object-cover">
        </template>
        <template x-if="!({{ $co }}.logo_preview || ({{ $co }}.logo && {{ $co }}.logo_url))">
          <span x-html="ICONS.image" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </template>
      </span>
      <span class="min-w-0 flex-1">
        <span class="block text-bodysm font-semibold text-ink" x-text="rHasLogo({{ $co }}) ? 'Logo added' : 'Upload company logo'"></span>
        <span class="block text-caption text-ink-secondary" x-text="rHasLogo({{ $co }}) ? 'Tap to change' : 'JPG or PNG'"></span>
      </span>
      <button type="button" x-show="{{ $co }}.logo_new" @click.prevent.stop="rRemoveFile('{{ $ctx }}', 'logo')" aria-label="Remove"
              class="inline-flex h-8 w-8 items-center justify-center rounded-full text-ink-muted hover:bg-surface">
        @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-4 w-4'])
      </button>
    </label>
  </div>
</div>
