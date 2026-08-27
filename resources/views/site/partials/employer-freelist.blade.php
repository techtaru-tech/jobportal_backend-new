{{--
  A free-text list the recruiter types themselves — duties, benefits.

  No suggestions here on purpose: what a specific unit needs someone to do is
  not something a dropdown can offer, and a canned list would get picked over
  the truth because it is quicker.
--}}
<div>
    <span class="block text-label text-ink-secondary">{{ $label }}</span>

    <div class="mt-md flex items-center gap-sm">
        <input x-model="form.{{ $draft }}" @keydown.enter.prevent="addTo({{ $list }}, '{{ $draft }}')"
               placeholder="{{ $placeholder }}"
               class="h-[46px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                      border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                      focus:border-focus focus:border-primary focus:shadow-glow">
        <button type="button" @click="addTo({{ $list }}, '{{ $draft }}')" :disabled="!form.{{ $draft }}.trim()"
                class="inline-flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-button bg-primary text-ink-onPrimary shadow-button
                       transition-[background-color,transform] duration-micro hover:bg-primary-dark active:scale-[0.97]
                       disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
            <span x-html="ICONS.plus" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        </button>
    </div>

    <ul class="mt-sm space-y-xs" x-show="{{ $list }}.length">
        <template x-for="(item, i) in {{ $list }}" :key="i">
            <li class="flex items-start gap-md rounded-field bg-surface-muted px-md py-sm">
                <span class="mt-[7px] h-[5px] w-[5px] shrink-0 rounded-full bg-primary"></span>
                <span class="min-w-0 flex-1 text-bodysm text-ink" x-text="item"></span>
                <button type="button" @click="removeAt({{ $list }}, i)" aria-label="Remove"
                        class="shrink-0 text-ink-muted transition-colors hover:text-danger">
                    <span x-html="ICONS.x" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </button>
            </li>
        </template>
    </ul>
</div>
