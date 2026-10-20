@extends('site.employer.layout')

@section('title', 'Contact details — Inthes employer')
@section('employer_key', 'settings')

@section('employer')
<div x-data="empSettings()" class="max-w-[720px]">
  <h1 class="text-h1 text-ink">Contact details</h1>
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
        <button type="button" class="wa-btn-quiet" x-show="dirty" @click="form = Inthes.clone(saved); errors = {}">Discard</button>
        <button type="submit" class="wa-btn-sm min-w-[120px]" :disabled="saving || !dirty">
          <span x-show="saving" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span>
          Save
        </button>
      </div>
    </form>
  </template>
</div>
@include('site.employer.partials.script', ['name' => 'employer-settings'])
@endsection
