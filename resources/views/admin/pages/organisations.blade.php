{{--
  Employers, and the verification queue.

  Verification is not a badge — `JobPosting::isPubliclyVisible()` gates on it,
  so approving here is what makes an employer's postings reachable at all.
  The detail page states that number up front: the difference between an admin
  approving a record and an admin knowing they are publishing three jobs.
--}}
<template x-if="view === 'organisations'">
  <div class="animate-enter-up" x-init="loadOrgs()">
    @include('admin.partials.page-header', [
      'kicker' => 'Employers',
      'title' => 'Employers',
      'description' => 'Until an employer is verified, none of their postings reach candidates.',
    ])

    @include('admin.partials.list-shell', [
      'state' => 'orgs',
      'loader' => 'loadOrgs',
      'cols' => 5,
      'placeholder' => 'Search name, GST or address…',
      'emptyTitle' => 'No employers match',
      'emptyMessage' => 'Try a different search or filter.',
      'filters' => '
        <div class="relative">
          <select x-model="orgs.state" @change="loadOrgs(1)" aria-label="Review state"
                  class="h-[46px] w-[180px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">Any state</option>
            <option value="pending">Awaiting review</option>
            <option value="verified">Verified</option>
            <option value="no_document">No document</option>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>
        <button @click="orgs.dup = !orgs.dup; loadOrgs(1)"
                :class="orgs.dup ? \'border-primary bg-primary-light font-semibold text-primary-dark\' : \'border-transparent bg-surface-muted text-ink hover:bg-hairline\'"
                class="inline-flex shrink-0 items-center gap-xs rounded-chip border-chip px-lg py-sm text-chip whitespace-nowrap transition-colors duration-150">
          <span x-show="orgs.dup" x-html="ICONS.check" class="[&>svg]:h-[14px] [&>svg]:w-[14px] text-primary"></span>
          Duplicate GST
        </button>',

      'head' => '
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">EMPLOYER</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden md:table-cell">GST</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">REVIEW</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">POSTINGS</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right hidden lg:table-cell">REGISTERED</th>',

      'body' => '
        <template x-for="row in orgs.data" :key="row.id">
          <tr @click="openOrganisation(row.id)"
              class="group border-b border-hairline-divider last:border-0 cursor-pointer transition-colors duration-micro hover:bg-canvas-alt">
            <td class="relative px-lg py-md align-middle text-bodysm text-ink">
              <span aria-hidden="true" class="absolute inset-y-0 left-0 w-[2px] origin-top scale-y-0 bg-primary transition-transform duration-micro ease-out group-hover:scale-y-100"></span>
              <div class="flex items-center gap-md">
                <span aria-hidden="true" x-text="initials(row.name)" style="font-size:11.6px"
                      class="inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                <div class="min-w-0">
                  <span class="block truncate font-semibold text-ink" x-text="row.name"></span>
                  <span class="block truncate text-caption text-ink-muted" x-text="row.owner?.phone || \'No owner\'"></span>
                </div>
              </div>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink hidden md:table-cell">
              <span class="tabular-nums" x-text="row.gst_number || \'—\'"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm">
              <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                    :class="{
                      \'bg-primary-light text-primary-dark\': row.review_state === \'verified\',
                      \'bg-warning-bg text-warning\': row.review_state === \'pending\',
                      \'bg-surface-muted text-ink-secondary\': row.review_state === \'no_document\',
                    }"
                    x-text="{ verified: \'Verified\', pending: \'Awaiting review\', no_document: \'No document\' }[row.review_state]"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-right tabular-nums">
              <span x-text="row.active_jobs"></span><span class="text-ink-muted" x-text="\' / \' + row.jobs"></span>
            </td>
            <td class="px-lg py-md align-middle text-right hidden lg:table-cell">
              <span class="text-caption text-ink-muted" x-text="timeAgo(row.created_at)"></span>
            </td>
          </tr>
        </template>',
    ])
  </div>
</template>

{{-- ── detail ────────────────────────────────────────────────────────── --}}
<template x-if="view === 'organisationDetail'">
  <div class="animate-enter-up">
    <template x-if="orgDetail.busy && !orgDetail.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the employer…'])
    </template>

    <template x-if="orgDetail.data">
      <div>
        @include('admin.partials.back', ['to' => 'organisations', 'label' => 'Back to employers'])

        <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
          <div class="flex min-w-0 items-center gap-lg">
            <span aria-hidden="true" x-text="initials(orgDetail.data.organisation.name)" style="font-size:17px"
                  class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
            <div class="min-w-0">
              <span class="block text-kicker text-ink-secondary">EMPLOYER</span>
              <h1 class="mt-[2px] truncate text-h2 text-ink" x-text="orgDetail.data.organisation.name"></h1>
              <p class="mt-xs text-bodysm text-ink-secondary" x-text="orgDetail.data.organisation.gst_number || 'No GST number'"></p>
            </div>
          </div>
          <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                :class="orgDetail.data.organisation.verified ? 'bg-primary-light text-primary-dark' : 'bg-surface-muted text-ink-secondary'"
                x-text="orgDetail.data.organisation.verified ? 'Verified' : 'Unverified'"></span>
        </div>

        {{--
          What actually changes for candidates the moment the switch is
          flipped. Stating the number up front is the difference between
          approving a record and knowing you are publishing three jobs.
        --}}
        <template x-if="canWrite && !orgDetail.data.organisation.verified">
          <div class="mb-md flex flex-wrap items-center justify-between gap-md rounded-card border-hair border-primary-line bg-primary-light p-lg">
            <div class="min-w-0">
              <p class="text-bodysm font-semibold text-primary-dark">
                <template x-if="orgDetail.data.impact.currently_hidden">
                  <span><span x-text="orgDetail.data.impact.active_postings"></span> active posting(s) are hidden from candidates pending this decision.</span>
                </template>
                <template x-if="!orgDetail.data.impact.currently_hidden">
                  <span>This employer is not verified.</span>
                </template>
              </p>
              <p class="mt-[2px] text-caption text-ink-secondary">Verifying grants the trust badge every candidate sees on their postings.</p>
            </div>
            <div class="flex flex-wrap items-center gap-sm">
              <template x-if="orgDetail.data.organisation.document_url">
                <a :href="orgDetail.data.organisation.document_url" target="_blank" rel="noopener"
                   class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                          bg-surface text-ink border-btn border-hairline transition-[background-color,border-color,transform] duration-micro ease-out
                          hover:border-hairline-strong hover:bg-surface-muted active:scale-[0.97]">
                  <span x-html="ICONS.fileText" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                  <span>View GST document</span>
                </a>
              </template>
              <button @click="verifyOrg()"
                      class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                             bg-primary text-ink-onPrimary shadow-button transition-[background-color,transform] duration-micro ease-out
                             hover:bg-primary-dark active:scale-[0.97]">
                <span x-html="ICONS.badgeCheck" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                <span>Verify employer</span>
              </button>
            </div>
          </div>
        </template>

        {{-- Withdrawing removes public trust from a live employer, so the
             reason is required: six months later it is the only thing that
             will explain why. --}}
        <template x-if="canWrite && orgDetail.data.organisation.verified">
          <div class="mb-md flex flex-col gap-sm rounded-card border-hair border-hairline bg-surface shadow-card p-lg sm:flex-row sm:items-center">
            <input x-model="orgUnverifyReason" placeholder="Reason for withdrawing verification (required)"
                   class="h-[46px] flex-1 rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <button @click="unverifyOrg()" :disabled="orgUnverifyReason.trim().length < 3"
                    class="inline-flex h-[46px] shrink-0 items-center justify-center rounded-button px-md text-btn font-semibold
                           bg-surface text-danger border-btn border-danger transition-[background-color,transform] duration-micro ease-out
                           hover:bg-danger-bg active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50 disabled:border-hairline">
              Withdraw verification
            </button>
          </div>
        </template>

        <div class="grid grid-cols-1 gap-md lg:grid-cols-3">
          <div class="lg:col-span-2 space-y-md">
            {{--
              The checks a human would run before granting the badge.
              Deliberately advisory: nothing here blocks the button. What it
              removes is the need to go hunting for each fact across four cards.
            --}}
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Review checklist</h2></div>
              <ul class="space-y-md">
                <template x-for="check in orgDetail.data.review" :key="check.key">
                  <li class="flex items-start gap-md">
                    <span :class="checkClass(check.status)"
                          class="inline-flex shrink-0 items-center rounded-chip px-[7px] py-[2px] text-caption font-semibold uppercase"
                          x-text="check.status"></span>
                    <div class="min-w-0">
                      <p class="text-bodysm font-semibold text-ink" x-text="check.label"></p>
                      <p class="text-caption text-ink-secondary" x-text="check.detail"></p>
                    </div>
                  </li>
                </template>
              </ul>
            </div>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Profile</h2></div>
              <div class="grid gap-lg grid-cols-1 sm:grid-cols-2">
                @include('admin.partials.field', ['label' => 'Industry', 'value' => 'orgDetail.data.organisation.industry'])
                @include('admin.partials.field', ['label' => 'Size', 'value' => 'orgDetail.data.organisation.size'])
                @include('admin.partials.field', ['label' => 'Website', 'value' => 'orgDetail.data.organisation.website'])
                @include('admin.partials.field', ['label' => 'Address', 'value' => 'orgDetail.data.organisation.address'])
              </div>
              <template x-if="orgDetail.data.organisation.about">
                <div class="mt-lg">
                  <span class="block text-kicker text-ink-secondary">ABOUT</span>
                  <p class="mt-xs whitespace-pre-line text-bodysm text-ink" x-text="orgDetail.data.organisation.about"></p>
                </div>
              </template>
            </div>

            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="border-b border-hairline-divider px-lg py-md">
                <h2 class="text-h4 text-ink">Postings
                  <span class="font-normal text-ink-muted" x-text="'(' + orgDetail.data.jobs.length + ')'"></span>
                </h2>
              </div>
              <template x-if="orgDetail.data.jobs.length === 0">
                <p class="p-lg text-bodysm text-ink-muted">This employer has never posted a job.</p>
              </template>
              <ul class="max-h-[400px] overflow-y-auto thin-scrollbar">
                <template x-for="job in orgDetail.data.jobs" :key="job.id">
                  <li class="border-b border-hairline-divider last:border-0">
                    <button @click="openJob(job.id)"
                            class="flex w-full items-center justify-between gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                      <span class="min-w-0">
                        <span class="block truncate text-bodysm font-semibold text-ink" x-text="job.title"></span>
                        <span class="block truncate text-caption text-ink-muted" x-text="job.city + ' · ' + timeAgo(job.posted_at)"></span>
                      </span>
                      <span x-html="statusDot(job.status, 'job')"></span>
                    </button>
                  </li>
                </template>
              </ul>
            </div>
          </div>

          <div class="space-y-md">
            <template x-if="orgDetail.data.owner">
              <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
                <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Account holder</h2></div>
                <button @click="openUser(orgDetail.data.owner.id)"
                        class="flex w-full items-center justify-between gap-md rounded-field p-sm -m-sm text-left transition-colors duration-micro hover:bg-canvas-alt">
                  <span class="text-bodysm text-ink" x-text="orgDetail.data.owner.phone"></span>
                  <span x-html="ICONS.chevronRight" class="[&>svg]:h-4 [&>svg]:w-4 text-ink-muted"></span>
                </button>
                {{-- Other employers under the same account — relevant context
                     when judging one of them. --}}
                <template x-if="orgDetail.data.owner.other_organisations.length">
                  <div class="mt-md border-t border-hairline-divider pt-md">
                    <span class="block text-kicker text-ink-secondary">ALSO RUNS</span>
                    <ul class="mt-sm space-y-xs">
                      <template x-for="other in orgDetail.data.owner.other_organisations" :key="other.id">
                        <li>
                          <button @click="openOrganisation(other.id)"
                                  class="flex w-full items-center justify-between gap-md text-left text-bodysm text-ink transition-colors hover:text-primary-dark">
                            <span class="truncate" x-text="other.name"></span>
                            <span class="shrink-0 text-caption" :class="other.verified ? 'text-success' : 'text-ink-muted'"
                                  x-text="other.verified ? 'verified' : 'unverified'"></span>
                          </button>
                        </li>
                      </template>
                    </ul>
                  </div>
                </template>
              </div>
            </template>

            {{-- The history a boolean cannot hold: re-uploading a GST document
                 resets `verified`, so an employer can pass through this queue
                 more than once and only the log says what happened each time. --}}
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="border-b border-hairline-divider px-lg py-md"><h2 class="text-h4 text-ink">History</h2></div>
              <template x-if="orgDetail.data.audit.length === 0">
                <p class="p-lg text-bodysm text-ink-muted">No admin has acted on this employer yet.</p>
              </template>
              <ul class="max-h-[320px] overflow-y-auto thin-scrollbar">
                <template x-for="log in orgDetail.data.audit" :key="log.at">
                  <li class="border-b border-hairline-divider px-lg py-md last:border-0">
                    <p class="text-bodysm text-ink" x-text="log.summary"></p>
                    <p class="mt-[2px] text-caption text-ink-muted"
                       x-text="log.admin_email + ' · ' + timeAgo(log.at)"></p>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
