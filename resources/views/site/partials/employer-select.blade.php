{{-- A labelled select over an Alpine list. Native, so a phone gives it the OS
     picker rather than a custom dropdown that fights the keyboard. --}}
<label class="block">
    <span class="mb-[5px] block text-label text-ink-secondary">{{ $label }}</span>
    <div class="relative">
        <select x-model="{{ $model }}"
                class="h-[50px] w-full cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink
                       border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro
                       focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">{{ $placeholder }}</option>
            <template x-for="item in {{ $items }}" :key="item">
                <option :value="item" x-text="item"></option>
            </template>
        </select>
        <span aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">
            @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])
        </span>
    </div>
</label>
