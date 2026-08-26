{{--
  Support & Legal — the admin side of `GET /content`.

  Two kinds of content: the four fixed pages the app has a named screen for,
  and an open-ended FAQ list. Every write is audited, the same as verifying an
  employer: this is public-facing legal copy, and six months from now "who
  changed the Privacy Policy and when" should be answerable.
--}}
<template x-if="view === 'content'">
  <div class="animate-enter-up" x-init="loadPages(); loadFaqs()">
    @include('admin.partials.page-header', [
      'kicker' => 'Support & Legal',
      'title' => 'Support &amp; Legal',
      'description' => 'Terms, Privacy, About and Contact, plus the Help & Support FAQ list.',
    ])

    @include('admin.partials.section-eyebrow', [
      'title' => 'Pages', 'hint' => 'The four screens the app knows how to render',
    ])

    <template x-if="pages.busy && !pages.data.length">
      @include('admin.partials.loading-panel', ['label' => 'Loading the pages…'])
    </template>

    <div class="grid grid-cols-1 gap-md lg:grid-cols-2">
      <template x-for="page in pages.data" :key="page.slug">
        <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
          <div class="mb-md flex items-center justify-between gap-md">
            <span class="block text-kicker text-ink-secondary" x-text="page.slug.toUpperCase()"></span>
            <span class="text-caption text-ink-muted"
                  x-text="page.updated_at ? 'Updated ' + timeAgo(page.updated_at) : 'Never edited'"></span>
          </div>

          <label class="block">
            <span class="mb-[5px] block text-label text-ink-secondary">Title</span>
            <input x-model="page.title" :disabled="!canWrite"
                   class="w-full h-[50px] rounded-field bg-surface-muted px-md text-input text-ink border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow disabled:cursor-not-allowed disabled:opacity-60">
          </label>

          <label class="mt-md block">
            <span class="mb-[5px] block text-label text-ink-secondary">Body</span>
            <textarea x-model="page.body" rows="10" :disabled="!canWrite"
                      class="w-full min-h-[92px] resize-y rounded-field bg-surface-muted px-md py-md text-input leading-normal text-ink border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow disabled:cursor-not-allowed disabled:opacity-60"></textarea>
          </label>

          <button x-show="canWrite" @click="savePage(page)"
                  class="mt-md inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                         bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                         hover:bg-primary-dark active:scale-[0.97]">
            Save changes
          </button>
        </div>
      </template>
    </div>

    <div class="mt-xl">
      <div class="mb-md flex items-baseline gap-md">
        <div class="shrink-0">
          <h2 class="text-h4 text-ink">FAQs</h2>
          <p class="mt-[2px] text-caption text-ink-muted">The most commonly asked question belongs first.</p>
        </div>
        <span class="red-rule h-[1px] flex-1 opacity-40" aria-hidden="true"></span>
        <button x-show="canWrite" @click="addFaqOpen = !addFaqOpen"
                class="inline-flex h-[42px] shrink-0 items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                       bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                       hover:bg-primary-dark active:scale-[0.97]">
          <span x-html="ICONS.plus" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
          <span>Add FAQ</span>
        </button>
      </div>

      <div x-show="addFaqOpen" x-cloak class="mb-md rounded-card border-hair border-primary-line bg-primary-light p-lg animate-slide-up">
        <input x-model="newFaq.question" placeholder="Question"
               class="w-full h-[50px] rounded-field bg-surface px-md text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
        <textarea x-model="newFaq.answer" rows="3" placeholder="Answer"
                  class="mt-sm w-full resize-y rounded-field bg-surface px-md py-md text-input leading-normal text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow"></textarea>
        <div class="mt-md flex items-center gap-sm">
          <button @click="addFaq()" :disabled="!newFaq.question.trim() || !newFaq.answer.trim()"
                  class="inline-flex h-[42px] items-center justify-center rounded-button px-md text-btn font-semibold
                         bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                         hover:bg-primary-dark active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
            Add
          </button>
          <button @click="addFaqOpen = false"
                  class="inline-flex h-[42px] items-center justify-center rounded-button px-md text-btn font-semibold
                         bg-surface text-ink border-btn border-hairline transition-[background-color,border-color] duration-micro
                         hover:border-hairline-strong hover:bg-surface-muted">
            Cancel
          </button>
        </div>
      </div>

      <template x-if="!faqs.busy && faqs.data.length === 0">
        <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
          @include('admin.partials.empty-state', [
            'icon' => 'fileText', 'title' => 'No FAQs yet',
            'message' => 'The Help &amp; Support screen is empty until something is added here.',
          ])
        </div>
      </template>

      <div class="space-y-md">
        <template x-for="(faq, idx) in faqs.data" :key="faq.id">
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg"
               :class="!faq.is_active && 'opacity-60'">
            <input x-model="faq.question" :disabled="!canWrite"
                   class="w-full rounded-field bg-transparent px-sm py-xs text-h5 text-ink border-hair border-transparent outline-none transition-[border-color,background-color] duration-micro hover:border-hairline focus:border-primary focus:bg-surface-muted disabled:cursor-not-allowed">
            <textarea x-model="faq.answer" rows="2" :disabled="!canWrite"
                      class="mt-xs w-full resize-y rounded-field bg-transparent px-sm py-xs text-bodysm leading-normal text-ink-secondary border-hair border-transparent outline-none transition-[border-color,background-color] duration-micro hover:border-hairline focus:border-primary focus:bg-surface-muted disabled:cursor-not-allowed"></textarea>

            <div x-show="canWrite" class="mt-md flex flex-wrap items-center gap-sm border-t border-hairline-divider pt-md">
              {{-- Turning `is_active` off is what "keep the text but hide it" is
                   for; delete is a hard delete with nothing to orphan. --}}
              <label class="inline-flex cursor-pointer items-center gap-sm">
                <input type="checkbox" x-model="faq.is_active" class="h-4 w-4 rounded-[4px] accent-[#EB0401]">
                <span class="text-caption text-ink-secondary" x-text="faq.is_active ? 'Shown in the app' : 'Hidden'"></span>
              </label>
              <button @click="saveFaq(faq)"
                      class="inline-flex h-9 items-center justify-center rounded-button px-md text-btnghost font-semibold
                             bg-surface text-primary border-btn border-primary transition-[background-color] duration-micro hover:bg-primary-light">
                Save
              </button>
              <div class="ml-auto flex items-center gap-xs">
                <button @click="moveFaq(idx, -1)" :disabled="idx === 0" aria-label="Move up"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink disabled:opacity-30">
                  <span x-html="ICONS.arrowUp" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </button>
                <button @click="moveFaq(idx, 1)" :disabled="idx === faqs.data.length - 1" aria-label="Move down"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink disabled:opacity-30">
                  <span x-html="ICONS.arrowDown" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </button>
                <button @click="deleteFaq(faq)" aria-label="Delete"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-danger-bg hover:text-danger">
                  <span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
