{{-- Companies (recruiter_profile/views/organisations_view.dart + widgets/organisation_form_sheet.dart). --}}
<template x-if="is('rOrgs')">
  <div class="wa-narrow">
    @include('webapp.ui.head', ['title' => 'Companies', 'back' => '/r/profile'])

    <template x-if="!rOrgsLoaded">
      <div>@include('webapp.ui.spinner')</div>
    </template>

    <template x-if="rOrgsLoaded && rOrgs.length === 0">
      <div>@include('webapp.ui.empty', ['icon' => 'building', 'title' => 'No companies added yet', 'text' => 'Your company is added when you post your first job — or add it here with its company document.'])</div>
    </template>

    <div class="space-y-md">
      <template x-for="org in rOrgs" :key="org.id">
        <div class="wa-card">
          <div class="flex items-start gap-md">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-[12px] bg-primary-light text-h4 text-primary">
              <template x-if="org.logo_url"><img :src="org.logo_url" alt="" class="h-full w-full object-cover"></template>
              <template x-if="!org.logo_url"><span x-text="(org.name || '?').trim().charAt(0).toUpperCase()"></span></template>
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-h5 text-ink" x-text="org.name"></p>
              <p class="line-clamp-2 text-bodysm text-ink-secondary" x-show="org.industry || org.address" x-text="[org.industry, org.address].filter(Boolean).join(' · ')"></p>
              <p class="text-caption text-ink-muted" x-text="(org.job_count || 0) === 0 ? 'No jobs posted yet' : org.job_count + ' job' + (org.job_count === 1 ? '' : 's') + ' posted'"></p>
            </div>
          </div>
          <div class="mt-sm flex flex-wrap gap-sm">
            <span class="wa-tag" :class="org.verified ? 'bg-primary-light text-primary-dark' : 'border border-hairline-strong bg-surface'" x-text="rOrgStateLabel(org)"></span>
            <span class="wa-tag" x-show="org.hidden_postings > 0" x-text="org.hidden_postings + (org.hidden_postings === 1 ? ' job' : ' jobs') + ' hidden'"></span>
          </div>
          <p class="mt-sm flex items-center gap-xs truncate text-caption" :class="rOrgNotice(org).cls">
            <span x-html="ICONS[rOrgNotice(org).icon]" class="shrink-0 [&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
            <span class="truncate" x-text="rOrgNotice(org).text"></span>
          </p>
          <div class="mt-md flex items-center gap-xs border-t border-hairline-divider pt-sm">
            <template x-if="org.document_name">
              <button type="button" class="wa-btn-quiet -ml-md min-w-0" @click="rViewDocument(org)">
                <span x-html="ICONS.fileText" class="shrink-0 [&>svg]:h-4 [&>svg]:w-4"></span><span class="truncate">View document</span>
              </button>
            </template>
            <span class="flex-1"></span>
            <button type="button" class="wa-btn-quiet" @click="rOpenOrgForm(org)"><span x-html="ICONS.pencil" class="[&>svg]:h-4 [&>svg]:w-4"></span>Edit</button>
            <button type="button" class="wa-btn-quiet text-danger hover:text-danger" @click="rRemoveOrg(org)"><span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span>Remove</button>
          </div>
        </div>
      </template>
    </div>

    <button type="button" class="wa-btn-ghost mt-md w-full" x-show="rOrgsLoaded" @click="rOpenOrgForm()">+ Add company</button>

    {{-- Add / Edit company --}}
    <div x-show="rOrgForm.open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
      <div class="flex max-h-[94vh] w-full max-w-[560px] flex-col rounded-t-sheet bg-surface shadow-raised sm:rounded-dialog">
        <div class="flex items-center gap-md border-b border-hairline-divider px-xl py-lg">
          <h3 class="flex-1 text-h2 text-ink" x-text="rOrgForm.initial ? 'Edit company' : 'Add company'"></h3>
          <template x-if="rOrgForm.initial">
            <span class="wa-tag" :class="rOrgForm.initial.verified && 'bg-primary-light text-primary-dark'" x-text="rOrgForm.initial.verified ? 'Verified' : 'Pending verification'"></span>
          </template>
          <button type="button" @click="rCloseOrgForm()" aria-label="Close" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary hover:bg-surface-muted">
            @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
          </button>
        </div>
        <div class="thin-scrollbar flex-1 overflow-y-auto px-xl py-lg">
          <template x-if="rOrgForm.open">
            @include('webapp.screens.recruiter.company-form', ['ctx' => 'org'])
          </template>
        </div>
        <div class="border-t border-hairline-divider px-xl py-lg">
          <button type="button" class="wa-btn w-full" :disabled="rOrgForm.busy" @click="rSaveOrgForm()">
            <span x-show="rOrgForm.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            <span x-text="rOrgForm.initial ? 'Save changes' : 'Add company'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
