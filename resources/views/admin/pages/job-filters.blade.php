{{--
  The filter sheet the app draws, as data.

  Reference data edits the *values* under a filter — add a city and it becomes
  a chip. This edits the filters themselves, which used to live in
  `config/options.php`: adding one meant a code change and a deploy.

  A group is not a value, so it gets a form. It carries the query parameter
  the app sends, the list its chips come from, the column it matches and
  whether it matches as a set or a threshold — and `GET /jobs` reads the same
  declaration for its whitelist, so a filter added here starts working the
  moment it is saved.
--}}
<template x-if="view === 'jobFilters'">
  <div class="animate-enter-up" x-init="loadJobFilters()">
    @include('admin.partials.page-header', [
      'kicker' => 'Job filters',
      'title' => 'Job filters',
      'description' => 'What a candidate can narrow the job list by. The app renders exactly what is here — a filter added below appears on the next cold start, with no app release.',
    ])

    <template x-if="jobFilters.busy && !jobFilters.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the filters…'])
    </template>

    <template x-if="jobFilters.data">
      <div>
        <div class="mb-lg flex flex-wrap items-center justify-between gap-md">
          <div class="flex items-center gap-sm">
            {{-- Before the first edit the filters come from the config file
                 and have no rows, so there is nothing to reorder or remove
                 yet — the first save copies the shipped set in. --}}
            <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                  :class="jobFilters.data.is_overridden ? 'bg-primary-light text-primary-dark' : 'bg-surface-muted text-ink-secondary'"
                  x-text="jobFilters.data.is_overridden ? 'Edited' : 'Shipped defaults'"></span>
            <span class="text-caption text-ink-muted">
              <span class="tabular-nums" x-text="jobFilters.data.groups.length"></span> filters
            </span>
          </div>

          <template x-if="canWrite">
            <div class="flex flex-wrap items-center gap-sm">
              <button @click="editJobFilter(null)"
                      class="inline-flex h-[46px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                             hover:bg-primary-dark active:scale-[0.97]">
                <span x-html="ICONS.plus" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span>Add filter</span>
              </button>
              <button @click="resetJobFilters()" x-show="jobFilters.data.is_overridden"
                      class="inline-flex h-[46px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-surface text-ink border-btn border-hairline transition-[background-color,border-color,transform] duration-micro ease-out
                             hover:border-hairline-strong hover:bg-surface-muted active:scale-[0.97]">
                Reset to defaults
              </button>
            </div>
          </template>
        </div>

        {{-- ── the form ──────────────────────────────────────────────── --}}
        <template x-if="filterForm">
          <div class="mb-lg rounded-card border-hair border-hairline bg-surface p-lg shadow-card">
            <h3 class="text-h4 text-ink" x-text="filterForm.id ? 'Edit filter' : 'New filter'"></h3>
            <p class="mt-xs text-bodysm text-ink-secondary">
              The heading is what a candidate reads. Everything else is how it behaves.
            </p>

            <div class="mt-md grid grid-cols-1 gap-md sm:grid-cols-2">
              <label class="block">
                <span class="block text-kicker text-ink-secondary">HEADING</span>
                <input x-model="filterForm.label" @input="suggestFilterKeys()" placeholder="e.g. City"
                       class="mt-xs h-[46px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
              </label>

              <label class="block">
                <span class="block text-kicker text-ink-secondary">VALUES FROM</span>
                <select x-model="filterForm.list"
                        class="mt-xs h-[46px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-transparent outline-none focus:border-focus focus:border-primary">
                  <template x-for="list in jobFilters.data.lists" :key="list">
                    <option :value="list" x-text="list.replace(/_/g, ' ')"></option>
                  </template>
                </select>
                <span class="mt-xs block text-caption text-ink-muted">
                  The chips come from this list — edit them under Reference data.
                </span>
              </label>

              <label class="block">
                <span class="block text-kicker text-ink-secondary">MATCHES COLUMN</span>
                <select x-model="filterForm.column"
                        class="mt-xs h-[46px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-transparent outline-none focus:border-focus focus:border-primary">
                  <template x-for="column in jobFilters.data.columns" :key="column">
                    <option :value="column" x-text="column"></option>
                  </template>
                </select>
                {{-- The list is served rather than typed: a filter pointed at
                     any other column would read it back through the public
                     job search one value at a time. --}}
                <span class="mt-xs block text-caption text-ink-muted">
                  Only these columns can be filtered on.
                </span>
              </label>

              <label class="block">
                <span class="block text-kicker text-ink-secondary">MATCHES HOW</span>
                <select x-model="filterForm.type"
                        class="mt-xs h-[46px] w-full rounded-field bg-surface-muted px-md text-input text-ink border-hair border-transparent outline-none focus:border-focus focus:border-primary">
                  <option value="in">Any of the picked values</option>
                  <option value="min">At least this much (₹ threshold)</option>
                </select>
              </label>

              <label class="block">
                <span class="block text-kicker text-ink-secondary">KEY</span>
                <input x-model="filterForm.key" @input="filterKeyTouched = true" placeholder="city"
                       class="mt-xs h-[46px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
                <span class="mt-xs block text-caption text-ink-muted">
                  How the app remembers a candidate's picks. Changing it forgets them.
                </span>
              </label>

              <label class="block">
                <span class="block text-kicker text-ink-secondary">QUERY PARAMETER</span>
                <input x-model="filterForm.param" @input="filterParamTouched = true" placeholder="city"
                       class="mt-xs h-[46px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
                <span class="mt-xs block text-caption text-ink-muted">
                  What the app sends to the job search. Lowercase, underscores.
                </span>
              </label>
            </div>

            <div class="mt-lg flex flex-wrap items-center gap-sm">
              <button @click="saveJobFilter()"
                      :disabled="!filterForm.label.trim() || !filterForm.key.trim() || !filterForm.param.trim()"
                      class="inline-flex h-[46px] items-center justify-center rounded-button px-lg text-btn font-semibold
                             bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                             hover:bg-primary-dark active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
                Save filter
              </button>
              <button @click="closeJobFilterForm()"
                      class="inline-flex h-[46px] items-center justify-center rounded-button px-lg text-btn font-semibold
                             bg-surface text-ink border-btn border-hairline transition-[background-color,border-color] duration-micro
                             hover:border-hairline-strong hover:bg-surface-muted">
                Cancel
              </button>
            </div>
          </div>
        </template>

        {{-- ── the filters ───────────────────────────────────────────── --}}
        <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface">
          <template x-if="jobFilters.data.groups.length === 0">
            @include('admin.partials.empty-state', [
              'icon' => 'funnel', 'title' => 'No filters',
              'message' => 'The app will show a filter button with nothing under it. Add a filter, or reset to the shipped defaults.',
            ])
          </template>

          <template x-if="jobFilters.data.groups.length">
            <div class="thin-scrollbar overflow-x-auto">
              <table class="w-full border-collapse text-left">
                <thead class="sticky top-0 z-10 bg-surface">
                  <tr>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">HEADING</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">VALUES FROM</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">MATCHES</th>
                    <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">ORDER</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="(group, idx) in jobFilters.data.groups" :key="group.key">
                    <tr class="border-b border-hairline-divider last:border-0">
                      <td class="px-lg py-md align-middle">
                        <div class="text-bodysm text-ink" x-text="group.label"></div>
                        <div class="text-caption text-ink-muted">
                          <span x-text="group.key"></span> · sends <span x-text="group.param"></span>
                        </div>
                      </td>
                      <td class="px-lg py-md align-middle">
                        <button @click="openOptionList(group.list)"
                                class="text-bodysm text-primary underline-offset-2 hover:underline"
                                x-text="group.list.replace(/_/g, ' ')"></button>
                      </td>
                      <td class="px-lg py-md align-middle text-caption text-ink-secondary">
                        <span x-text="group.column"></span>
                        <span x-text="group.type === 'min' ? ' · at least' : ' · any of'"></span>
                      </td>
                      <td class="px-lg py-md align-middle text-right">
                        <div class="inline-flex items-center gap-xs" x-show="canWrite">
                          <button @click="editJobFilter(group)" aria-label="Edit"
                                  class="inline-flex h-8 items-center justify-center rounded-field px-sm text-caption text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink">
                            Edit
                          </button>
                          {{-- Reordering and removing need a row to act on,
                               and the shipped set has none until it is
                               materialised by the first save. --}}
                          <button @click="moveJobFilter(idx, -1)" :disabled="idx === 0 || !group.id" aria-label="Move up"
                                  class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink disabled:opacity-30">
                            <span x-html="ICONS.arrowUp" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                          </button>
                          <button @click="moveJobFilter(idx, 1)" :disabled="idx === jobFilters.data.groups.length - 1 || !group.id" aria-label="Move down"
                                  class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-surface-muted hover:text-ink disabled:opacity-30">
                            <span x-html="ICONS.arrowDown" class="[&>svg]:h-4 [&>svg]:w-4"></span>
                          </button>
                          <button @click="deleteJobFilter(group)" :disabled="!group.id" aria-label="Delete"
                                  class="inline-flex h-8 w-8 items-center justify-center rounded-field text-ink-muted transition-colors duration-micro hover:bg-danger-bg hover:text-danger disabled:opacity-30">
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
          The app caches this for the length of a session, so a change here reaches a device on its
          next cold start. A filter whose list has no values is not shown at all — a heading with no
          chips under it is worse than no heading.
        </p>
      </div>
    </template>
  </div>
</template>
