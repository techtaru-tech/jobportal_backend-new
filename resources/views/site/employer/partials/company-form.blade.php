{{-- Every field of a company (core/widgets/company_form_fields.dart), bound to
     the `co` / `coErrors` of Emp.companyForm(), which the page's component
     spreads in. No GST number, document or logo: the business document is
     asked for after a plan is paid for (checkout, Jobs, Companies). Used inline on Post a job and in the
     Companies add/edit dialog. Each block carries `data-field` so a failed
     submit can scroll to the first problem.
     @include('site.employer.partials.company-form', ['compact' => false]) --}}
<div class="space-y-lg">
  <div x-show="Object.keys(coHiringTypes()).length">
    <span class="wa-label">Company type @include('site.ui.mark', ['required' => true])</span>
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
    {{-- An open list: pick one, or add your own (≤ 40 characters). --}}
    <div data-field="co.industry">@include('site.ui.combo', ['label' => 'Industry', 'model' => 'co.industry', 'items' => "opt('organisation_industries')", 'placeholder' => 'Select or add your industry', 'attrs' => 'maxlength=40', 'error' => 'coErrors.industry'])</div>
    <div data-field="co.size">
      <span class="wa-label">Company size @include('site.ui.mark', ['required' => true])</span>
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
</div>
