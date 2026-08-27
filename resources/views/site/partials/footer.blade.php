{{--
  The footer. Carries the legal pages the app also renders (they come from the
  same `content_pages` rows an admin edits), the category links that give the
  browse page its internal linking, and the app download.
--}}
<footer class="mt-xxxl border-t border-hairline-divider bg-canvas-alt">
  <div class="mx-auto max-w-[1200px] px-page py-xxl lg:px-xl">
    <div class="grid grid-cols-1 gap-xl sm:grid-cols-2 lg:grid-cols-4">

      <div>
        <div class="flex items-center gap-md">
          @include('admin.partials.logo', ['size' => 34])
          <span>
            <span class="block text-kicker text-ink-secondary">INTHES</span>
            <span class="block text-h4 text-ink">Healthcare jobs</span>
          </span>
        </div>
        <p class="mt-md text-bodysm text-ink-secondary">
          Nursing, lab, pharmacy and allied healthcare roles — with employers verified before
          their postings go live.
        </p>
      </div>

      <div>
        <span class="block text-kicker text-ink-secondary">FOR CANDIDATES</span>
        <ul class="mt-md space-y-sm text-bodysm">
          <li><a href="{{ route('site.jobs') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Browse all jobs</a></li>
          <li><a href="{{ route('site.get-app') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Get the app</a></li>
          <li><a href="{{ route('site.faq') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Help &amp; support</a></li>
        </ul>
      </div>

      <div>
        <span class="block text-kicker text-ink-secondary">FOR EMPLOYERS</span>
        <ul class="mt-md space-y-sm text-bodysm">
          <li><a href="{{ route('site.post-job') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Post a job</a></li>
          <li><a href="{{ route('site.post-job') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Manage postings</a></li>
          <li><a href="{{ route('site.page', 'contact') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Contact us</a></li>
        </ul>
      </div>

      <div>
        <span class="block text-kicker text-ink-secondary">LEGAL</span>
        <ul class="mt-md space-y-sm text-bodysm">
          <li><a href="{{ route('site.page', 'about') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">About us</a></li>
          <li><a href="{{ route('site.page', 'terms') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Terms of use</a></li>
          <li><a href="{{ route('site.page', 'privacy') }}" class="text-ink-secondary transition-colors hover:text-primary-dark">Privacy policy</a></li>
        </ul>
      </div>
    </div>

    <div class="mt-xxl flex flex-col items-start justify-between gap-md border-t border-hairline-divider pt-lg sm:flex-row sm:items-center">
      <p class="text-caption text-ink-muted">© {{ date('Y') }} Inthes. All rights reserved.</p>
      <a href="{{ App\Support\StoreQr::storeUrl() }}" target="_blank" rel="noopener"
         class="inline-flex h-10 items-center gap-sm rounded-button border-btn border-hairline bg-surface px-lg text-btnghost font-semibold text-ink
                transition-colors duration-micro hover:border-hairline-strong hover:bg-surface-muted">
        Download for Android
      </a>
    </div>
  </div>
</footer>
