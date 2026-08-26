{{-- The back link on a detail page. --}}
<button @click="go('{{ $to }}')"
        class="mb-lg inline-flex items-center gap-xs rounded-button px-sm py-xs -ml-sm text-btnghost text-ink-secondary transition-colors duration-micro hover:bg-surface-muted hover:text-ink">
  @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-4 w-4'])
  <span>{{ $label }}</span>
</button>
