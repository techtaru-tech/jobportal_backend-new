{{-- Shared component classes for the signed-in pages (account, employer, popups). Tailwind's play build reads `text/tailwindcss` blocks, so these resolve against the design tokens. --}}
<style type="text/tailwindcss">
@layer components {
  .wa-page { @apply mx-auto w-full max-w-[1200px] px-page py-xl lg:px-xl; }
  .wa-narrow { @apply mx-auto w-full max-w-[720px] px-page py-xl; }
  .wa-card { @apply rounded-card border-hair border-hairline bg-surface p-xl shadow-card; }
  .wa-btn { @apply inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-primary px-xl text-btn font-semibold text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-hairline disabled:text-ink-muted disabled:shadow-none; }
  .wa-btn-sm { @apply inline-flex h-10 items-center justify-center gap-sm rounded-button bg-primary px-lg text-btnghost font-semibold text-ink-onPrimary shadow-button transition-colors duration-micro hover:bg-primary-dark disabled:bg-hairline disabled:shadow-none; }
  .wa-btn-ghost { @apply inline-flex h-[50px] items-center justify-center gap-sm rounded-button border-btn border-primary bg-surface px-xl text-btn font-semibold text-primary transition-colors duration-micro hover:bg-primary-light disabled:border-hairline disabled:text-ink-muted; }
  .wa-btn-ghost-sm { @apply inline-flex h-10 items-center justify-center gap-sm rounded-button border-btn border-primary bg-surface px-lg text-btnghost font-semibold text-primary transition-colors duration-micro hover:bg-primary-light disabled:border-hairline disabled:text-ink-muted; }
  .wa-btn-quiet { @apply inline-flex h-10 items-center justify-center gap-sm rounded-button px-md text-btnghost font-semibold text-ink-secondary transition-colors duration-micro hover:bg-surface-muted hover:text-ink; }
  .wa-label { @apply mb-[5px] block text-label text-ink-secondary; }
  .wa-input { @apply h-[50px] w-full rounded-field border-hair border-hairline bg-surface-muted px-md text-input text-ink outline-none transition-[border-color,box-shadow] duration-micro placeholder:text-ink-muted focus:border-primary focus:shadow-glow; }
  .wa-textarea { @apply w-full rounded-field border-hair border-hairline bg-surface-muted px-md py-md text-input text-ink outline-none transition-[border-color,box-shadow] duration-micro placeholder:text-ink-muted focus:border-primary focus:shadow-glow; }
  .wa-chip { @apply inline-flex items-center gap-xs rounded-chip border-chip border-transparent bg-surface-muted px-lg py-sm text-chip text-ink transition-colors duration-150 hover:bg-hairline; }
  .wa-chip-on { @apply border-primary bg-primary-light font-semibold text-primary-dark hover:bg-primary-light; }
  .wa-tag { @apply inline-flex items-center gap-xs rounded-field bg-surface-muted px-sm py-[3px] text-tag text-ink-secondary; }
  .wa-pill { @apply inline-flex items-center rounded-full px-sm py-[2px] text-[11.5px] font-semibold; }
  .wa-error { @apply mt-xs text-caption text-danger; }
  .wa-section-title { @apply text-kicker uppercase text-ink-secondary; }
}
</style>
