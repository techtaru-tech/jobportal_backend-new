@extends('site.layout')

@php
    $headline = $state['q'] !== '' ? $state['q'].' jobs' : ($state['category'] !== '' ? $state['category'].' jobs' : 'Healthcare jobs');
    $cities = $state['filters']['city'] ?? [];
    $meta = $initial['meta'];
    $total = (int) ($meta['total'] ?? count($initial['list']));
    $pageUrl = fn (int $p) => route('site.jobs', array_filter(request()->except('page', 'job') + ['page' => $p > 1 ? $p : null], fn ($v) => $v !== null && $v !== ''));
@endphp

@section('title', $headline.(count($cities) === 1 ? ' in '.$cities[0] : '').' — Inthes')
@section('description', 'Search and filter nursing, lab, pharmacy and allied healthcare openings by city, experience, salary, job type and shift. Verified employers only.')
@section('body_class', 'bg-canvas-alt')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/jobs-browse.js') }}?v={{ filemtime(public_path('js/site/pages/jobs-browse.js')) }}"></script>
@endpush

@section('content')
<div x-data="jobsBrowse(@js(['initial' => $initial, 'state' => $state, 'groups' => $groups, 'categories' => $categories]))"
     class="mx-auto w-full max-w-[1440px] px-page pb-xxl pt-lg lg:px-xl">

  {{-- ── search bar ─────────────────────────────────────────────────────── --}}
  {{-- A real GET form, so it still searches with scripts off. --}}
  <form action="{{ route('site.jobs') }}" method="GET" @submit.prevent="runSearch()"
        class="flex flex-wrap gap-sm rounded-card border-hair border-hairline bg-surface p-sm shadow-card sm:flex-nowrap">
    <div class="relative w-full sm:w-auto sm:flex-1" @click.outside="sugOpen = false">
      <span aria-hidden="true" class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">
        @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[18px] w-[18px]'])
      </span>
      <input type="search" name="q" value="{{ $state['q'] }}" x-model="q" x-ref="search" autocomplete="off"
             @focus="focusSearch()" @input="sugOpen = true; typed()" @keydown="sugKey($event)"
             placeholder="Job title, skill or employer — try 'nurse', 'lab'…" aria-label="Search jobs"
             class="h-[48px] w-full rounded-field border-hair border-transparent bg-surface-muted pl-[42px] [&::-webkit-search-cancel-button]:hidden pr-10 text-input text-ink outline-none transition-[border-color,box-shadow] duration-micro placeholder:text-ink-muted focus:border-primary focus:shadow-glow">
      <button type="button" x-show="q" x-cloak @click="clearSearch()" aria-label="Clear search"
              class="absolute right-sm top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-field text-ink-muted hover:bg-hairline hover:text-ink">
        <span x-html="ICONS.x" class="[&>svg]:h-4 [&>svg]:w-4"></span>
      </button>

      {{-- Typeahead: suggestions while typing, recent + trending when empty --}}
      <div x-show="sugOpen && sugRows.length" x-cloak x-transition.opacity.duration.100ms
           class="absolute inset-x-0 top-[54px] z-30 overflow-hidden rounded-card border-hair border-hairline bg-surface py-xs shadow-raised">
        <p class="px-lg pb-xs pt-sm text-kicker uppercase text-ink-muted" x-text="q.trim() ? 'Suggestions' : 'Popular searches'"></p>
        <template x-for="(s, i) in sugRows" :key="s.icon + s.term">
          <button type="button" @mousedown.prevent="runSearch(s.term)" @mouseenter="sugIdx = i"
                  :class="sugIdx === i && 'bg-surface-muted'" class="flex w-full items-center gap-md px-lg py-sm text-left">
            <span x-html="ICONS[s.icon]" :class="s.icon === 'trendingUp' ? 'text-primary' : 'text-ink-muted'" class="[&>svg]:h-4 [&>svg]:w-4"></span>
            <span class="flex-1 truncate text-body text-ink" x-text="s.term"></span>
            <span class="text-caption text-ink-muted" x-text="s.meta"></span>
          </button>
        </template>
      </div>
    </div>

    <div class="relative min-w-0 flex-1 sm:w-[220px] sm:flex-none">
      <select name="category" x-model="category" @change="changed()" aria-label="Job category"
              class="h-[48px] w-full cursor-pointer appearance-none rounded-field border-hair border-hairline bg-surface-muted px-md pr-10 text-input text-ink outline-none focus:border-primary focus:shadow-glow">
        <option value="">All categories</option>
        @foreach ($categories as $c)
          <option value="{{ $c }}" @selected($state['category'] === $c)>{{ $c }}</option>
        @endforeach
      </select>
      <span aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">
        @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])
      </span>
    </div>
    <button type="submit" class="wa-btn !h-[48px]">Search</button>
  </form>

  {{-- Phones: filters + sort in one row --}}
  <div class="mt-md flex items-center gap-sm lg:hidden">
    <button type="button" @click="sheetOpen = true"
            :class="activeCount && '!border-primary !text-primary-dark'"
            class="inline-flex h-10 items-center gap-sm rounded-button border-btn border-hairline-strong bg-surface px-lg text-btnghost font-semibold text-ink">
      <span x-html="ICONS.sliders" class="[&>svg]:h-4 [&>svg]:w-4"></span>
      <span x-text="activeCount ? 'Filters (' + activeCount + ')' : 'Filters'">Filters</span>
    </button>
    <span class="flex-1"></span>
    <label class="relative">
      <span class="sr-only">Sort</span>
      <select x-model="sort" @change="changed()" class="h-10 cursor-pointer appearance-none rounded-button border-btn border-hairline-strong bg-surface pl-md pr-9 text-btnghost font-semibold text-ink outline-none">
        <option value="relevant">Most relevant</option>
        <option value="newest">Newest</option>
      </select>
      <span aria-hidden="true" class="pointer-events-none absolute right-sm top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-4 w-4'])</span>
    </label>
  </div>

  <div class="mt-lg lg:grid lg:grid-cols-[210px_minmax(0,350px)_minmax(0,1fr)] lg:gap-lg xl:grid-cols-[230px_400px_minmax(0,1fr)]">

    {{-- ── filters: a sidebar on desktop, a full-screen sheet on phones ──── --}}
    <aside :class="sheetOpen && '!fixed !inset-0 !z-[80] !flex !flex-col !bg-surface'" class="hidden lg:block" aria-label="Filters">
      <div class="flex items-center justify-between border-b border-hairline-divider px-page py-md lg:hidden">
        <h2 class="text-h3 text-ink">Filters</h2>
        <button type="button" @click="sheetOpen = false" aria-label="Close filters" class="inline-flex h-10 w-10 items-center justify-center rounded-field hover:bg-surface-muted">
          <span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
      </div>

      <div class="flex-1 overflow-y-auto px-page py-lg lg:sticky lg:top-[84px] lg:max-h-[calc(100vh-100px)] lg:rounded-card lg:border-hair lg:border-hairline lg:bg-surface lg:px-lg lg:py-lg">
        <div class="flex items-center justify-between">
          <p class="text-h5 text-ink">Filters</p>
          <button type="button" x-show="activeCount || query" x-cloak @click="clearAll()" class="text-caption font-semibold text-primary-dark hover:underline">Clear all</button>
        </div>
        <p class="mt-[2px] text-caption text-ink-muted" aria-live="polite">
          <span x-text="busy ? 'Counting…' : (total === 1 ? '1 job matches' : fmtNumber(total) + ' jobs match')">{{ number_format($total) }} jobs match</span>
        </p>

        <template x-for="g in groups" :key="g.key">
          <div role="group" :aria-label="g.label" class="mt-md border-t border-hairline-divider pt-md">
            <p class="flex w-full items-center justify-between text-label font-semibold text-ink">
              <span x-text="g.label"></span>
              <span x-show="groupCount(g)" class="wa-pill bg-primary-light text-primary-dark" x-text="groupCount(g)"></span>
            </p>
            <div class="mt-xs space-y-[2px]">
              <template x-for="o in g.options" :key="o">
                <label class="flex cursor-pointer items-center gap-sm rounded-field px-xs py-[6px] text-bodysm text-ink hover:bg-surface-muted">
                  <input :type="g.type === 'min' ? 'radio' : 'checkbox'" :name="'f-' + g.key" :checked="isOn(g, o)" @click="toggle(g, o)"
                         class="h-4 w-4 shrink-0 cursor-pointer accent-[#EB0401]">
                  <span x-text="o"></span>
                </label>
              </template>
            </div>
          </div>
        </template>
      </div>

      <div class="flex gap-sm border-t border-hairline-divider px-page py-md lg:hidden" style="padding-bottom: max(12px, env(safe-area-inset-bottom))">
        <button type="button" @click="clearAll()" class="wa-btn-ghost flex-1">Clear</button>
        <button type="button" @click="sheetOpen = false; window.scrollTo({ top: 0 })" class="wa-btn flex-[2]"
                x-text="busy ? 'Updating…' : (total === 1 ? 'Show 1 job' : 'Show ' + fmtNumber(total) + ' jobs')"></button>
      </div>
    </aside>

    {{-- ── results ───────────────────────────────────────────────────────── --}}
    <section class="mt-md min-w-0 lg:mt-0" aria-label="Results">
      <div class="flex items-end justify-between gap-md">
        <div class="min-w-0">
          <h1 class="truncate text-h3 text-ink" x-text="heading">{{ $state['q'] !== '' ? 'Results for “'.$state['q'].'”' : ($state['category'] !== '' ? $state['category'].' jobs' : 'All healthcare jobs') }}</h1>
          <p class="text-caption text-ink-muted" x-text="total === 1 ? '1 job' : fmtNumber(total) + ' jobs'">{{ number_format($total) }} jobs</p>
        </div>
        <label class="relative hidden lg:block">
          <span class="sr-only">Sort</span>
          <select x-model="sort" @change="changed()" class="h-9 cursor-pointer appearance-none rounded-field border-hair border-hairline bg-surface pl-md pr-8 text-bodysm text-ink outline-none focus:border-primary">
            <option value="relevant">Most relevant</option>
            <option value="newest">Newest</option>
          </select>
          <span aria-hidden="true" class="pointer-events-none absolute right-sm top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-4 w-4'])</span>
        </label>
      </div>

      {{-- Applied filters --}}
      <div x-show="chips.length" x-cloak class="mt-md flex flex-wrap items-center gap-xs">
        <template x-for="c in chips" :key="c.key + (c.value || '')">
          <button type="button" @click="removeChip(c)"
                  class="inline-flex items-center gap-xs rounded-chip border-chip border-primary bg-primary-light px-md py-[5px] text-chip font-semibold text-primary-dark hover:bg-primary-light/70">
            <span x-text="c.label"></span><span x-html="ICONS.x" class="[&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
          </button>
        </template>
        <button type="button" @click="clearAll()" class="px-xs text-caption font-semibold text-ink-muted hover:text-primary-dark">Clear all</button>
      </div>

      <div class="mt-md space-y-sm transition-opacity" :class="busy && page === 1 && 'opacity-60'">
        <template x-for="job in list" :key="job.id">
          @include('site.jobs.row', ['select' => 'pick(job, $event)', 'active' => 'isDesktop && selectedId === job.id'])
        </template>
      </div>

      <template x-if="error && !busy">
        <div class="mt-md">@include('site.ui.empty', ['icon' => 'alert', 'title' => 'Something went wrong', 'text' => 'Check your connection and try again.'])</div>
      </template>
      <template x-if="!error && !busy && !list.length">
        <div class="mt-md">
          @include('site.ui.empty', ['icon' => 'search', 'title' => 'No jobs match that', 'text' => 'Try removing a filter or searching for something broader — new jobs go live every day.'])
          <div class="mt-md flex flex-wrap justify-center gap-sm">
            <button type="button" x-show="activeCount || query" @click="clearAll()" class="wa-btn-ghost-sm">Clear filters</button>
            <a href="{{ url('/account/alerts') }}" class="wa-btn-quiet">@include('admin.partials.icon', ['name' => 'bell', 'class' => 'h-4 w-4'])Get a job alert</a>
          </div>
        </div>
      </template>

      <div class="mt-lg text-center" x-show="page < totalPages" x-cloak>
        <button type="button" @click="loadMore()" :disabled="busy" class="wa-btn-ghost-sm min-w-[160px]">
          <span x-text="busy ? 'Loading…' : 'Load more jobs'"></span>
        </button>
      </div>

      {{-- Crawlable copy of the first page, for anything that does not run scripts. --}}
      <noscript>
        <ul class="space-y-sm">
          @foreach ($initial['list'] as $j)
            <li class="rounded-card border-hair border-hairline bg-surface p-lg">
              <a href="{{ route('site.job', $j['code']) }}" class="text-h5 text-ink hover:underline">{{ $j['title'] }}</a>
              <p class="text-bodysm text-ink-secondary">{{ $j['organisation'] }} · {{ $j['city'] }} · {{ $j['salary_display'] ?? '' }} · {{ $j['experience_display'] ?? '' }}</p>
            </li>
          @endforeach
        </ul>
        <p class="mt-md flex justify-between text-bodysm">
          @if (($meta['page'] ?? 1) > 1)<a href="{{ $pageUrl(($meta['page'] ?? 1) - 1) }}" class="text-primary-dark">← Previous</a>@endif
          @if (($meta['page'] ?? 1) < ($meta['total_pages'] ?? 1))<a href="{{ $pageUrl(($meta['page'] ?? 1) + 1) }}" class="text-primary-dark">Next →</a>@endif
        </p>
      </noscript>
    </section>

    {{-- ── the selected job (desktop) ───────────────────────────────────── --}}
    <aside class="hidden min-w-0 lg:block" aria-label="Job details">
      <div x-ref="detail" class="no-scrollbar sticky top-[84px] max-h-[calc(100vh-100px)] overflow-y-auto rounded-card">
        <template x-if="detail">
          <div x-data="{ get job() { return detail } }">@include('site.jobs.detail')</div>
        </template>
        <template x-if="!detail && detailBusy">
          <div class="rounded-card border-hair border-hairline bg-surface">@include('site.ui.spinner')</div>
        </template>
        <template x-if="!detail && !detailBusy && (detailMissing || !list.length)">
          @include('site.ui.empty', ['icon' => 'briefcase', 'title' => 'Pick a job', 'text' => 'Select a job on the left to see the full details here.'])
        </template>
      </div>
    </aside>
  </div>
</div>
@endsection
