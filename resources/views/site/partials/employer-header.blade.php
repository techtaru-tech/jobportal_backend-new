{{--
  The employer area's own bar. Sticky and blurred, matching the site header and
  the admin panel's, so the three read as one product.

  Three destinations only, so they sit inline at every width rather than
  collapsing into a drawer nobody would open.
--}}
<header class="sticky top-0 z-40 border-b border-hairline-divider bg-canvas/85 backdrop-blur-md">
  <div class="mx-auto flex h-[64px] max-w-[1200px] items-center gap-md px-page lg:px-xl">

    <a href="{{ route('site.home') }}" class="flex shrink-0 items-center gap-md" title="Inthes">
      @include('admin.partials.logo', ['size' => 32])
      <span class="hidden min-w-0 sm:block">
        <span class="block text-kicker text-ink-secondary">INTHES</span>
        <span class="block truncate text-h4 text-ink">Employer</span>
      </span>
    </a>

    <nav class="ml-auto flex items-center gap-xs">
      <button @click="go('jobs')"
              :class="(view === 'jobs' || view === 'applicants')
                ? 'bg-primary-light font-semibold text-primary-dark'
                : 'text-ink-secondary hover:bg-surface-muted hover:text-ink'"
              class="rounded-field px-md py-sm text-bodysm transition-colors duration-micro">
        My jobs
      </button>
      <button @click="startPost()"
              class="inline-flex h-10 items-center justify-center gap-sm rounded-button bg-primary px-lg text-btn font-semibold text-ink-onPrimary shadow-button
                     transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
        <span x-html="ICONS.plus" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        <span class="hidden sm:inline">Post a job</span>
      </button>
    </nav>

    <div class="ml-md flex items-center gap-sm border-l border-hairline-divider pl-md">
      <span aria-hidden="true" x-text="initials(user?.phone)" style="font-size:11.6px"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
      <button @click="signOut()" title="Sign out"
              class="inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-danger-bg hover:text-danger">
        <span x-html="ICONS.logOut" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      </button>
    </div>
  </div>
</header>
