{{-- The recruiter's Profile tab (profile/views/profile_view.dart, recruiter mode). --}}
@php
  $row = 'flex w-full items-center gap-sm border-b border-hairline-divider py-[14px] text-left';
@endphp
<template x-if="is('rProfile')">
  <div class="wa-narrow">
    <div class="rounded-card bg-surface-dark p-xl text-ink-onPrimary">
      <div class="flex items-center gap-lg">
        <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary text-h2">
          <template x-if="rPrimaryOrg?.logo_url"><img :src="rPrimaryOrg.logo_url" alt="" class="h-full w-full object-cover"></template>
          <template x-if="!rPrimaryOrg?.logo_url && rPrimaryOrg">
            <span x-text="initials(rPrimaryOrg.name)"></span>
          </template>
          <template x-if="!rPrimaryOrg">
            <span x-html="ICONS.building" class="[&>svg]:h-7 [&>svg]:w-7"></span>
          </template>
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-h2" x-text="rProfileName"></p>
          <p class="text-caption text-ink-onPrimary/65" x-text="rProfileSubLine"></p>
        </div>
      </div>
      <div class="mt-xl flex items-baseline">
        <p class="flex-1 text-kicker uppercase text-ink-onPrimary/60">Company profile strength</p>
        <p class="text-[30px] font-bold" x-text="rCompletion + '%'"></p>
      </div>
      <span class="mt-sm block h-2 overflow-hidden rounded-[4px] bg-ink-onPrimary/20"><span class="block h-full bg-primary transition-[width] duration-page" :style="'width:' + rCompletion + '%'"></span></span>
      <div class="mt-sm flex justify-between gap-md">
        <span class="text-bodysm font-semibold" x-text="rStrengthLabel(rCompletion)"></span>
        <span class="text-caption text-ink-onPrimary/75" x-text="rVisibilityLabel"></span>
      </div>
    </div>

    <template x-if="rBoosts.length">
      <div class="mt-xl">
        <p class="wa-section-title mb-sm">Complete these to go further</p>
        <div class="space-y-sm">
          <template x-for="b in rBoosts" :key="b.label">
            <a href="#/r/orgs" class="flex items-center justify-between gap-md rounded-card border-hair border-hairline bg-surface p-md hover:bg-surface-muted">
              <span class="text-bodysm font-semibold text-ink" x-text="b.label"></span>
              <span class="rounded-field bg-primary-light px-sm py-[3px] text-tag font-bold text-primary-dark" x-text="b.gain"></span>
            </a>
          </template>
        </div>
      </div>
    </template>

    <p class="wa-section-title mt-xl">Company details</p>
    <div class="mt-xs">
      <a href="#/subscription" class="{{ $row }}">
        <span :class="rPlanName && rPlanName !== 'Free' ? 'text-success' : 'text-ink-muted'" x-html="rPlanName && rPlanName !== 'Free' ? ICONS.checkCircle : ICONS.crown" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        <span class="flex-1 text-bodysm font-semibold text-ink">My Plan</span>
        <span class="text-caption text-ink-muted" x-text="rPlanName || '—'"></span>
        <span class="text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])</span>
      </a>
      <a href="#/r/orgs" class="{{ $row }}">
        <span :class="rOrgs.length ? 'text-success' : 'text-ink-muted'" x-html="rOrgs.length ? ICONS.checkCircle : ICONS.building" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        <span class="flex-1 text-bodysm font-semibold text-ink">Companies</span>
        <span class="text-caption" :class="rOrgs.length ? 'text-success' : 'text-ink-muted'" x-text="rOrgs.length ? rOrgs.length + ' added' : 'Missing'"></span>
        <span class="text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])</span>
      </a>
      <a href="#/r/contact" class="{{ $row }}">
        <span :class="rHasContact ? 'text-success' : 'text-ink-muted'" x-html="rHasContact ? ICONS.checkCircle : ICONS.phone" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        <span class="flex-1 text-bodysm font-semibold text-ink">Contact details</span>
        <span class="text-caption" :class="rHasContact ? 'text-success' : 'text-ink-muted'" x-text="rHasContact ? 'Complete' : 'Missing'"></span>
        <span class="text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])</span>
      </a>
    </div>

    <p class="wa-section-title mt-xl">Support &amp; Legal</p>
    <div class="mt-xs">
      <template x-for="link in R_SUPPORT_LINKS" :key="link.slug">
        <a :href="'#/page/' + link.slug" class="{{ $row }}">
          <span class="flex-1 text-bodysm font-semibold text-ink" x-text="link.label"></span>
          <span class="text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-[18px] w-[18px]'])</span>
        </a>
      </template>
    </div>

    <div class="mt-xl space-y-sm">
      <button type="button" class="wa-btn-ghost w-full" @click="switchMode()">
        @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-5 w-5'])
        Switch to job search
      </button>
      <a :href="playStoreUrl" class="flex w-full items-center justify-center gap-sm rounded-button border-btn border-hairline-strong py-md text-btnghost text-ink hover:bg-surface-muted">
        @include('admin.partials.icon', ['name' => 'smartphone', 'class' => 'h-[18px] w-[18px]'])
        Get the Android app
      </a>
      <button type="button" class="h-[50px] w-full rounded-button border-btn border-hairline text-btn text-ink-secondary hover:bg-surface-muted" @click="confirmSignOut()">Sign Out</button>
    </div>
  </div>
</template>
