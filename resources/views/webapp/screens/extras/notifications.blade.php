{{-- ── Notifications ───────────────────────────────────────────────────
     This side's inbox only. Opening it marks everything read (the app's
     rule); rows that were new keep their dot until "Mark all as read". --}}
<template x-if="is('notifications')">
  <div class="wa-narrow">
    <div class="mb-xl flex items-start gap-md">
      <button type="button" @click="back(defaultPath)" aria-label="Back"
              class="-ml-sm mt-[2px] inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
      </button>
      <h1 class="min-w-0 flex-1 text-h2 text-ink">Notifications</h1>
      <button type="button" x-show="notifItems.some((n) => notifIsNew(n))" @click="notifMarkAll()" class="wa-btn-quiet shrink-0 !text-primary-dark">
        @include('admin.partials.icon', ['name' => 'check', 'class' => 'h-4 w-4'])Mark all as read
      </button>
    </div>

    <template x-if="!notifLoaded && notifBusy">
      <div>@include('webapp.ui.spinner')</div>
    </template>

    <template x-if="notifLoaded && notifItems.length === 0">
      <div>@include('webapp.ui.empty', ['icon' => 'bell', 'title' => 'No New Notifications', 'text' => "You're all caught up."])</div>
    </template>

    <template x-if="notifItems.length > 0">
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card">
        <template x-for="n in notifItems" :key="n.id">
          <button type="button" @click="notifOpen(n)"
                  :class="notifIsNew(n) ? 'bg-primary-light/40' : ''"
                  class="flex w-full items-start gap-md border-b border-hairline-divider px-lg py-md text-left transition-colors last:border-b-0 hover:bg-surface-muted">
            <span class="relative mt-[2px] inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                  :class="notifIsNew(n) ? 'bg-primary-light text-primary' : 'bg-surface-muted text-ink-secondary'">
              <span x-html="ICONS[notifIcon(n)]" class="[&>svg]:h-[17px] [&>svg]:w-[17px]"></span>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-[13.5px] leading-[1.45]" :class="notifIsNew(n) ? 'font-semibold text-ink' : 'text-ink-secondary'" x-text="n.text"></span>
              <span class="mt-[3px] block text-caption text-ink-muted" x-text="timeAgo(n.at)"></span>
            </span>
            <span class="mt-[7px] h-[7px] w-[7px] shrink-0 rounded-full" :class="notifIsNew(n) ? 'bg-primary' : 'bg-transparent'"></span>
          </button>
        </template>
      </div>
    </template>
  </div>
</template>
