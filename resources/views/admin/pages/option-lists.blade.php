{{--
  The reference data behind every picker and chip row in the app.

  These lists reach further than they look: `skills` is what the Smart Apply
  form offers a nurse, `categories` drives the home screen's chip row, and
  `job_types`/`shifts` are validated with `Rule::in` when a job is posted. So a
  careless edit is felt on every installed device at once — which is why usage
  counts are shown before anything can be removed.
--}}
<template x-if="view === 'optionLists'">
  <div class="animate-enter-up" x-init="loadOptionLists()">
    @include('admin.partials.page-header', [
      'kicker' => 'Reference data',
      'title' => 'Reference data',
      'description' => 'Skills, cities, qualifications and the rest — the lists every picker in the app is built from.',
    ])

    <template x-if="optionLists.busy && !optionLists.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the lists…'])
    </template>

    <template x-if="optionLists.data">
      <div class="space-y-xl">
        <div class="grid grid-cols-1 gap-md sm:grid-cols-2 xl:grid-cols-3">
          <template x-for="list in optionLists.data.lists" :key="list.key">
            <button @click="openOptionList(list.key)" class="text-left">
              <div class="group h-full rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg
                          transition-[box-shadow,border-color,transform] duration-micro ease-out
                          hover:-translate-y-[2px] hover:border-hairline-strong hover:shadow-raised">
                <div class="flex items-start justify-between gap-md">
                  <div class="min-w-0">
                    <h3 class="truncate text-h4 text-ink" x-text="list.label"></h3>
                    <p class="mt-[2px] text-caption text-ink-muted">
                      <span class="tabular-nums" x-text="list.count"></span> values
                    </p>
                  </div>
                  {{-- A list nobody has edited has no rows and reads from the
                       config file; the first edit materialises it. --}}
                  <span class="inline-flex shrink-0 items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                        :class="list.is_overridden ? 'bg-primary-light text-primary-dark' : 'bg-surface-muted text-ink-secondary'"
                        x-text="list.is_overridden ? 'Edited' : 'Default'"></span>
                </div>

                <div class="mt-md flex flex-wrap gap-xs">
                  <template x-for="value in list.preview" :key="value">
                    <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-surface-muted text-ink-secondary" x-text="value"></span>
                  </template>
                </div>

                {{-- `job_types` and `shifts` are validated on save, so a value
                     removed here is rejected rather than merely unlisted. --}}
                <template x-if="list.validated">
                  <p class="mt-md text-caption text-warning">Validated on save — editing this rejects existing values.</p>
                </template>
              </div>
            </button>
          </template>
        </div>

        {{-- Named so an operator can see why a list they expected is absent,
             rather than hunting for one that is deliberately not here. --}}
        <template x-if="(optionLists.data.locked || []).length">
          <div>
            @include('admin.partials.section-eyebrow', [
              'title' => 'Not editable', 'hint' => 'These are closed lists the API validates against',
            ])
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card">
              <ul>
                <template x-for="locked in optionLists.data.locked" :key="locked.reason">
                  <li class="border-b border-hairline-divider px-lg py-md last:border-0">
                    <div class="flex flex-wrap gap-xs">
                      <template x-for="key in locked.keys" :key="key">
                        <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-surface-muted text-ink-secondary" x-text="key"></span>
                      </template>
                    </div>
                    <p class="mt-sm text-bodysm text-ink-secondary" x-text="locked.reason"></p>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>

{{-- ── detail ────────────────────────────────────────────────────────── --}}
<template x-if="view === 'optionListDetail'">
  <div class="animate-enter-up">
    <template x-if="optionListDetail.busy && !optionListDetail.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the list…'])
    </template>

    <template x-if="optionListDetail.data">
      <div>
        @include('admin.partials.back', ['to' => 'optionLists', 'label' => 'Back to reference data'])

        <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
          <div class="min-w-0">
            <span class="block text-kicker text-ink-secondary">REFERENCE DATA</span>
            <h1 class="mt-[2px] text-h2 text-ink" x-text="optionListDetail.data.label"></h1>
            <p class="mt-xs text-bodysm text-ink-secondary">
              Order is editorial — the app renders these in the order below, and common answers belong first.
            </p>
          </div>
          <template x-if="canWrite">
            <div class="flex flex-wrap items-center gap-sm">
              <input x-model="newOptionValue" @keydown.enter="addOptionItem()" placeholder="New value"
                     class="h-[46px] w-[200px] rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
              <button @click="addOptionItem()" :disabled="!newOptionValue.trim()"
                      class="inline-flex h-[46px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                             hover:bg-primary-dark active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
                <span x-html="ICONS.plus" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span>Add</span>
              </button>
              {{-- The escape hatch for an edit that went wrong — reverting
                   should not require knowing what the defaults were. --}}
              <button @click="resetOptionList()"
                      class="inline-flex h-[46px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-surface text-ink border-btn border-hairline transition-[background-color,border-color,transform] duration-micro ease-out
                             hover:border-hairline-strong hover:bg-surface-muted active:scale-[0.97]">
                Reset to defaults
              </button>
            </div>
          </template>
        </div>

        <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
          <template x-if="optionListDetail.data.items.length === 0">
            @include('admin.partials.empty-state', [
              'icon' => 'sliders', 'title' => 'Reading from the config file',
              'message' => 'Nobody has edited this list yet. Adding a value materialises the shipped defaults first, so nothing is lost.',
            ])
          </template>

          <template x-if="optionListDetail.data.items.length">
            <div class="thin-scrollbar overflow-x-auto">
              <table class="w-full border-collapse text-left">
                <thead class="sticky top-0 z-10 bg-surface">
                  <tr>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">VALUE</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">IN USE</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">OFFERED</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">ORDER</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="(item, idx) in optionListDetail.data.items" :key="item.id">
                    <tr class="border-b border-hairline-divider last:border-0">
                      <td class="px-lg py-md align-middle">
                        <input :value="item.value" :disabled="!canWrite"
                               @change="updateOptionItem(item, { value: $event.target.value })"
                               class="w-full rounded-field bg-transparent px-sm py-xs text-bodysm text-ink border-hair border-transparent outline-none transition-[border-color,background-color] duration-micro hover:border-hairline focus:border-primary focus:bg-surface-muted disabled:cursor-not-allowed">
                      </td>
                      <td class="px-lg py-md align-middle text-bodysm">
                        {{-- The JSON-column lists would need a per-row search to
                             count exactly; -1 means "not counted", so the panel
                             says so rather than implying a confident zero. --}}
                        <template x-if="item.usage < 0">
                          <span class="text-caption text-ink-muted">not counted</span>
                        </template>
                        <template x-if="item.usage >= 0">
                          <span class="text-caption tabular-nums"
                                :class="item.usage > 0 ? 'text-warning' : 'text-ink-muted'"
                                x-text="item.usage + ' record(s)'"></span>
                        </template>
                      </td>
                      <td class="px-lg py-md align-middle">
                        <label class="inline-flex cursor-pointer items-center gap-sm">
                          <input type="checkbox" :checked="item.is_active" :disabled="!canWrite"
                                 @change="updateOptionItem(item, { is_active: $event.target.checked })"
                                 class="h-4 w-4 rounded-[4px] accent-[#EB0401]">
                          <span class="text-caption text-ink-secondary" x-text="item.is_active ? 'Offered' : 'Hidden'"></span>
                        </label>
                      </td>
                      <td class="px-lg py-md align-middle text-right">
                        <div class="inline-flex items-center gap-xs" x-show="canWrite">
                          <button @click="moveOptionItem(idx, -1)" :disabled="idx === 0" aria-label="Move up"
                                  class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink disabled:opacity-30">
                            <span x-html="ICONS.arrowUp" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                          </button>
                          <button @click="moveOptionItem(idx, 1)" :disabled="idx === optionListDetail.data.items.length - 1" aria-label="Move down"
                                  class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink disabled:opacity-30">
                            <span x-html="ICONS.arrowDown" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                          </button>
                          <button @click="deleteOptionItem(item)" aria-label="Delete"
                                  class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-danger-bg hover:text-danger">
                            <span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </template>
        </div>

        <p class="mt-md text-caption text-ink-muted">
          Values are suggestions, not a whitelist — removing one stops it being offered without
          invalidating the profiles and postings already using it.
        </p>
      </div>
    </template>
  </div>
</template>
