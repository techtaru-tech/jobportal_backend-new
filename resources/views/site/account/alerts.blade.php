@extends('site.account.layout')

@section('title', 'Job alerts — Inthes')
@section('account_key', 'alerts')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-alerts.js') }}?v={{ filemtime(public_path('js/site/pages/account-alerts.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountAlerts()">
  <div class="mb-lg">
    <h1 class="text-h2 text-ink">Job alerts</h1>
    <p class="mt-xs text-bodysm text-ink-secondary">We'll notify you the moment a matching job goes live. Keep up to 10.</p>
  </div>

  <div class="grid gap-lg xl:grid-cols-[minmax(0,1fr)_360px]">
    {{-- Create --}}
    <form @submit.prevent="create()" class="wa-card self-start xl:order-2 xl:sticky xl:top-[92px]">
      <h2 class="text-h4 text-ink">Create an alert</h2>
      <p class="mt-[2px] text-caption text-ink-secondary">Leave a field blank to match anything.</p>
      <div class="mt-lg grid gap-md sm:grid-cols-2 xl:grid-cols-1">
        @include('site.ui.select', ['label' => 'Job category', 'model' => 'form.role', 'items' => "opt('categories')", 'placeholder' => 'Any category'])
        @include('site.ui.select', ['label' => 'City', 'model' => 'form.city', 'items' => "opt('cities')", 'placeholder' => 'Any city'])
      </div>
      <div class="mt-md">
        @include('site.ui.input', ['label' => 'Keyword', 'model' => 'form.keyword', 'placeholder' => 'e.g. ICU, night shift', 'attrs' => 'maxlength=120'])
      </div>
      <p x-show="isCatchAll && !full" class="mt-md text-caption text-warning">You'll be notified about every new job.</p>
      <p x-show="full" x-cloak class="mt-md text-caption text-danger">You can keep up to 10 job alerts. Delete one to add another.</p>
      <button type="submit" :disabled="saving || full" class="wa-btn mt-lg w-full">
        <span x-show="saving" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
        Create alert
      </button>
    </form>

    {{-- List --}}
    <section class="min-w-0 xl:order-1">
      <div class="mb-md flex items-center justify-between">
        <p class="wa-section-title">Your alerts</p>
        <p x-show="items.length" class="text-caption text-ink-muted" x-text="items.length + ' / ' + max"></p>
      </div>
      <template x-if="!loaded">@include('site.ui.spinner')</template>
      <template x-if="loaded && error">
        <div>@include('site.ui.empty', ['icon' => 'alert', 'title' => 'Something went wrong', 'text' => 'Check your connection and try again.'])</div>
      </template>
      <template x-if="loaded && !error && !items.length">
        <div>@include('site.ui.empty', ['icon' => 'bell', 'title' => 'No alerts yet', 'text' => "Create one and we'll notify you the moment a matching job goes live."])</div>
      </template>
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card" x-show="items.length" x-cloak>
        <ul class="divide-y divide-hairline-divider">
          <template x-for="a in items" :key="a.id">
            <li class="flex items-center gap-md px-lg py-md">
              <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="a.is_active ? 'bg-primary-light text-primary' : 'bg-surface-muted text-ink-muted'">
                @include('admin.partials.icon', ['name' => 'bell', 'class' => 'h-5 w-5'])
              </span>
              <div class="min-w-0 flex-1">
                <a :href="browseUrl(a)" class="block truncate text-h5 text-ink hover:text-primary-dark" x-text="a.summary" title="See matching jobs"></a>
                <div class="mt-[2px] flex flex-wrap items-center gap-sm">
                  <span class="wa-pill" :class="a.is_active ? 'bg-primary-light text-primary-dark' : 'border-hair border-hairline-strong text-ink-secondary'" x-text="a.is_active ? 'Active' : 'Paused'"></span>
                  <span class="text-caption text-ink-muted" x-text="a.last_notified_at ? 'Last match ' + timeAgo(a.last_notified_at) : 'No matches yet'"></span>
                </div>
              </div>
              <button type="button" @click="toggle(a)" :title="a.is_active ? 'Pause alert' : 'Resume alert'" :aria-label="a.is_active ? 'Pause alert' : 'Resume alert'"
                      class="inline-flex h-9 shrink-0 items-center gap-xs rounded-field px-sm text-btnghost font-semibold text-ink-secondary hover:bg-surface-muted">
                <span x-html="a.is_active ? ICONS.pause : ICONS.play" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                <span class="hidden sm:inline" x-text="a.is_active ? 'Pause' : 'Resume'"></span>
              </button>
              <button type="button" @click="remove(a)" title="Delete alert" aria-label="Delete alert"
                      class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-field text-danger hover:bg-danger-bg">
                @include('admin.partials.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])
              </button>
            </li>
          </template>
        </ul>
      </div>
    </section>
  </div>
</div>
@endsection
