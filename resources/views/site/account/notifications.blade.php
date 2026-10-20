@extends('site.account.layout')

@section('title', 'Notifications — Inthes')
@section('account_key', 'notifications')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-notifications.js') }}?v={{ filemtime(public_path('js/site/pages/account-notifications.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountNotifications()" class="max-w-[820px]">
  <div class="mb-lg flex flex-wrap items-end gap-md">
    <div class="min-w-0 flex-1">
      <h1 class="text-h2 text-ink">Notifications</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">Application updates, messages and job matches.</p>
    </div>
    <button type="button" x-show="unreadCount > 0" @click="markAll()" class="wa-btn-quiet !text-primary-dark">
      @include('admin.partials.icon', ['name' => 'check', 'class' => 'h-4 w-4'])Mark all as read
    </button>
  </div>

  <div class="mb-lg flex flex-wrap items-center gap-md">
    {{-- Which inbox --}}
    <div class="inline-flex rounded-button bg-surface-muted p-[3px]" role="tablist">
      <template x-for="a in [['jobSeeker', 'Job search'], ['recruiter', 'Hiring']]" :key="a[0]">
        <button type="button" role="tab" @click="setAudience(a[0])" :aria-selected="audience === a[0]"
                :class="audience === a[0] ? 'bg-surface text-ink shadow-card' : 'text-ink-secondary hover:text-ink'"
                class="h-9 rounded-[10px] px-lg text-btnghost font-semibold transition-colors" x-text="a[1]"></button>
      </template>
    </div>
    <div class="flex gap-xs">
      <button type="button" @click="filter = 'all'" :class="filter === 'all' && 'wa-chip-on'" class="wa-chip !py-[6px]">All</button>
      <button type="button" @click="filter = 'unread'" :class="filter === 'unread' && 'wa-chip-on'" class="wa-chip !py-[6px]">
        Unread <span x-show="unreadCount" class="text-caption" x-text="'(' + unreadCount + ')'"></span>
      </button>
    </div>
  </div>

  <template x-if="!loaded">
    <div>@include('site.ui.spinner')</div>
  </template>
  <template x-if="loaded && error">
    <div class="wa-card text-center">
      <p class="text-bodysm text-ink-secondary" x-text="error"></p>
      <button type="button" class="wa-btn-ghost-sm mt-md" @click="load()">Try again</button>
    </div>
  </template>
  <template x-if="loaded && !error && shown.length === 0">
    <div>
      <template x-if="filter === 'unread' && items.length">
        <div>@include('site.ui.empty', ['icon' => 'checkCircle', 'title' => "You're all caught up", 'text' => 'No unread notifications.'])</div>
      </template>
      <template x-if="!(filter === 'unread' && items.length)">
        <div>@include('site.ui.empty', ['icon' => 'bell', 'title' => 'No notifications yet', 'text' => 'Updates on your applications, messages and new jobs will show up here.'])</div>
      </template>
    </div>
  </template>

  <template x-if="loaded && shown.length > 0">
    <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card">
      <template x-for="n in shown" :key="n.id">
        <button type="button" @click="open(n)"
                :class="isNew(n) ? 'bg-primary-light/40' : ''"
                class="flex w-full items-start gap-md border-b border-hairline-divider px-lg py-md text-left transition-colors last:border-b-0 hover:bg-surface-muted">
          <span class="mt-[2px] inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                :class="isNew(n) ? 'bg-primary-light text-primary' : 'bg-surface-muted text-ink-secondary'">
            <span x-html="ICONS[icon(n)]" class="[&>svg]:h-[17px] [&>svg]:w-[17px]"></span>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-[14px] leading-[1.45]" :class="isNew(n) ? 'font-semibold text-ink' : 'text-ink-secondary'" x-text="n.text"></span>
            <span class="mt-[3px] block text-caption text-ink-muted" :title="fmtDate(n.at, true)" x-text="timeAgo(n.at)"></span>
          </span>
          <span class="mt-[8px] h-[7px] w-[7px] shrink-0 rounded-full" :class="isNew(n) ? 'bg-primary' : 'bg-transparent'"></span>
        </button>
      </template>
    </div>
  </template>
</div>
@endsection
