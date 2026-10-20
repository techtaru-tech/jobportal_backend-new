{{--
  The notifications bell: an unread dot and a dropdown of the latest few for
  the side the visitor is on (the employer area reads the recruiter inbox).
  State and routing live in `$store.notif` (js/site/notifications.js).
  Opening it only peeks — "Mark all as read" is explicit.
--}}
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
  <button type="button" title="Notifications" :aria-expanded="open"
          @click="open = !open; if (open) $store.notif.refresh()"
          class="relative inline-flex h-10 w-10 items-center justify-center rounded-field text-ink-secondary transition-colors hover:bg-surface-muted hover:text-ink"
          :class="open && 'bg-surface-muted text-ink'">
    @include('admin.partials.icon', ['name' => 'bell', 'class' => 'h-5 w-5'])
    <span x-show="$store.notif.unread > 0" x-cloak
          class="absolute right-[7px] top-[7px] h-[9px] w-[9px] rounded-full border-2 border-canvas bg-primary"></span>
  </button>

  <div x-show="open" x-cloak x-transition.opacity.duration.150ms
       class="fixed inset-x-sm top-[72px] z-50 overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-raised
              sm:absolute sm:inset-x-auto sm:right-0 sm:top-[52px] sm:w-[380px]">
    <div class="flex items-center gap-sm border-b border-hairline-divider px-lg py-md">
      <p class="min-w-0 flex-1 text-h5 text-ink">
        Notifications
        <span class="ml-xs text-caption font-normal text-ink-muted" x-text="$store.notif.audience === 'recruiter' ? 'Hiring' : 'Job search'"></span>
      </p>
      <button type="button" x-show="$store.notif.unread > 0" @click="$store.notif.markAll()"
              class="text-caption font-semibold text-primary-dark hover:underline">Mark all as read</button>
    </div>

    <div class="thin-scrollbar max-h-[min(440px,calc(100vh-180px))] overflow-y-auto">
      <template x-if="!$store.notif.loaded">
        <div class="flex justify-center py-xl">
          @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-5 w-5 animate-spin text-primary'])
        </div>
      </template>
      <template x-if="$store.notif.loaded && $store.notif.items.length === 0">
        <div class="px-lg py-xl text-center">
          <span class="mx-auto inline-flex h-11 w-11 items-center justify-center rounded-full bg-primary-light text-primary">
            @include('admin.partials.icon', ['name' => 'bell', 'class' => 'h-5 w-5'])
          </span>
          <p class="mt-md text-h5 text-ink">You're all caught up</p>
          <p class="mt-[2px] text-caption text-ink-secondary">Application updates and messages show up here.</p>
        </div>
      </template>
      <template x-for="n in $store.notif.latest" :key="n.id">
        <button type="button" @click="open = false; $store.notif.go(n)"
                :class="!n.read && 'bg-primary-light/40'"
                class="flex w-full items-start gap-md border-b border-hairline-divider px-lg py-md text-left transition-colors last:border-b-0 hover:bg-surface-muted">
          <span class="mt-[1px] inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="!n.read ? 'bg-primary-light text-primary' : 'bg-surface-muted text-ink-secondary'">
            <span x-html="ICONS[$store.notif.icon(n)]" class="[&>svg]:h-4 [&>svg]:w-4"></span>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-[13px] leading-[1.45]" :class="!n.read ? 'font-semibold text-ink' : 'text-ink-secondary'" x-text="n.text"></span>
            <span class="mt-[2px] block text-caption text-ink-muted" x-text="timeAgo(n.at)"></span>
          </span>
          <span class="mt-[6px] h-[7px] w-[7px] shrink-0 rounded-full" :class="!n.read ? 'bg-primary' : 'bg-transparent'"></span>
        </button>
      </template>
    </div>

    <a :href="$store.notif.allUrl" class="block border-t border-hairline-divider px-lg py-md text-center text-bodysm font-semibold text-primary-dark hover:bg-surface-muted">
      See all notifications
    </a>
  </div>
</div>
