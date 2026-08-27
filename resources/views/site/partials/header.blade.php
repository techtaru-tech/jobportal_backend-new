{{--
  The site header.

  Sticky and blurred rather than opaque, matching the admin panel's top bar:
  content scrolling under a fixed bar should be visibly *under* it, which a
  flat fill hides.

  The nav collapses to a drawer below `md`. Five links do fit on a phone if you
  shrink them enough, which is exactly why they should not be.
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

    <nav class="ml-auto hidden items-center gap-xs md:flex">
      @foreach ([
        ['label' => 'Find jobs', 'route' => 'site.jobs'],
        ['label' => 'For employers', 'route' => 'site.post-job'],
        ['label' => 'Get the app', 'route' => 'site.get-app'],
        ['label' => 'Help', 'route' => 'site.faq'],
      ] as $item)
        @php $active = request()->routeIs($item['route']); @endphp
        <a href="{{ route($item['route']) }}"
           class="rounded-field px-md py-sm text-bodysm transition-colors duration-micro
                  {{ $active ? 'bg-primary-light font-semibold text-primary-dark' : 'text-ink-secondary hover:bg-surface-muted hover:text-ink' }}">
          {{ $item['label'] }}
        </a>
      @endforeach
    </nav>

    <div class="ml-auto flex items-center gap-sm md:ml-0">
      {{-- The one call to action in the header, and it is the employer one:
           candidates are sent to the app, and a second primary button beside
           this would make neither look like the main thing. --}}
      <a href="{{ route('site.post-job') }}"
         class="hidden h-10 items-center justify-center gap-sm rounded-button bg-primary px-lg text-btn font-semibold text-ink-onPrimary shadow-button
                transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97] sm:inline-flex">
        Post a job
      </a>

      <button @click="menuOpen = !menuOpen" aria-label="Menu"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors duration-micro hover:bg-hairline md:hidden">
        <span x-show="!menuOpen" x-html="ICONS.menu" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        <span x-show="menuOpen" x-cloak x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
      </button>
    </div>
  </div>

  {{-- Mobile drawer. Height-animated rather than toggled, so it reads as the
       header growing instead of a panel appearing from nowhere. --}}
  <div x-show="menuOpen" x-cloak
       x-transition:enter="transition duration-component ease-out"
       x-transition:enter-start="opacity-0 -translate-y-2"
       x-transition:leave="transition duration-micro"
       x-transition:leave-end="opacity-0 -translate-y-2"
       class="border-t border-hairline-divider bg-canvas md:hidden">
    <nav class="mx-auto max-w-[1200px] px-page py-md">
      @foreach ([
        ['label' => 'Find jobs', 'route' => 'site.jobs'],
        ['label' => 'For employers', 'route' => 'site.post-job'],
        ['label' => 'Get the app', 'route' => 'site.get-app'],
        ['label' => 'Help', 'route' => 'site.faq'],
      ] as $item)
        <a href="{{ route($item['route']) }}"
           class="block rounded-field px-md py-md text-bodysm transition-colors
                  {{ request()->routeIs($item['route']) ? 'bg-primary-light font-semibold text-primary-dark' : 'text-ink-secondary hover:bg-surface-muted' }}">
          {{ $item['label'] }}
        </a>
      @endforeach
      <a href="{{ route('site.post-job') }}"
         class="mt-sm flex h-[46px] items-center justify-center rounded-button bg-primary text-btn font-semibold text-ink-onPrimary shadow-button">
        Post a job
      </a>
    </nav>
  </div>
</header>
