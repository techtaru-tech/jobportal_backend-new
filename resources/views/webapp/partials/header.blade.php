{{--
  The app bar. On a phone it is the app's top row (logo, chats, bell) and the
  tabs live in the bottom nav; from `lg` up the tabs move up here inline.
--}}
<template x-if="showChrome">
  <header class="sticky top-0 z-40 border-b border-hairline-divider bg-canvas/90 backdrop-blur-md">
    <div class="mx-auto flex h-[60px] max-w-[1200px] items-center gap-md px-page lg:px-xl">

      <a :href="'#' + defaultPath" class="flex shrink-0 items-center gap-sm" title="Inthes">
        @include('admin.partials.logo', ['size' => 32])
        <span class="min-w-0">
          <span class="block text-kicker text-ink-secondary">INTHES</span>
          <span class="block text-h5 text-ink" x-text="mode === 'recruiter' ? 'Hiring' : 'Jobs'"></span>
        </span>
      </a>

      {{-- Desktop tabs --}}
      <nav class="ml-lg hidden items-center gap-xs lg:flex">
        <template x-for="tab in tabs" :key="tab.name">
          <a :href="'#' + tab.path"
             :class="activeTab === tab.name ? 'bg-primary-light font-semibold text-primary-dark' : 'text-ink-secondary hover:bg-surface-muted hover:text-ink'"
             class="inline-flex items-center gap-sm rounded-field px-md py-sm text-bodysm transition-colors duration-micro">
            <span x-html="ICONS[tab.icon]" class="[&>svg]:h-[17px] [&>svg]:w-[17px]"></span>
            <span x-text="tab.label"></span>
          </a>
        </template>
      </nav>

      <div class="ml-auto flex items-center gap-xs">
        <template x-if="!token">
          <div class="flex items-center gap-sm">
            <a :href="playStoreUrl" class="hidden h-10 items-center gap-sm rounded-button border-btn border-hairline-strong px-md text-btnghost text-ink transition-colors hover:bg-surface-muted sm:inline-flex">
              <span x-html="ICONS.smartphone" class="[&>svg]:h-4 [&>svg]:w-4"></span>Get the app
            </a>
            <a href="#/auth" class="inline-flex h-10 items-center rounded-button bg-primary px-lg text-btnghost font-semibold text-ink-onPrimary shadow-button hover:bg-primary-dark">Sign in</a>
          </div>
        </template>

        <template x-if="token">
          <div class="flex items-center gap-xs">
            <a href="#/chats" title="Chats"
               :class="is('conversations','chat') ? 'bg-primary-light text-primary-dark' : 'text-ink-secondary hover:bg-surface-muted'"
               class="relative inline-flex h-10 w-10 items-center justify-center rounded-field transition-colors">
              <span x-html="ICONS.message" class="[&>svg]:h-5 [&>svg]:w-5"></span>
              <span x-show="(typeof chatUnread !== 'undefined') && chatUnread > 0" class="absolute right-[7px] top-[7px] h-2 w-2 rounded-full bg-primary"></span>
            </a>
            <a href="#/notifications" title="Notifications"
               :class="is('notifications') ? 'bg-primary-light text-primary-dark' : 'text-ink-secondary hover:bg-surface-muted'"
               class="relative inline-flex h-10 w-10 items-center justify-center rounded-field transition-colors">
              <span x-html="ICONS.bell" class="[&>svg]:h-5 [&>svg]:w-5"></span>
              <span x-show="(typeof notifUnread !== 'undefined') && notifUnread > 0" class="absolute right-[8px] top-[7px] h-2 w-2 rounded-full bg-primary"></span>
            </a>

            {{-- Avatar menu --}}
            <div class="relative ml-xs" @click.outside="userMenu = false">
              <button @click="userMenu = !userMenu" class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-primary-light text-[12px] font-semibold text-primary-dark ring-2 ring-transparent hover:ring-primary-line">
                <template x-if="me?.photo_url"><img :src="me.photo_url" alt="" class="h-full w-full object-cover"></template>
                <template x-if="!me?.photo_url"><span x-text="initials(me?.name || user?.phone)"></span></template>
              </button>
              <div x-show="userMenu" x-transition.opacity.duration.150ms
                   class="absolute right-0 top-[48px] z-50 w-[240px] overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-raised">
                <div class="border-b border-hairline-divider px-lg py-md">
                  <p class="truncate text-h5 text-ink" x-text="me?.name || 'Your account'"></p>
                  <p class="text-caption text-ink-muted" x-text="user?.phone ? '+91 ' + user.phone : ''"></p>
                </div>
                <a :href="mode === 'recruiter' ? '#/r/profile' : '#/profile'" class="flex items-center gap-md px-lg py-md text-bodysm hover:bg-surface-muted">
                  <span x-html="ICONS.user" class="text-ink-secondary [&>svg]:h-4 [&>svg]:w-4"></span>Profile
                </a>
                <button @click="switchMode()" class="flex w-full items-center gap-md px-lg py-md text-left text-bodysm hover:bg-surface-muted">
                  <span x-html="ICONS.refresh" class="text-ink-secondary [&>svg]:h-4 [&>svg]:w-4"></span>
                  <span x-text="mode === 'recruiter' ? 'Switch to job search' : 'Switch to hiring'"></span>
                </button>
                <a :href="playStoreUrl" class="flex items-center gap-md px-lg py-md text-bodysm hover:bg-surface-muted">
                  <span x-html="ICONS.smartphone" class="text-ink-secondary [&>svg]:h-4 [&>svg]:w-4"></span>Get the Android app
                </a>
                <button @click="confirmSignOut()" class="flex w-full items-center gap-md border-t border-hairline-divider px-lg py-md text-left text-bodysm text-danger hover:bg-danger-bg">
                  <span x-html="ICONS.logOut" class="[&>svg]:h-4 [&>svg]:w-4"></span>Sign Out
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- Profile-completion bar: job-seeker side, under 100%, not on Profile. --}}
    <template x-if="token && mode === 'jobSeeker' && me && (me.profile_strength ?? 0) < 100 && !is('profile','profileEdit','resume')">
      <a href="#/profile" class="block border-t border-hairline-divider bg-primary-light/60 hover:bg-primary-light">
        <div class="mx-auto flex max-w-[1200px] items-center gap-md px-page py-sm lg:px-xl">
          <span class="text-caption font-semibold text-primary-dark">Profile <span x-text="(me.profile_strength ?? 0) + '%'"></span> complete</span>
          <span class="h-[6px] flex-1 overflow-hidden rounded-full bg-surface">
            <span class="block h-full rounded-full bg-primary transition-[width] duration-page" :style="`width:${me.profile_strength ?? 0}%`"></span>
          </span>
          <span class="text-caption font-semibold text-primary-dark">Complete now</span>
        </div>
      </a>
    </template>
  </header>
</template>
