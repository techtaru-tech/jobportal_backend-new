{{--
  The fixed sidebar from `lg` up, a slide-over drawer below it.

  The drawer is the whole responsive story for navigation — squeezing nine
  items into a bottom bar would either truncate the list or make every label
  unreadable, and an operator panel is used on a laptop most of the time.
--}}

{{-- drawer backdrop --}}
<div x-show="drawerOpen" @click="drawerOpen = false" role="presentation"
     x-transition:enter="transition-opacity duration-micro" x-transition:enter-start="opacity-0"
     x-transition:leave="transition-opacity duration-micro" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-40 bg-black/35 lg:hidden"></div>

<aside :class="[
         railed ? 'w-[72px]' : 'w-[248px]',
         drawerOpen ? 'translate-x-0' : '-translate-x-full',
       ]"
       class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-hairline-divider bg-canvas
              transition-[transform,width] duration-component ease-out lg:translate-x-0">

  <div :class="railed ? 'justify-center px-0' : 'px-xl'"
       class="relative flex h-[64px] shrink-0 items-center gap-md border-b border-hairline-divider">
    <button @click="go('dashboard')" title="Inthes Admin" class="flex min-w-0 items-center gap-md">
      @include('admin.partials.logo', ['size' => 32])
      <span class="min-w-0" x-show="!railed">
        <span class="block text-kicker text-ink-secondary">INTHES</span>
        <span class="block truncate text-h4 text-ink text-left">Admin</span>
      </span>
    </button>
    <button @click="drawerOpen = false" aria-label="Close navigation" title="Close navigation"
            class="ml-auto lg:hidden inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline">
      @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
    </button>
  </div>

  <nav :class="railed ? 'px-sm' : 'px-md'" class="thin-scrollbar flex-1 overflow-y-auto py-md">
    <template x-for="item in NAV" :key="item.key">
      {{--
        Both the active-state colours and the railed spacing go through a
        single `:class`. They used to be two separate bindings (`:class` plus
        `x-bind:class`), which are two spellings of the same Alpine directive —
        so they overwrote each other and the losing half never applied, leaving
        the icon flush against its label with no padding.
      --}}
      <button @click="go(item.key)"
              :title="railed ? item.label : null"
              :class="[
                isSection(item.key)
                  ? 'bg-primary-light font-semibold text-primary-dark'
                  : 'text-ink-secondary hover:bg-surface-muted hover:text-ink',
                railed ? 'justify-center px-0 py-[11px]' : 'gap-md px-md py-[9px]',
              ]"
              class="group relative mb-[2px] flex w-full items-center rounded-field text-bodysm transition-colors duration-micro">

        {{-- Active marker: a red bar on the left edge. The tinted fill alone
             reads as a hover, so the edge is what makes "you are here"
             unambiguous. --}}
        <span x-show="isSection(item.key)" aria-hidden="true"
              class="absolute left-0 top-1/2 h-[18px] w-[3px] -translate-y-1/2 rounded-r-chip bg-primary"></span>

        <span x-html="ICONS[item.icon]"
              :class="isSection(item.key) ? 'text-primary-dark' : 'text-ink-muted group-hover:text-ink'"
              class="[&>svg]:h-[18px] [&>svg]:w-[18px] shrink-0 transition-colors"></span>

        <span x-show="!railed" x-text="item.label" class="min-w-0 flex-1 truncate text-left"></span>

        {{-- Collapsed: a dot, since a two-digit pill would not fit inside a
             72px rail without clipping. --}}
        <template x-if="badgeCount(item) > 0 && railed">
          <span class="absolute right-[14px] top-[9px] h-[6px] w-[6px] rounded-full bg-primary ring-2 ring-canvas"
                :aria-label="badgeCount(item) + ' waiting'"></span>
        </template>
        <template x-if="badgeCount(item) > 0 && !railed">
          <span class="shrink-0 rounded-chip bg-primary px-[7px] py-[1px] text-caption font-bold tabular-nums text-ink-onPrimary"
                x-text="badgeCount(item) > 99 ? '99+' : badgeCount(item)"></span>
        </template>
      </button>
    </template>
  </nav>

  {{--
    Who is signed in, and the collapse control — pinned to the bottom of the
    rail rather than living only in the top bar's account menu, because "which
    account am I acting as" is the one thing an operator with access to several
    environments should never have to open a menu to check.
  --}}
  <div class="shrink-0 border-t border-hairline-divider p-sm">
    <div x-show="!railed" class="mb-xs flex items-center gap-md rounded-field px-sm py-sm">
      <span aria-hidden="true" x-text="initials(admin.name)"
            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"
            style="font-size:10.9px"></span>
      <div class="min-w-0 flex-1">
        <p class="truncate text-bodysm font-semibold text-ink" x-text="admin.name"></p>
        <p class="truncate text-caption text-ink-muted"
           x-text="admin.can_write ? admin.role : admin.role + ' · read-only'"></p>
      </div>
    </div>

    <div :class="railed && 'flex-col'" class="flex items-center gap-xs">
      <button @click="toggleCollapsed()"
              :title="railed ? 'Expand sidebar' : 'Collapse sidebar'"
              :aria-label="railed ? 'Expand sidebar' : 'Collapse sidebar'"
              class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink lg:inline-flex">
        <span x-show="railed" x-html="ICONS.panelOpen" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        <span x-show="!railed" x-html="ICONS.panelClose" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      </button>

      <button @click="logout()" title="Sign out"
              :class="railed ? 'h-9 w-9' : 'h-9 flex-1'"
              class="flex items-center justify-center gap-sm rounded-field text-btnghost text-ink-muted transition-colors duration-micro hover:bg-danger-bg hover:text-danger">
        @include('admin.partials.icon', ['name' => 'logOut', 'class' => 'h-[18px] w-[18px] shrink-0'])
        <span x-show="!railed">Sign out</span>
      </button>
    </div>
  </div>
</aside>
