{{--
  The persistent top bar: breadcrumb, menu search, the alert bell, and the
  operator's own account menu.

  Semi-transparent + blurred rather than opaque: content scrolling under a
  sticky bar should be visibly *under* it, which a flat fill hides.
--}}
<header class="sticky top-0 z-30 border-b border-hairline-divider bg-canvas/85 backdrop-blur-md">
  <div class="flex h-[64px] items-center gap-md px-page lg:px-xl">

    {{-- Drawer toggle, mobile only — on desktop the sidebar carries nav. --}}
    <button @click="drawerOpen = true" aria-label="Open navigation" title="Open navigation"
            class="lg:hidden inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline">
      @include('admin.partials.icon', ['name' => 'menu', 'class' => 'h-5 w-5'])
    </button>

    {{--
      Where you are, derived from the current view rather than declared per
      page. A detail screen renders a plain "Details" crumb: the record's own
      name lives in the page header a few pixels below, and repeating it here
      would say the same thing twice while making the bar jump as it loads.
    --}}
    <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-xs">
      <template x-if="view === 'dashboard'">
        <span class="min-w-0 truncate text-h4 text-ink">Dashboard</span>
      </template>
      <template x-if="view !== 'dashboard'">
        <span class="flex min-w-0 items-center gap-xs">
          <button @click="go('dashboard')" class="hidden shrink-0 text-bodysm text-ink-muted transition-colors hover:text-ink sm:block">Dashboard</button>
          <span class="hidden sm:block" x-html="ICONS.chevronRight" aria-hidden="true"
                class="[&>svg]:h-3 [&>svg]:w-3 shrink-0 text-ink-muted"></span>

          <template x-if="!isDetail()">
            <span class="min-w-0 truncate text-h4 text-ink" x-text="sectionLabel()"></span>
          </template>
          <template x-if="isDetail()">
            <span class="flex min-w-0 items-center gap-xs">
              <button @click="go(sectionKey())" x-text="sectionLabel()"
                      class="shrink-0 text-bodysm text-ink-muted transition-colors hover:text-ink"></button>
              <span x-html="ICONS.chevronRight" aria-hidden="true" class="[&>svg]:h-3 [&>svg]:w-3 shrink-0 text-ink-muted"></span>
              <span class="min-w-0 truncate text-h4 text-ink">Details</span>
            </span>
          </template>
        </span>
      </template>
    </nav>

    <div class="ml-auto flex items-center gap-sm">

      {{--
        Search over the panel's own sections — not over the data. Nine sections
        is past the point where scanning a sidebar beats typing, and a
        data-wide search would need an endpoint that doesn't exist (each list
        has its own scoped `?query=`). So this navigates, and the section it
        lands on has the search box for its own rows.
      --}}
      <div class="relative hidden md:block" @click.outside="searchOpen = false">
        <span x-html="ICONS.search" aria-hidden="true"
              class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 [&>svg]:h-4 [&>svg]:w-4 text-ink-muted"></span>
        <input x-ref="menuSearch" x-model="searchQuery"
               @input="searchOpen = true; searchHighlight = 0" @focus="searchOpen = true"
               @keydown.escape="searchOpen = false; $refs.menuSearch.blur()"
               @keydown.arrow-down.prevent="moveSearch(1)"
               @keydown.arrow-up.prevent="moveSearch(-1)"
               @keydown.enter.prevent="openSearchHit()"
               placeholder="Search menu…" aria-label="Search the panel's sections"
               class="h-10 w-[200px] rounded-field bg-surface-muted pl-[38px] pr-[48px] text-bodysm text-ink
                      border-hair border-transparent outline-none
                      transition-[width,border-color,background-color] duration-component ease-out
                      placeholder:text-ink-muted
                      focus:w-[280px] focus:border-primary focus:bg-surface focus:shadow-glow
                      lg:w-[240px] lg:focus:w-[320px]">
        <kbd class="pointer-events-none absolute right-sm top-1/2 -translate-y-1/2 rounded-[6px] border-hair border-hairline bg-canvas px-[6px] py-[2px] text-caption text-ink-muted">⌘K</kbd>

        <div x-show="searchOpen && searchQuery.trim() !== ''" x-cloak
             class="absolute right-0 top-[calc(100%+8px)] z-50 w-[320px] overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-raised animate-pop-in">
          <template x-if="searchMatches().length === 0">
            <p class="px-lg py-md text-bodysm text-ink-muted">
              No section matches “<span x-text="searchQuery.trim()"></span>”.
            </p>
          </template>
          <template x-if="searchMatches().length > 0">
            <div>
              <div class="border-b border-hairline-divider px-lg py-sm">
                <span class="block text-kicker text-ink-secondary">SECTIONS</span>
              </div>
              <template x-for="(item, i) in searchMatches()" :key="item.key">
                <button type="button" @mouseenter="searchHighlight = i" @click="go(item.key); searchQuery = ''; searchOpen = false"
                        :class="i === searchHighlight ? 'bg-primary-light font-semibold text-primary-dark' : 'text-ink-secondary'"
                        class="flex w-full items-center gap-md px-lg py-[10px] text-left text-bodysm transition-colors">
                  <span x-html="ICONS[item.icon]"
                        :class="i === searchHighlight ? 'text-primary-dark' : 'text-ink-muted'"
                        class="[&>svg]:h-[18px] [&>svg]:w-[18px] shrink-0"></span>
                  <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>
                  <kbd x-show="i === searchHighlight" class="shrink-0 text-caption text-primary-dark/70">↵</kbd>
                </button>
              </template>
            </div>
          </template>
        </div>
      </div>

      {{--
        The bell. Reads the operator's own alert feed — never
        `app_notifications`, which is the *users'* inbox. Only `action` groups
        are counted: folding data-quality warnings in would leave a number
        nobody can ever clear, which is how a badge stops being read at all.
      --}}
      <div class="relative" @click.outside="bellOpen = false">
        <button type="button" @click="bellOpen = !bellOpen"
                :aria-label="actionTotal > 0 ? actionTotal + ' items need attention' : 'Nothing needs attention'"
                class="relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline">
          @include('admin.partials.icon', ['name' => 'bell', 'class' => 'h-5 w-5'])
          {{-- A counted badge rather than a bare dot: 3 waiting and 40 waiting
               are different-sized problems and the bar has room to say which. --}}
          <span x-show="actionTotal > 0" x-cloak
                class="absolute -right-[2px] -top-[2px] flex min-w-[17px] items-center justify-center rounded-chip bg-primary px-[4px] py-[1px] text-[10px] font-bold leading-[13px] tabular-nums text-ink-onPrimary ring-2 ring-canvas">
            <span class="absolute inset-0 animate-pulse-ring rounded-chip" aria-hidden="true"></span>
            <span x-text="actionTotal > 99 ? '99+' : actionTotal"></span>
          </span>
        </button>

        <div x-show="bellOpen" x-cloak
             class="absolute right-0 top-[calc(100%+8px)] z-50 w-[340px] overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-raised animate-pop-in">
          <div class="border-b border-hairline-divider px-lg py-md">
            <span class="block text-kicker text-ink-secondary">NOTIFICATIONS</span>
            <p class="mt-[2px] text-bodysm font-semibold text-ink"
               x-text="actionTotal === 0 ? 'Nothing waiting on you' : actionTotal + ' item' + (actionTotal === 1 ? '' : 's') + ' waiting on you'"></p>
          </div>

          <template x-if="notifications.data.length === 0">
            <div class="flex items-center gap-md px-lg py-lg">
              @include('admin.partials.icon', ['name' => 'shieldCheck', 'class' => 'h-5 w-5 shrink-0 text-success'])
              <p class="text-bodysm text-ink-secondary">Nothing has happened recently.</p>
            </div>
          </template>

          {{-- The five most recent events, not the whole feed: this is a peek
               that leads somewhere, and a dropdown long enough to scroll is
               just the page in a worse container. --}}
          <template x-if="notifications.data.length > 0">
            <div>
              <template x-for="row in notifications.data.slice(0, 5)" :key="row.id">
                <button type="button" @click="openNotification(row); bellOpen = false"
                        class="flex w-full items-start gap-md px-lg py-md text-left transition-colors hover:bg-surface-muted">
                  <span x-html="ICONS[notificationIcon(row.type)]"
                        :class="row.severity === 'action' ? 'bg-primary-light text-primary-dark' : 'bg-surface-muted text-ink-secondary'"
                        class="mt-[1px] flex h-8 w-8 shrink-0 items-center justify-center rounded-full [&>svg]:h-4 [&>svg]:w-4"></span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-bodysm text-ink" x-text="row.title"></span>
                    <span class="block truncate text-caption text-ink-muted" x-text="timeAgo(row.at)"></span>
                  </span>
                </button>
              </template>
              <div class="border-t border-hairline-divider">
                <button type="button" @click="go('notifications'); bellOpen = false"
                        class="w-full px-lg py-md text-btnghost text-primary transition-colors hover:bg-primary-light">
                  See everything
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>

      {{-- account menu --}}
      <div class="relative" @click.outside="profileOpen = false">
        <button type="button" @click="profileOpen = !profileOpen"
                :class="profileOpen ? 'bg-surface-muted' : 'hover:bg-surface-muted'"
                class="flex items-center gap-sm rounded-full py-[4px] pl-[4px] pr-md transition-colors">
          <span aria-hidden="true" x-text="initials(admin.name)"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"
                style="font-size:10.9px"></span>
          <span class="hidden min-w-0 text-left sm:block">
            <span class="block truncate text-bodysm font-semibold text-ink" x-text="admin.name"></span>
          </span>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="[&>svg]:h-4 [&>svg]:w-4 shrink-0 text-ink-muted"></span>
        </button>

        <div x-show="profileOpen" x-cloak
             class="absolute right-0 top-[calc(100%+8px)] z-50 w-[280px] overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-raised animate-pop-in">
          <div class="border-b border-hairline-divider px-lg py-md">
            <div class="flex items-center gap-md">
              <span aria-hidden="true" x-text="initials(admin.name)"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"
                    style="font-size:13.6px"></span>
              <div class="min-w-0">
                <p class="truncate text-bodysm font-semibold text-ink" x-text="admin.name"></p>
                <p class="truncate text-caption text-ink-muted" x-text="admin.email"></p>
              </div>
            </div>
            <p class="mt-md text-caption text-ink-muted"
               x-text="admin.can_write ? 'Full access — you can change things here.' : 'Read-only access — you can look, not change.'"></p>
          </div>
          <div class="py-xs">
            <button @click="logout()"
                    class="flex w-full items-center gap-md px-lg py-[10px] text-left text-bodysm text-danger transition-colors hover:bg-danger-bg">
              @include('admin.partials.icon', ['name' => 'logOut', 'class' => 'h-[18px] w-[18px] shrink-0'])
              <span>Sign out</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>
