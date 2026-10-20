{{-- Toast, confirm dialog and payment sheet — driven by `$store.ui` (public/js/site/core.js). --}}
<div x-data>
  {{-- Toast --}}
  <div x-show="$store.ui.toast" x-cloak
       x-transition:enter="transition duration-component ease-out" x-transition:enter-start="opacity-0 translate-y-[6px]"
       x-transition:leave="transition duration-micro" x-transition:leave-end="opacity-0"
       class="fixed inset-x-page bottom-xl z-[99] mx-auto max-w-[420px] lg:left-auto lg:right-xl lg:mx-0" role="status" aria-live="polite">
    <div :class="$store.ui.toastError ? 'border-danger/30 bg-danger-bg text-danger' : 'border-success/30 bg-success-bg text-success'"
         class="flex items-start gap-md rounded-card border-hair px-lg py-md shadow-raised">
      <span x-html="$store.ui.toastError ? ICONS.alert : ICONS.check" class="mt-[1px] shrink-0 [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      <p class="text-bodysm font-medium" x-text="$store.ui.toast"></p>
    </div>
  </div>

  {{-- Confirm --}}
  <div x-show="$store.ui.confirm.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[97] flex items-center justify-center bg-ink/40 px-page"
       @keydown.escape.window="$store.ui.confirm.open && $store.ui.closeConfirm(false)">
    <div @click.outside="$store.ui.closeConfirm(false)" class="w-full max-w-[420px] rounded-dialog bg-surface p-xl shadow-raised animate-pop-in" role="alertdialog">
      <h3 class="text-h3 text-ink" x-text="$store.ui.confirm.title"></h3>
      <p x-show="$store.ui.confirm.message" class="mt-sm text-bodysm text-ink-secondary" x-text="$store.ui.confirm.message"></p>
      <div class="mt-xl flex justify-end gap-sm">
        <button @click="$store.ui.closeConfirm(false)" class="wa-btn-quiet" x-text="$store.ui.confirm.cancel"></button>
        <button @click="$store.ui.closeConfirm(true)"
                :class="$store.ui.confirm.danger ? '!bg-danger hover:!bg-danger/90 !shadow-none' : ''"
                class="wa-btn-sm" x-text="$store.ui.confirm.ok"></button>
      </div>
    </div>
  </div>

  {{-- Payment method --}}
  <div x-show="$store.ui.pay.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[97] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page">
    <div @click.outside="$store.ui.closePay(false)" class="w-full max-w-[440px] rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog animate-pop-in">
      <h3 class="text-h3 text-ink">Payment method</h3>
      <p class="mt-xs text-bodysm text-ink-secondary">Choose how you'd like to pay.</p>
      <div class="mt-lg space-y-sm">
        <template x-for="m in $store.ui.pay.methods" :key="m.key">
          <label :class="$store.ui.pay.method === m.key ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted'"
                 class="flex cursor-pointer items-center gap-md rounded-field border-chip px-lg py-md transition-colors">
            <input type="radio" class="accent-[#EB0401]" :value="m.key" x-model="$store.ui.pay.method">
            <span x-html="ICONS[m.key === 'card' ? 'creditCard' : (m.key === 'upi' ? 'smartphone' : 'building')]" class="text-ink-secondary [&>svg]:h-5 [&>svg]:w-5"></span>
            <span class="text-body font-medium text-ink" x-text="m.label"></span>
          </label>
        </template>
      </div>
      <button @click="$store.ui.closePay(true)" :disabled="!$store.ui.pay.method" class="wa-btn mt-xl w-full">
        Pay <span x-text="$store.ui.pay.amount"></span>
      </button>
      <button @click="$store.ui.closePay(false)" class="wa-btn-quiet mt-sm w-full">Cancel</button>
    </div>
  </div>
</div>
