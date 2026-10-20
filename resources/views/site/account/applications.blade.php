@extends('site.account.layout')

@section('title', 'My applications — Inthes')
@section('account_key', 'applications')

@push('page-scripts')
<script defer src="{{ asset('js/site/pages/account-applications.js') }}?v={{ filemtime(public_path('js/site/pages/account-applications.js')) }}"></script>
@endpush

@section('account')
<div x-data="accountApplications()">
  <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
    <div>
      <h1 class="text-h2 text-ink">My applications</h1>
      <p class="mt-xs text-bodysm text-ink-secondary">Every job you have applied to, and where each one stands.</p>
    </div>
    <a href="{{ route('site.jobs') }}" class="wa-btn-ghost-sm">@include('admin.partials.icon', ['name' => 'search', 'class' => 'h-4 w-4'])Find more jobs</a>
  </div>

  {{-- Status tabs --}}
  <div class="no-scrollbar -mx-page mb-lg flex gap-xs overflow-x-auto px-page pb-xs lg:mx-0 lg:px-0" role="tablist">
    <template x-for="t in tabs" :key="t.key">
      <button type="button" role="tab" :aria-selected="tab === t.key" @click="pick(t.key)" :class="tab === t.key && 'wa-chip-on'" class="wa-chip shrink-0">
        <span x-text="t.label"></span>
        <span class="rounded-full bg-surface px-[7px] text-caption text-ink-secondary" x-text="count(t.key)"></span>
      </button>
    </template>
  </div>

  <template x-if="busy">@include('site.ui.spinner')</template>
  <template x-if="!busy && error">
    <div>@include('site.ui.empty', ['icon' => 'alert', 'title' => 'Something went wrong', 'text' => 'Check your connection and try again.'])</div>
  </template>
  <template x-if="!busy && !error && !list.length">
    <div class="space-y-md text-center">
      @include('site.ui.empty', ['icon' => 'clipboard', 'title' => 'No applications yet', 'text' => 'Start exploring healthcare opportunities — applying takes one click once your profile is set.'])
      <a href="{{ route('site.jobs') }}" class="wa-btn-sm">Find jobs</a>
    </div>
  </template>
  <template x-if="!busy && list.length && !filtered.length">
    <div>@include('site.ui.empty', ['icon' => 'inbox', 'title' => 'Nothing here yet', 'text' => 'Applications in this stage will show up here.'])</div>
  </template>

  {{-- Desktop: a table. Phones: stacked cards. --}}
  <template x-if="!busy && filtered.length">
    <div>
      <div class="hidden overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card md:block">
        <table class="w-full text-left">
          <thead class="border-b border-hairline-divider bg-surface-muted/60">
            <tr class="text-kicker uppercase text-ink-secondary">
              <th class="px-lg py-md font-semibold">Job</th>
              <th class="px-lg py-md font-semibold">Applied</th>
              <th class="px-lg py-md font-semibold">Status</th>
              <th class="w-[180px] px-lg py-md font-semibold">Progress</th>
              <th class="w-10 px-lg py-md"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline-divider">
            <template x-for="a in filtered" :key="a.id">
              <tr @click="open(a)" class="group cursor-pointer transition-colors hover:bg-surface-muted/50">
                <td class="px-lg py-md">
                  <a :href="'/account/applications/' + encodeURIComponent(a.id)" class="flex items-center gap-md">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[12px] font-bold text-primary-dark" x-text="initials(a.job?.organisation)"></span>
                    <span class="min-w-0">
                      <span class="block truncate text-h5 text-ink group-hover:text-primary-dark" x-text="a.job?.title"></span>
                      <span class="block truncate text-bodysm text-ink-secondary" x-text="[a.job?.organisation, a.job?.city].filter(Boolean).join(' · ')"></span>
                    </span>
                  </a>
                </td>
                <td class="whitespace-nowrap px-lg py-md text-bodysm text-ink-secondary" x-text="fmtDate(a.applied_at)"></td>
                <td class="px-lg py-md"><span class="wa-pill" :class="kit.appStatus(a.status).tone" x-text="kit.appStatus(a.status).label"></span></td>
                <td class="px-lg py-md">
                  <div class="h-[6px] overflow-hidden rounded-full bg-surface-muted">
                    <div class="h-full rounded-full" :class="a.status === 'rejected' ? 'bg-ink-muted' : 'bg-primary'" :style="`width:${a.progress_percent ?? 33}%`"></div>
                  </div>
                  <p class="mt-[3px] text-caption text-ink-muted" x-text="kit.appStatus(a.status).note"></p>
                </td>
                <td class="px-lg py-md text-ink-muted"><span x-html="ICONS.chevronRight" class="nudge inline-block [&>svg]:h-4 [&>svg]:w-4"></span></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="space-y-sm md:hidden">
        <template x-for="a in filtered" :key="a.id">
          <a :href="'/account/applications/' + encodeURIComponent(a.id)" class="wa-card flex items-start gap-md !p-lg">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-logo bg-primary-light text-[13px] font-bold text-primary-dark" x-text="initials(a.job?.organisation)"></span>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-sm">
                <h3 class="truncate text-h5 text-ink" x-text="a.job?.title"></h3>
                <span class="wa-pill shrink-0" :class="kit.appStatus(a.status).tone" x-text="kit.appStatus(a.status).label"></span>
              </div>
              <p class="mt-[2px] truncate text-bodysm text-ink-secondary" x-text="[a.job?.organisation, a.job?.city].filter(Boolean).join(' · ')"></p>
              <p class="mt-xs text-caption text-ink-muted" x-text="'Applied ' + fmtDate(a.applied_at)"></p>
              <div class="mt-sm h-[5px] overflow-hidden rounded-full bg-surface-muted">
                <div class="h-full rounded-full" :class="a.status === 'rejected' ? 'bg-ink-muted' : 'bg-primary'" :style="`width:${a.progress_percent ?? 33}%`"></div>
              </div>
            </div>
          </a>
        </template>
      </div>
    </div>
  </template>
</div>
@endsection
