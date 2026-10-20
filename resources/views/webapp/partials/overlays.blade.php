{{-- Toast --}}
<div x-show="toast" x-cloak
     x-transition:enter="transition duration-component ease-out"
     x-transition:enter-start="opacity-0 translate-y-[6px]"
     x-transition:leave="transition duration-micro" x-transition:leave-end="opacity-0"
     class="fixed inset-x-page bottom-[88px] z-[80] mx-auto max-w-[420px] lg:bottom-xl lg:left-auto lg:right-xl lg:mx-0"
     role="status" aria-live="polite">
  <div :class="toastError ? 'border-danger/30 bg-danger-bg text-danger' : 'border-success/30 bg-success-bg text-success'"
       class="flex items-start gap-md rounded-card border-hair px-lg py-md shadow-raised">
    <span x-html="toastError ? ICONS.alert : ICONS.check" class="mt-[1px] shrink-0 [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
    <p class="text-bodysm font-medium" x-text="toast"></p>
  </div>
</div>

{{-- Confirm dialog --}}
<div x-show="confirmBox.open" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-ink/40 px-page"
     x-transition.opacity @keydown.escape.window="confirmBox.open && closeConfirm(false)">
  <div @click.outside="closeConfirm(false)" class="w-full max-w-[400px] rounded-dialog bg-surface p-xl shadow-raised animate-pop-in">
    <h3 class="text-h3 text-ink" x-text="confirmBox.title"></h3>
    <p x-show="confirmBox.message" class="mt-sm text-bodysm text-ink-secondary" x-text="confirmBox.message"></p>
    <div class="mt-xl flex justify-end gap-sm">
      <button @click="closeConfirm(false)" class="h-11 rounded-button px-lg text-btnghost text-ink-secondary hover:bg-surface-muted" x-text="confirmBox.cancel"></button>
      <button @click="closeConfirm(true)"
              :class="confirmBox.danger ? 'bg-danger hover:bg-danger/90' : 'bg-primary hover:bg-primary-dark'"
              class="h-11 rounded-button px-lg text-btnghost font-semibold text-ink-onPrimary" x-text="confirmBox.ok"></button>
    </div>
  </div>
</div>

{{-- Payment method sheet — the app's "Payment method" bottom sheet. --}}
<div x-show="paySheet.open" x-cloak class="fixed inset-0 z-[90] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page" x-transition.opacity>
  <div @click.outside="closePaySheet(false)" class="w-full max-w-[440px] rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog animate-enter-up">
    <h3 class="text-h3 text-ink">Payment method</h3>
    <p class="mt-xs text-bodysm text-ink-secondary">Choose how you'd like to pay.</p>
    <div class="mt-lg space-y-sm">
      <template x-for="m in paySheet.methods" :key="m.key || m.id || m">
        <label :class="paySheet.method === (m.key || m.id || m) ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted'"
               class="flex cursor-pointer items-center gap-md rounded-field border-chip px-lg py-md transition-colors">
          <input type="radio" class="accent-[#EB0401]" :value="m.key || m.id || m" x-model="paySheet.method">
          <span x-html="ICONS[(m.key || m.id || m) === 'card' ? 'creditCard' : ((m.key || m.id || m) === 'upi' ? 'smartphone' : 'building')]" class="text-ink-secondary [&>svg]:h-5 [&>svg]:w-5"></span>
          <span class="text-body font-medium text-ink" x-text="m.label || m.name || m"></span>
        </label>
      </template>
    </div>
    <button @click="closePaySheet(true)" :disabled="!paySheet.method"
            class="mt-xl inline-flex h-[50px] w-full items-center justify-center rounded-button bg-primary text-btn font-semibold text-ink-onPrimary shadow-button hover:bg-primary-dark disabled:bg-hairline disabled:shadow-none">
      Pay <span class="ml-xs" x-text="paySheet.amount"></span>
    </button>
    <button @click="closePaySheet(false)" class="mt-sm h-11 w-full rounded-button text-btnghost text-ink-secondary hover:bg-surface-muted">Cancel</button>
  </div>
</div>
