{{--
  /account/resume — the resume Inthes writes from the profile.

  Desktop: the PDF previewed in the page with Download / Open / Share beside
  it. Phones: the same actions (a phone opens PDFs in its own viewer). No
  resume yet: what is missing, linked to the profile card that fixes it, or
  "Create my resume". Plus the ₹20 watermark-free one-off.
--}}
@extends('site.account.layout')

@section('title', 'My resume — Inthes')
@section('account_key', 'profile')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-profile-kit.js') }}?v={{ filemtime(public_path('js/site/pages/account-profile-kit.js')) }}"></script>
<script defer src="{{ asset('js/site/pages/account-resume.js') }}?v={{ filemtime(public_path('js/site/pages/account-resume.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountResume()">
  <div class="mb-lg flex items-start gap-md">
    <a href="{{ url('/account') }}" aria-label="Back to profile" class="-ml-sm mt-[2px] inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface">
      @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
    </a>
    <div class="min-w-0 flex-1">
      <h1 class="text-h2 text-ink">My resume</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">Written from your profile and kept up to date with it — change your profile and this changes with it.</p>
    </div>
  </div>

  <template x-if="!loaded">@include('site.ui.spinner')</template>

  {{-- ── Has a resume ─────────────────────────────────────────────────── --}}
  <template x-if="loaded && me?.resume">
    <div class="grid gap-lg lg:grid-cols-[minmax(0,1fr)_280px] lg:items-start">
      <div class="min-w-0">
        {{-- Preview (tablet and up) --}}
        <div class="hidden overflow-hidden rounded-card border-hair border-hairline bg-surface-muted shadow-card md:block">
          <template x-if="previewUrl">
            <iframe :src="previewUrl" title="Resume preview" class="block h-[78vh] min-h-[620px] w-full bg-surface"></iframe>
          </template>
          <template x-if="!previewUrl && !previewFailed">@include('site.ui.spinner')</template>
          <template x-if="previewFailed">
            <div class="flex flex-col items-center px-xl py-xxxl text-center">
              <span x-html="ICONS.fileText" class="text-ink-muted [&>svg]:h-10 [&>svg]:w-10"></span>
              <p class="mt-md text-h5 text-ink">The preview couldn't load here</p>
              <a :href="me.resume_url" target="_blank" rel="noopener" class="wa-btn-ghost-sm mt-md">Open the PDF</a>
            </div>
          </template>
        </div>
        {{-- Phones --}}
        <a :href="me.resume_url" target="_blank" rel="noopener" class="wa-card flex items-center gap-md !p-lg md:hidden">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-field bg-primary-light text-primary"><span x-html="ICONS.fileText" class="[&>svg]:h-6 [&>svg]:w-6"></span></span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-h5 text-ink" x-text="me.resume"></span>
            <span class="block text-caption text-ink-secondary">PDF · tap to open</span>
          </span>
          <span x-html="ICONS.externalLink" class="text-ink-muted [&>svg]:h-5 [&>svg]:w-5"></span>
        </a>
      </div>

      <div class="space-y-lg lg:sticky lg:top-[92px]">
        <div class="wa-card !p-lg">
          <p class="truncate text-h5 text-ink" x-text="me.resume"></p>
          <p class="text-caption text-ink-secondary" x-text="me.resume_watermark_free ? 'Clean copy — no INTHES mark' : 'Carries a faint INTHES mark'"></p>
          <a :href="me.resume_url" :download="me.resume" target="_blank" rel="noopener" class="wa-btn mt-lg w-full">
            <span x-html="ICONS.download" class="[&>svg]:h-5 [&>svg]:w-5"></span> Download
          </a>
          <div class="mt-sm grid grid-cols-2 gap-sm">
            <a :href="me.resume_url" target="_blank" rel="noopener" class="wa-btn-ghost-sm"><span x-html="ICONS.externalLink" class="[&>svg]:h-4 [&>svg]:w-4"></span> Open</a>
            <button type="button" @click="Inthes.share(me.resume, me.resume_url)" class="wa-btn-ghost-sm"><span x-html="ICONS.share" class="[&>svg]:h-4 [&>svg]:w-4"></span> Share</button>
          </div>
          <a href="{{ url('/account') }}" class="mt-md flex items-center justify-center gap-xs text-bodysm font-semibold text-primary hover:underline">
            <span x-html="ICONS.pencil" class="[&>svg]:h-4 [&>svg]:w-4"></span> Edit the profile behind it
          </a>
        </div>

        {{-- Watermark --}}
        <div class="wa-card !p-lg">
          <p class="wa-section-title">Watermark</p>
          <template x-if="me.resume_watermark_free">
            <div class="mt-sm flex items-start gap-sm">
              <span x-html="ICONS.checkCircle" class="mt-[1px] text-success [&>svg]:h-5 [&>svg]:w-5"></span>
              <p class="text-bodysm text-ink">Your resume downloads without the INTHES mark.</p>
            </div>
          </template>
          <template x-if="!me.resume_watermark_free">
            <div class="mt-sm">
              <p class="text-h5 text-ink">Your resume carries the INTHES logo</p>
              <p class="mt-[4px] text-caption text-ink-secondary" x-text="cleanOffer?.description || 'Downloads are free, and the logo sits faintly behind the text.'"></p>
              <template x-if="cleanOffer">
                <div>
                  <button type="button" @click="buyClean()" :disabled="buying" class="wa-btn-sm mt-md w-full">
                    <span x-show="buying" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span>
                    <span x-text="'Remove it for ' + cleanOffer.price_label"></span>
                  </button>
                  <p x-show="!cleanOffer.description" class="mt-xs text-center text-caption text-ink-muted">One payment. Every future version is clean too.</p>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </div>
  </template>

  {{-- ── No resume yet ────────────────────────────────────────────────── --}}
  <template x-if="loaded && me && !me.resume">
    <div class="wa-card mx-auto max-w-[620px] !p-xl text-center">
      <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
        @include('admin.partials.icon', ['name' => 'fileText', 'class' => 'h-6 w-6'])
      </span>
      <h2 class="mt-lg text-h3 text-ink">No resume yet</h2>
      <p class="mt-xs text-bodysm text-ink-secondary">Inthes writes it for you from your profile — nothing to upload.</p>

      {{-- Named one by one, each linked to the card that fixes it. --}}
      <template x-if="profMissingForResume.length">
        <div class="mt-xl rounded-card bg-primary-light p-lg text-left">
          <p class="text-h5 text-ink">Add these and your resume writes itself</p>
          <ul class="mt-sm space-y-sm">
            <template x-for="m in profMissingForResume" :key="m">
              <li class="flex flex-wrap items-center gap-sm">
                <span class="h-[14px] w-[14px] shrink-0 rounded-full border-[1.5px] border-primary"></span>
                <span class="flex-1 text-bodysm text-ink" x-text="m.charAt(0).toUpperCase() + m.slice(1)"></span>
                <a :href="fixLink(m).href" class="wa-btn-ghost-sm !h-8" x-text="fixLink(m).label"></a>
              </li>
            </template>
          </ul>
        </div>
      </template>
      <template x-if="!profMissingForResume.length">
        <button type="button" @click="profGenerateResume()" :disabled="profResumeBusy" class="wa-btn mt-xl w-full sm:w-auto">
          <span x-html="profResumeBusy ? ICONS.loader : ICONS.sparkles" :class="profResumeBusy && 'animate-spin'" class="[&>svg]:h-5 [&>svg]:w-5"></span> Create my resume
        </button>
      </template>
    </div>
  </template>
</div>
@endsection
