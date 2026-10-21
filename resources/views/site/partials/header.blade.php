{{--
  The site header — one header for the whole website, signed in or not.

  Signed out: the nav, "Sign in" (a popup, so nobody loses their place) and
  "Post a job". Signed in: messages, the notifications bell and an account
  menu. The session lives in `$store.auth` (js/site/core.js).

  The nav collapses to a drawer below `md`.
--}}
<header data-site-header class="sticky top-0 z-40 border-b border-hairline-divider bg-canvas/85 backdrop-blur-md">
  <div class="mx-auto flex h-[68px] max-w-[1200px] items-center gap-lg px-page lg:px-xl">

    <a href="{{ route('site.home') }}" class="flex shrink-0 items-center gap-md" title="Inthes">
      @include('admin.partials.logo', ['size' => 34])
      <span class="min-w-0">
        <span class="block text-kicker text-ink-secondary">INTHES</span>
        <span class="block truncate text-h4 text-ink">Healthcare jobs</span>
      </span>
    </a>

    @php
      $nav = [
        ['label' => 'Find jobs', 'href' => route('site.jobs'), 'active' => request()->routeIs('site.jobs', 'site.job')],
        ['label' => 'For employers', 'href' => url('/employer'), 'active' => request()->is('employer*')],
        ['label' => 'Get the app', 'href' => route('site.get-app'), 'active' => request()->routeIs('site.get-app')],
        ['label' => 'Help', 'href' => route('site.faq'), 'active' => request()->routeIs('site.faq')],
      ];
    @endphp
    <nav class="ml-auto hidden items-center gap-xs md:flex">
      @foreach ($nav as $item)
        <a href="{{ $item['href'] }}"
           class="rounded-field px-md py-sm text-bodysm transition-colors duration-micro
                  {{ $item['active'] ? 'bg-primary-light font-semibold text-primary-dark' : 'text-ink-secondary hover:bg-surface-muted hover:text-ink' }}">
          {{ $item['label'] }}
        </a>
      @endforeach
    </nav>

    <div class="ml-auto flex items-center gap-sm md:ml-0">
      {{-- Signed out --}}
      <template x-if="!$store.auth.signedIn">
        <div class="flex items-center gap-sm">
          <button type="button" @click="signIn()"
                  class="inline-flex h-10 items-center justify-center gap-sm rounded-button border-btn border-hairline-strong bg-surface px-lg text-btnghost font-semibold text-ink transition-colors duration-micro hover:bg-surface-muted">
            Sign in
          </button>
          <a href="{{ url('/employer/jobs/new') }}"
             class="hidden h-10 items-center justify-center gap-sm rounded-button bg-primary px-lg text-btn font-semibold text-ink-onPrimary shadow-button
                    transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97] lg:inline-flex">
            Post a job
          </a>
        </div>
      </template>

      {{-- Signed in --}}
      <template x-if="$store.auth.signedIn">
        <div class="flex items-center gap-xs">
          <a href="{{ url('/account/messages') }}" :href="$store.notif.messagesUrl" title="Messages"
             class="relative hidden h-10 w-10 items-center justify-center rounded-field text-ink-secondary transition-colors hover:bg-surface-muted hover:text-ink sm:inline-flex">
            @include('admin.partials.icon', ['name' => 'message', 'class' => 'h-5 w-5'])
            <span x-show="$store.notif.chatUnread > 0" x-cloak class="absolute right-[7px] top-[7px] h-[9px] w-[9px] rounded-full border-2 border-canvas bg-primary"></span>
          </a>
          @include('site.partials.notif-menu')

          <div class="relative" @click.outside="accountOpen = false" @keydown.escape.window="accountOpen = false">
            <button type="button" @click="accountOpen = !accountOpen" :aria-expanded="accountOpen"
                    class="flex items-center gap-sm rounded-full py-[3px] pl-[3px] pr-sm transition-colors hover:bg-surface-muted">
              <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-primary-light text-[12px] font-semibold text-primary-dark">
                <template x-if="$store.auth.me?.photo_url"><img :src="$store.auth.me.photo_url" alt="" class="h-full w-full object-cover"></template>
                <template x-if="!$store.auth.me?.photo_url"><span x-html="initials($store.auth.name) || ICONS.user" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span></template>
              </span>
              <span class="hidden max-w-[120px] truncate text-bodysm font-semibold text-ink lg:block" x-text="$store.auth.firstName || 'Account'"></span>
              @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'hidden h-4 w-4 text-ink-muted lg:block'])
            </button>

            <div x-show="accountOpen" x-cloak x-transition.opacity.duration.150ms
                 class="absolute right-0 top-[52px] z-50 w-[260px] overflow-hidden rounded-card border-hair border-hairline bg-surface py-xs shadow-raised">
              <div class="border-b border-hairline-divider px-lg pb-md pt-sm">
                <p class="truncate text-h5 text-ink" x-text="$store.auth.name || 'Your account'"></p>
                <p class="text-caption text-ink-muted" x-text="$store.auth.phone ? '+91 ' + $store.auth.phone : ''"></p>
                <template x-if="$store.auth.me && ($store.auth.me.profile_strength ?? 0) < 100">
                  <a href="{{ url('/account') }}" class="mt-sm block">
                    <span class="flex items-center justify-between text-caption"><span class="text-ink-secondary">Profile</span><span class="font-semibold text-primary-dark" x-text="($store.auth.me.profile_strength ?? 0) + '%'"></span></span>
                    <span class="mt-[3px] block h-[5px] overflow-hidden rounded-full bg-surface-muted"><span class="block h-full rounded-full bg-primary" :style="`width:${$store.auth.me.profile_strength ?? 0}%`"></span></span>
                  </a>
                </template>
              </div>
              @foreach ([
                ['My profile', '/account', 'user'],
                ['My applications', '/account/applications', 'clipboard'],
                ['Saved jobs', '/account/saved', 'bookmark'],
                ['Job alerts', '/account/alerts', 'bell'],
                ['Messages', '/account/messages', 'message'],
                ['My plan', '/account/plan', 'crown'],
              ] as [$label, $href, $icon])
                <a href="{{ url($href) }}" class="flex items-center gap-md px-lg py-[9px] text-bodysm text-ink hover:bg-surface-muted">
                  @include('admin.partials.icon', ['name' => $icon, 'class' => 'h-4 w-4 text-ink-secondary'])
                  {{ $label }}
                </a>
              @endforeach
              <div class="my-xs border-t border-hairline-divider"></div>
              <a href="{{ url('/employer') }}" class="flex items-center gap-md px-lg py-[9px] text-bodysm text-ink hover:bg-surface-muted">
                @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-4 w-4 text-ink-secondary'])
                Employer dashboard
              </a>
              <button type="button" @click="accountOpen = false; Inthes.confirmSignOut()" class="flex w-full items-center gap-md px-lg py-[9px] text-left text-bodysm text-danger hover:bg-danger-bg">
                @include('admin.partials.icon', ['name' => 'logOut', 'class' => 'h-4 w-4'])
                Sign out
              </button>
            </div>
          </div>
        </div>
      </template>

      <button @click="menuOpen = !menuOpen" aria-label="Menu"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline md:hidden">
        <span x-show="!menuOpen" x-html="ICONS.menu" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        <span x-show="menuOpen" x-cloak x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
      </button>
    </div>
  </div>

  {{-- Mobile drawer. --}}
  <div x-show="menuOpen" x-cloak
       x-transition:enter="transition duration-component ease-out" x-transition:enter-start="opacity-0 -translate-y-2"
       x-transition:leave="transition duration-micro" x-transition:leave-end="opacity-0 -translate-y-2"
       class="border-t border-hairline-divider bg-canvas md:hidden">
    <nav class="mx-auto max-w-[1200px] px-page py-md">
      @foreach ($nav as $item)
        <a href="{{ $item['href'] }}"
           class="block rounded-field px-md py-md text-bodysm transition-colors
                  {{ $item['active'] ? 'bg-primary-light font-semibold text-primary-dark' : 'text-ink-secondary hover:bg-surface-muted' }}">
          {{ $item['label'] }}
        </a>
      @endforeach
      <template x-if="$store.auth.signedIn">
        <div class="mt-sm border-t border-hairline-divider pt-sm">
          <a href="{{ url('/account') }}" class="block rounded-field px-md py-md text-bodysm text-ink hover:bg-surface-muted">My profile</a>
          <a href="{{ url('/account/applications') }}" class="block rounded-field px-md py-md text-bodysm text-ink hover:bg-surface-muted">My applications</a>
          <a href="{{ url('/account/messages') }}" class="block rounded-field px-md py-md text-bodysm text-ink hover:bg-surface-muted">Messages</a>
        </div>
      </template>
      <a href="{{ url('/employer/jobs/new') }}"
         class="mt-sm flex h-[46px] items-center justify-center rounded-button bg-primary text-btn font-semibold text-ink-onPrimary shadow-button">
        Post a job
      </a>
    </nav>
  </div>
</header>
