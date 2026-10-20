@extends('site.account.layout')

@section('title', 'Saved jobs — Inthes')
@section('account_key', 'saved')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-saved.js') }}?v={{ filemtime(public_path('js/site/pages/account-saved.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountSaved()">
  <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
    <div>
      <h1 class="text-h2 text-ink">Saved jobs</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">Jobs you bookmarked. Apply when you are ready — they stay here until you remove them.</p>
    </div>
    <a href="{{ url('/account/alerts') }}" class="wa-btn-ghost-sm">@include('admin.partials.icon', ['name' => 'bell', 'class' => 'h-4 w-4'])Job alerts</a>
  </div>

  <template x-if="busy">@include('site.ui.spinner')</template>
  <template x-if="!busy && error">
    <div>@include('site.ui.empty', ['icon' => 'alert', 'title' => 'Something went wrong', 'text' => 'Check your connection and try again.'])</div>
  </template>
  <template x-if="!busy && !error && !list.length">
    <div class="space-y-md text-center">
      @include('site.ui.empty', ['icon' => 'bookmark', 'title' => 'No saved jobs', 'text' => 'Tap the bookmark on any job to keep it here for later.'])
      <a href="{{ route('site.jobs') }}" class="wa-btn-sm">Explore jobs</a>
    </div>
  </template>

  <div class="grid gap-md lg:grid-cols-2" x-show="!busy && list.length">
    <template x-for="job in list" :key="job.id">
      <div class="flex flex-col gap-sm">
        @include('site.jobs.row')
        <div class="flex items-center gap-sm px-xs">
          <template x-if="$store.jobs.hasApplied(job)">
            <a :href="'/account/applications/' + encodeURIComponent($store.jobs.appFor(job)?.id || '')" class="wa-btn-ghost-sm flex-1 !border-success !text-success">Applied ✓ · Track</a>
          </template>
          <template x-if="!$store.jobs.hasApplied(job)">
            <button type="button" @click="Inthes.quickApply(job)" class="wa-btn-sm flex-1">Apply</button>
          </template>
          <button type="button" @click="$store.jobs.toggleSave(job)" class="wa-btn-quiet">@include('admin.partials.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])Remove</button>
        </div>
      </div>
    </template>
  </div>
</div>
@endsection
