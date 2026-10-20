{{-- ── Static content ──────────────────────────────────────────────────
     Terms / Privacy / About / Contact from GET /content, and Help & Support
     (slug `faq`) from its FAQs. Body text: `## ` lines are headings, blank
     lines split paragraphs — the app's ContentPage.blocks convention. --}}
<template x-if="is('page')">
  <div class="wa-narrow">
    <div class="mb-xl flex items-start gap-md">
      <button type="button" @click="back(token ? (mode === 'recruiter' ? '/r/profile' : '/profile') : '/welcome')" aria-label="Back"
              class="-ml-sm mt-[2px] inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
      </button>
      <h1 class="min-w-0 flex-1 text-h2 text-ink" x-text="pageTitle"></h1>
    </div>

    <template x-if="pageSlug && !pageContent">
      <div>@include('webapp.ui.spinner')</div>
    </template>

    {{-- Unknown slug --}}
    <template x-if="!pageSlug">
      <div class="flex flex-col items-center justify-center rounded-card border-hair border-dashed border-hairline-strong bg-surface px-xl py-xxxl text-center">
        <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
          @include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-6 w-6'])
        </span>
        <p class="mt-lg text-h4 text-ink">We couldn't find that page</p>
        <p class="mt-xs max-w-[360px] text-bodysm text-ink-secondary">It may have moved. These are the pages we have:</p>
        <div class="mt-lg flex flex-wrap justify-center gap-xs">
          <a href="#/page/about" class="wa-chip">About Us</a>
          <a href="#/page/faq" class="wa-chip">Help &amp; Support</a>
          <a href="#/page/contact" class="wa-chip">Contact Us</a>
          <a href="#/page/terms" class="wa-chip">Terms of Use</a>
          <a href="#/page/privacy" class="wa-chip">Privacy Policy</a>
        </div>
      </div>
    </template>

    {{-- Terms / Privacy / About --}}
    <template x-if="pageContent && ['terms', 'privacy', 'about'].includes(pageSlug)">
      <article class="wa-card">
        <p x-show="pageUpdated" class="mb-sm text-caption text-ink-muted" x-text="'Last updated: ' + pageUpdated"></p>
        <template x-for="(b, i) in pageBlocks" :key="i">
          <div>
            <template x-if="b.h"><h2 class="mb-sm text-h5 text-ink" :class="i > 0 ? 'mt-xl' : ''" x-text="b.text"></h2></template>
            <template x-if="!b.h"><p class="mb-sm whitespace-pre-line text-body text-ink-secondary" x-text="b.text"></p></template>
          </div>
        </template>
        <p x-show="!pageBlocks.length" class="text-body text-ink-secondary">This page hasn't been published yet. Please check back shortly.</p>
      </article>
    </template>

    {{-- Contact Us: intro, then tappable email / phone, then hours --}}
    <template x-if="pageContent && pageSlug === 'contact'">
      <div class="space-y-md">
        <p class="text-body text-ink-secondary"
           x-text="pageData?.body || 'Have a question about your account, an application, or a job posting? We\'re here to help.'"></p>
        <template x-if="pageMeta.email">
          <a :href="'mailto:' + pageMeta.email" class="wa-card flex items-center gap-md !p-lg transition-shadow hover:shadow-raised">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-primary">
              @include('admin.partials.icon', ['name' => 'mail', 'class' => 'h-[18px] w-[18px]'])
            </span>
            <span class="min-w-0 flex-1"><span class="block text-caption text-ink-secondary">Email</span><span class="block truncate text-h5 text-ink" x-text="pageMeta.email"></span></span>
            @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px] text-ink-muted'])
          </a>
        </template>
        <template x-if="pageMeta.phone">
          <a :href="'tel:' + pageMeta.phone" class="wa-card flex items-center gap-md !p-lg transition-shadow hover:shadow-raised">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-primary">
              @include('admin.partials.icon', ['name' => 'phone', 'class' => 'h-[18px] w-[18px]'])
            </span>
            <span class="min-w-0 flex-1"><span class="block text-caption text-ink-secondary">Phone</span><span class="block text-h5 text-ink" x-text="pageMeta.phone_display || pageMeta.phone"></span></span>
            @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px] text-ink-muted'])
          </a>
        </template>
        <template x-if="pageMeta.hours">
          <div class="wa-card flex items-center gap-md !p-lg">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-primary">
              @include('admin.partials.icon', ['name' => 'clock', 'class' => 'h-[18px] w-[18px]'])
            </span>
            <span class="min-w-0 flex-1"><span class="block text-caption text-ink-secondary">Support hours</span><span class="block text-h5 text-ink" x-text="pageMeta.hours"></span></span>
          </div>
        </template>
      </div>
    </template>

    {{-- Help & Support: the FAQ accordion, then Contact support --}}
    <template x-if="pageContent && pageSlug === 'faq'">
      <div>
        <p class="wa-section-title mb-md">Frequently asked</p>
        <p x-show="!pageFaqs.length" class="mb-lg text-bodysm text-ink-secondary">Nothing published yet — use Contact support below and we'll help directly.</p>
        <div class="space-y-md">
          <template x-for="(f, i) in pageFaqs" :key="i">
            <div class="wa-card !p-0">
              <button type="button" @click="pageOpenFaq = pageOpenFaq === i ? -1 : i" :aria-expanded="pageOpenFaq === i"
                      class="flex w-full items-center gap-md px-lg py-md text-left">
                <span class="flex-1 text-h5 text-ink" x-text="f.question"></span>
                <span x-html="ICONS.chevronDown" class="shrink-0 text-ink-muted transition-transform duration-micro [&>svg]:h-5 [&>svg]:w-5"
                      :class="pageOpenFaq === i ? 'rotate-180' : ''"></span>
              </button>
              <div x-show="pageOpenFaq === i" class="px-lg pb-md">
                <p class="whitespace-pre-line text-bodysm text-ink-secondary" x-text="f.answer"></p>
              </div>
            </div>
          </template>
        </div>
        <a href="#/page/contact" class="wa-btn mt-xl w-full">
          @include('admin.partials.icon', ['name' => 'message', 'class' => 'h-5 w-5'])Contact support
        </a>
      </div>
    </template>
  </div>
</template>
