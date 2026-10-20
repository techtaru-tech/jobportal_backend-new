@extends('site.employer.layout')

@section('title', 'Companies — Inthes employer')
@section('employer_key', 'companies')

@section('employer')
<div x-data="empCompanies()" @keydown.escape.window="formOpen && closeForm()">
  <div class="mb-xl flex flex-wrap items-center justify-between gap-md">
    <div>
      <h1 class="text-h1 text-ink">Companies</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">The companies you hire for. Each is verified from its company document before its jobs go live.</p>
    </div>
    <button type="button" class="wa-btn-sm" x-show="!loading" @click="openForm()">@include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4']) Add company</button>
  </div>

  <template x-if="loading">@include('site.ui.spinner')</template>
  <template x-if="!loading && error">
    <div class="wa-card text-center"><p class="text-bodysm text-danger" x-text="error"></p><button type="button" class="wa-btn-ghost-sm mt-md" @click="load(true)">Try again</button></div>
  </template>
  <template x-if="!loading && !error && !orgs.length">
    <div>
      @include('site.ui.empty', ['icon' => 'building', 'title' => 'No companies added yet', 'text' => 'Your company is added when you post your first job — or add it here with its company document.'])
      <div class="mt-lg text-center"><button type="button" class="wa-btn" @click="openForm()">Add company</button></div>
    </div>
  </template>

  <div class="grid gap-lg md:grid-cols-2" x-show="!loading && orgs.length">
    <template x-for="org in orgs" :key="org.id">
      <article class="wa-card flex flex-col !p-lg">
        <div class="flex items-start gap-md">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-[12px] bg-primary-light text-h4 text-primary-dark">
            <template x-if="org.logo_url"><img :src="org.logo_url" alt="" class="h-full w-full object-cover"></template>
            <template x-if="!org.logo_url"><span x-text="initials(org.name)"></span></template>
          </span>
          <div class="min-w-0 flex-1">
            <p class="flex items-center gap-xs text-h4 text-ink">
              <span class="truncate" x-text="org.name"></span>
              <span x-show="org.verified" class="shrink-0 text-primary" title="Verified">@include('admin.partials.icon', ['name' => 'badgeCheck', 'class' => 'h-4 w-4'])</span>
            </p>
            <p class="line-clamp-2 text-bodysm text-ink-secondary" x-text="[org.industry, org.size && org.size + ' company', org.address].filter(Boolean).join(' · ') || 'Details not filled in'"></p>
            <p class="mt-[2px] text-caption text-ink-muted" x-text="(org.job_count || 0) === 0 ? 'No jobs posted yet' : org.job_count + ' job' + (org.job_count === 1 ? '' : 's') + ' posted'"></p>
          </div>
          <span class="wa-pill shrink-0" :class="statePill(org)" x-text="stateLabel(org)"></span>
        </div>

        <p class="mt-md flex items-center gap-xs text-caption" :class="notice(org).cls">
          <span x-html="ICONS[notice(org).icon]" class="shrink-0 [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
          <span x-text="notice(org).text"></span>
        </p>

        {{-- document --}}
        <div class="mt-md flex items-center gap-sm rounded-field bg-surface-muted px-md py-sm">
          <span class="shrink-0 [&>svg]:h-[18px] [&>svg]:w-[18px]" :class="org.document_name || org.verified ? 'text-ink-secondary' : 'text-primary'" x-html="org.document_name || org.verified ? ICONS.fileText : ICONS.alert"></span>
          <span class="min-w-0 flex-1 truncate text-bodysm" :class="org.document_name || org.verified ? 'text-ink-secondary' : 'font-semibold text-primary-dark'" x-text="org.document_name || (org.verified ? 'No document on file — needed for new posts' : 'Company document required')"></span>
          <template x-if="org.document_name">
            <button type="button" class="shrink-0 text-btnghost text-ink-secondary hover:text-ink" @click="viewDocument(org)">View</button>
          </template>
          <label class="shrink-0 cursor-pointer text-btnghost text-primary hover:underline">
            <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="uploadDoc(org, $event)" :disabled="!!docBusy">
            <span x-text="docBusy === org.id ? 'Uploading…' : (org.document_name ? 'Replace' : 'Upload')"></span>
          </label>
        </div>

        <div class="mt-auto flex items-center gap-xs border-t border-hairline-divider pt-sm" style="margin-top: 16px">
          <span x-show="org.hidden_postings > 0" class="wa-tag" x-text="org.hidden_postings + (org.hidden_postings === 1 ? ' job' : ' jobs') + ' hidden'"></span>
          <span class="flex-1"></span>
          <button type="button" class="wa-btn-quiet" @click="openForm(org)">@include('admin.partials.icon', ['name' => 'pencil', 'class' => 'h-4 w-4']) Edit</button>
          <button type="button" class="wa-btn-quiet text-danger hover:bg-danger-bg hover:text-danger" @click="remove(org)">@include('admin.partials.icon', ['name' => 'trash', 'class' => 'h-4 w-4']) Remove</button>
        </div>
      </article>
    </template>
  </div>

  {{-- Add / Edit company --}}
  <div x-show="formOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[80] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" data-company-dialog>
    <div class="flex max-h-[100dvh] w-full max-w-[680px] flex-col rounded-t-sheet bg-surface shadow-raised sm:max-h-[92vh] sm:rounded-dialog" role="dialog" aria-modal="true">
      <div class="flex items-center gap-md border-b border-hairline-divider px-xl py-lg">
        <h3 class="flex-1 text-h2 text-ink" x-text="coSaved ? 'Edit company' : 'Add company'"></h3>
        <template x-if="coSaved">
          <span class="wa-pill" :class="statePill(coSaved)" x-text="stateLabel(coSaved)"></span>
        </template>
        <button type="button" @click="closeForm()" aria-label="Close" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary hover:bg-surface-muted">
          @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
        </button>
      </div>
      <form id="company-form" class="thin-scrollbar flex-1 overflow-y-auto px-xl py-lg" @submit.prevent="saveForm()" novalidate>
        <template x-if="formOpen">
          @include('site.employer.partials.company-form')
        </template>
      </form>
      <div class="flex flex-col-reverse gap-sm border-t border-hairline-divider px-xl py-lg sm:flex-row sm:justify-end">
        <button type="button" class="wa-btn-quiet h-[50px]" @click="closeForm()">Cancel</button>
        <button type="submit" form="company-form" class="wa-btn sm:min-w-[200px]" :disabled="formBusy">
          <span x-show="formBusy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
          <span x-text="coSaved ? 'Save changes' : 'Add company'"></span>
        </button>
      </div>
    </div>
  </div>
</div>
@include('site.employer.partials.script', ['name' => 'employer-companies'])
@endsection
