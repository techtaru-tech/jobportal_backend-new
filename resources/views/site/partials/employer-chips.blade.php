{{--
  A multi-select over a suggested list.

  Chips rather than a multi-select box: these lists run to dozens of values, and
  a native multi-select on a phone is close to unusable. What is chosen stays
  visible, which is the part that matters when there are eight of them.
--}}
<div>
    <span class="block text-label text-ink-secondary">{{ $label }}</span>
    @isset($hint)
        <p class="mt-[2px] text-caption text-ink-muted">{{ $hint }}</p>
    @endisset

    <div class="mt-md flex flex-wrap gap-xs">
        <template x-for="item in {{ $items }}" :key="item">
            <button type="button" @click="toggleIn({{ $list }}, item)"
                    :class="{{ $list }}.includes(item)
                        ? 'border-primary bg-primary-light font-semibold text-primary-dark'
                        : 'border-transparent bg-surface-muted text-ink hover:bg-hairline'"
                    class="inline-flex items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip transition-colors duration-150">
                <span x-show="{{ $list }}.includes(item)" x-html="ICONS.check"
                      class="[&>svg]:h-[13px] [&>svg]:w-[13px] text-primary"></span>
                <span x-text="item"></span>
            </button>
        </template>

        <template x-if="{{ $items }}.length === 0">
            <span class="text-bodysm text-ink-muted">Nothing to choose from yet.</span>
        </template>
    </div>
</div>
