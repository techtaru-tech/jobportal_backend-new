{{-- ── Job Alerts ──────────────────────────────────────────────────────
     Standing searches: criteria are ANDed, a blank one matches anything,
     and an alert fires when a matching job goes live. Up to 10. --}}
<template x-if="is('alerts')">
  <div class="wa-narrow">
    @include('webapp.ui.head', ['title' => 'Job Alerts', 'back' => '/saved'])

    <form @submit.prevent="alertCreate()" class="wa-card">
      <h2 class="text-h5 text-ink">Create an alert</h2>
      <p class="mt-[2px] text-caption text-ink-secondary">Leave a field blank to match anything.</p>

      <div class="mt-lg grid gap-md sm:grid-cols-2">
        @include('webapp.ui.select', ['label' => 'Job category', 'model' => 'alertForm.role', 'items' => "opt('categories')", 'placeholder' => 'Any category'])
        @include('webapp.ui.select', ['label' => 'City', 'model' => 'alertForm.city', 'items' => "opt('cities')", 'placeholder' => 'Any city'])
      </div>
      <div class="mt-md">
        @include('webapp.ui.input', ['label' => 'Keyword', 'model' => 'alertForm.keyword', 'placeholder' => 'e.g. ICU, night shift', 'attrs' => 'maxlength=120'])
      </div>

      <p x-show="alertIsCatchAll" class="mt-md text-caption text-warning">You'll be notified about every new job.</p>
      <p x-show="alertItems.length >= alertMax" class="mt-md text-caption text-danger">
        You can keep up to 10 job alerts. Delete one to add another.
      </p>

      <button type="submit" :disabled="alertSaving || alertItems.length >= alertMax" class="wa-btn mt-lg w-full sm:w-auto">
        <span x-show="alertSaving" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
        Create alert
      </button>
    </form>

    <div class="mb-md mt-xxl flex items-center justify-between">
      <p class="wa-section-title">Your alerts</p>
      <p x-show="alertItems.length" class="text-caption text-ink-muted" x-text="alertItems.length + ' / ' + alertMax"></p>
    </div>

    <template x-if="!alertLoaded">
      <div>@include('webapp.ui.spinner')</div>
    </template>

    <template x-if="alertLoaded && alertItems.length === 0">
      <div>@include('webapp.ui.empty', ['icon' => 'bell', 'title' => 'No alerts yet', 'text' => "Create one above and we'll notify you the moment a matching job goes live."])</div>
    </template>

    <div class="space-y-md">
      <template x-for="a in alertItems" :key="a.id">
        <div class="wa-card flex items-start gap-md !p-lg">
          <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                :class="a.is_active ? 'bg-primary-light text-primary' : 'bg-surface-muted text-ink-muted'">
            @include('admin.partials.icon', ['name' => 'bell', 'class' => 'h-5 w-5'])
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-h5 text-ink" x-text="a.summary"></p>
            <div class="mt-xs flex flex-wrap items-center gap-sm">
              <span class="wa-pill" :class="a.is_active ? 'bg-primary-light text-primary-dark' : 'border-hair border-hairline-strong text-ink-secondary'"
                    x-text="a.is_active ? 'Active' : 'Paused'"></span>
              <span class="text-caption text-ink-muted" x-text="a.last_notified_at ? 'Last match ' + timeAgo(a.last_notified_at) : 'No matches yet'"></span>
            </div>
          </div>
          <button type="button" @click="alertToggle(a)" :title="a.is_active ? 'Pause alert' : 'Resume alert'" :aria-label="a.is_active ? 'Pause alert' : 'Resume alert'"
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-field text-ink-secondary hover:bg-surface-muted">
            <span x-html="a.is_active ? ICONS.pause : ICONS.play" class="[&>svg]:h-5 [&>svg]:w-5"></span>
          </button>
          <button type="button" @click="alertDelete(a)" title="Delete alert" aria-label="Delete alert"
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-field text-danger hover:bg-danger-bg">
            @include('admin.partials.icon', ['name' => 'trash', 'class' => 'h-5 w-5'])
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
