{{-- The app's bottom navigation — phones and tablets only. --}}
<template x-if="showChrome">
  <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-hairline-divider bg-surface/95 backdrop-blur-md lg:hidden"
       style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="mx-auto flex max-w-[640px]">
      <template x-for="tab in tabs" :key="tab.name">
        <a :href="'#' + tab.path"
           :class="activeTab === tab.name ? 'text-primary' : 'text-ink-muted'"
           class="flex flex-1 flex-col items-center gap-[3px] pb-sm pt-[9px] text-[11px] font-semibold transition-colors">
          <span x-html="ICONS[tab.icon]" class="[&>svg]:h-[22px] [&>svg]:w-[22px]"></span>
          <span x-text="tab.label"></span>
        </a>
      </template>
    </div>
  </nav>
</template>
