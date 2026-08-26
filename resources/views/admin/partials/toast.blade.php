{{--
  Transient feedback, bottom-right. Tinted by outcome rather than always red:
  a success message in the brand colour reads as an error at a glance.
--}}
<div x-show="toast" x-cloak
     x-transition:enter="transition duration-component ease-out"
     x-transition:enter-start="opacity-0 translate-y-[6px]"
     x-transition:leave="transition duration-micro" x-transition:leave-end="opacity-0"
     class="fixed bottom-xl right-xl z-[60] max-w-[380px]" role="status" aria-live="polite">
  <div :class="toastError ? 'border-danger/30 bg-danger-bg text-danger' : 'border-success/30 bg-success-bg text-success'"
       class="flex items-start gap-md rounded-card border-hair px-lg py-md shadow-raised">
    <span x-html="toastError ? ICONS.alert : ICONS.check" class="[&>svg]:h-[18px] [&>svg]:w-[18px] shrink-0 mt-[1px]"></span>
    <p class="text-bodysm font-medium" x-text="toast"></p>
  </div>
</div>
