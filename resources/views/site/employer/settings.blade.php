@extends('site.employer.layout')

@section('title', 'Contact details — Inthes employer')
@section('employer_key', 'settings')

@section('employer')
<div x-data="empSettings()" class="max-w-[720px]">
  {{-- A new employer account's first stop (?welcome=1) — the app's
       RecruiterContactView: a back arrow, its title and its intro. --}}
  <template x-if="welcome">
    <div data-employer-welcome>
      <div class="flex items-center gap-sm">
        <button type="button" @click="finishWelcome()" aria-label="Back" class="-ml-sm inline-flex h-10 w-10 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
          @include('admin.partials.icon', ['name' => 'arrowLeft', 'class' => 'h-5 w-5'])
        </button>
        <h1 class="text-h2 text-ink">Set up your employer account</h1>
      </div>
      <p class="mt-md text-bodysm text-ink-secondary">This is your employer account — separate from any job seeker account on this number. Tell candidates who to contact; your company is added when you post your first job.</p>
    </div>
  </template>
  <template x-if="!welcome">
    <div>
      <h1 class="text-h1 text-ink">Contact details</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">Who candidates should reach out to. Shared across every company you post jobs for.</p>
    </div>
  </template>

  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && loadError">
    <div class="wa-card mt-xl text-center"><p class="text-bodysm text-danger" x-text="loadError"></p><button type="button" class="wa-btn-ghost-sm mt-md" @click="load()">Try again</button></div>
  </template>

  <template x-if="!loading && !loadError">
    <form class="wa-card mt-xl space-y-lg" @submit.prevent="save()" novalidate>
      @include('site.ui.input', ['label' => 'Contact person', 'required' => true, 'model' => 'form.contact_person_name', 'placeholder' => 'Who candidates should ask for', 'icon' => 'user', 'attrs' => 'autocomplete=name maxlength=120', 'error' => 'errors.contact_person_name'])
      <div class="grid gap-lg">
        @include('site.ui.input', ['label' => 'Contact email', 'model' => 'form.contact_email', 'type' => 'email', 'placeholder' => 'hr@yourcompany.com', 'icon' => 'mail', 'attrs' => 'autocomplete=email', 'error' => 'errors.contact_email'])
        @include('site.ui.input', ['label' => 'Contact phone', 'model' => 'form.contact_phone', 'type' => 'tel', 'placeholder' => '98765 43210', 'icon' => 'phone', 'attrs' => 'autocomplete=tel inputmode=tel', 'error' => 'errors.contact_phone'])
      </div>
      <div class="flex items-center justify-end gap-sm border-t border-hairline-divider pt-lg">
        <button type="button" class="wa-btn-quiet" x-show="dirty && !welcome" @click="form = Inthes.clone(saved); errors = {}">Discard</button>
        <button type="submit" :class="welcome ? 'wa-btn flex-1 sm:flex-none sm:min-w-[240px]' : 'wa-btn-sm min-w-[120px]'" :disabled="saving || (!dirty && !welcome)" data-settings-save>
          <span x-show="saving" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span>
          <span x-text="welcome ? 'Continue to post a job' : 'Save'">Save</span>
        </button>
      </div>
    </form>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-settings'])
@endsection
