@extends('site.employer.layout')

@section('title', 'Contact details — Inthes employer')
@section('employer_key', 'settings')

@section('employer')
<div x-data="empSettings()" class="max-w-[720px]">
  {{-- A new employer account's first stop (?welcome=1). --}}
  <template x-if="welcome">
    <div class="mb-xl overflow-hidden rounded-card border-hair border-primary-line bg-surface shadow-card" data-employer-welcome>
      <div class="hero-wash px-xl py-xl">
        <p class="wa-section-title text-primary">Employer account</p>
        <h1 class="mt-xs text-h1 text-ink">Welcome! Let's set up your employer account</h1>
        <p class="mt-xs max-w-[560px] text-bodysm text-ink-secondary">This account is only for hiring — it's separate from any job seeker account on your number. Tell candidates who to contact, then post your first job; you'll add your company details there.</p>
        <ol class="mt-lg flex flex-wrap gap-x-xl gap-y-sm text-caption text-ink-secondary">
          <li class="flex items-center gap-xs font-semibold text-ink"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[11px] text-ink-onPrimary">1</span>Contact details</li>
          <li class="flex items-center gap-xs"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-surface-muted text-[11px]">2</span>Company &amp; first job</li>
          <li class="flex items-center gap-xs"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-surface-muted text-[11px]">3</span>Applicants on your dashboard</li>
        </ol>
      </div>
    </div>
  </template>
  <h1 x-show="!welcome" class="text-h1 text-ink">Contact details</h1>
  <h2 x-show="welcome" x-cloak class="text-h2 text-ink">Contact details</h2>
  <p class="mt-xs text-bodysm text-ink-secondary">Who candidates should reach out to. Shared across every company you post jobs for.</p>

  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && loadError">
    <div class="wa-card mt-xl text-center"><p class="text-bodysm text-danger" x-text="loadError"></p><button type="button" class="wa-btn-ghost-sm mt-md" @click="load()">Try again</button></div>
  </template>

  <template x-if="!loading && !loadError">
    <form class="wa-card mt-xl space-y-lg" @submit.prevent="save()" novalidate>
      @include('site.ui.input', ['label' => 'Contact person', 'model' => 'form.contact_person_name', 'placeholder' => 'Who candidates should ask for', 'attrs' => 'autocomplete=name maxlength=120', 'error' => 'errors.contact_person_name'])
      <div class="grid gap-lg sm:grid-cols-2">
        @include('site.ui.input', ['label' => 'Contact email', 'model' => 'form.contact_email', 'type' => 'email', 'placeholder' => 'hr@yourcompany.com', 'attrs' => 'autocomplete=email', 'error' => 'errors.contact_email'])
        @include('site.ui.input', ['label' => 'Contact phone', 'model' => 'form.contact_phone', 'type' => 'tel', 'placeholder' => '98765 43210', 'attrs' => 'autocomplete=tel inputmode=tel', 'error' => 'errors.contact_phone'])
      </div>
      <p class="text-caption text-ink-muted">The phone number is also filled in as the call number when you post a job.</p>
      <div class="flex items-center justify-end gap-sm border-t border-hairline-divider pt-lg">
        <button type="button" class="wa-btn-quiet" x-show="dirty && !welcome" @click="form = Inthes.clone(saved); errors = {}">Discard</button>
        <button type="button" class="wa-btn-quiet" x-show="welcome" @click="finishWelcome()">Skip for now</button>
        <button type="submit" class="wa-btn-sm min-w-[120px]" :disabled="saving || (!dirty && !welcome)" data-settings-save>
          <span x-show="saving" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span>
          <span x-text="welcome ? 'Save & continue' : 'Save'">Save</span>
        </button>
      </div>
    </form>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-settings'])
@endsection
