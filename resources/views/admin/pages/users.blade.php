{{--
  Accounts. **One list, not two.**

  There is no candidates page and no recruiters page, and that is deliberate:
  `users.role` records the side an account signed up on and nothing else, and
  the same person routinely posts jobs and applies for them. So one row per
  human, with a facet for each side.
--}}
<template x-if="view === 'users'">
  <div class="animate-enter-up" x-init="loadUsers()">
    @include('admin.partials.page-header', [
      'kicker' => 'Accounts',
      'title' => 'Accounts',
      'description' => 'One row per person — an account can be a candidate and a recruiter at the same time.',
    ])

    @include('admin.partials.list-shell', [
      'state' => 'users',
      'loader' => 'loadUsers',
      'cols' => 5,
      'placeholder' => 'Search phone, name or email…',
      'emptyTitle' => 'No accounts match',
      'emptyMessage' => 'Try a different search or filter.',
      'filters' => '
        <div class="relative">
          <select x-model="users.side" @change="loadUsers(1)" aria-label="Side"
                  class="h-[46px] w-[150px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">Any side</option>
            <option value="recruiter">Recruiter</option>
            <option value="candidate">Candidate</option>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>
        <div class="relative">
          <select x-model="users.sort" @change="loadUsers(1)" aria-label="Sort"
                  class="h-[46px] w-[160px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
            <option value="">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="last_login">Last login</option>
            <option value="applications">Most applications</option>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>',

      'head' => '
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">PERSON</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">SIGNED UP AS</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden lg:table-cell">CANDIDATE</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden lg:table-cell">RECRUITER</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">JOINED</th>',

      'body' => '
        <template x-for="row in users.data" :key="row.id">
          <tr @click="openUser(row.id)"
              class="group border-b border-hairline-divider last:border-0 cursor-pointer transition-colors duration-micro hover:bg-canvas-alt">
            <td class="relative px-lg py-md align-middle text-bodysm text-ink">
              <span aria-hidden="true" class="absolute inset-y-0 left-0 w-[2px] origin-top scale-y-0 bg-primary transition-transform duration-micro ease-out group-hover:scale-y-100"></span>
              <div class="flex items-center gap-md">
                <span aria-hidden="true" x-text="initials(row.name || row.phone)" style="font-size:11.6px"
                      class="inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
                <div class="min-w-0">
                  <span class="block truncate font-semibold text-ink" x-text="row.name || \'—\'"></span>
                  <span class="block truncate text-caption text-ink-muted" x-text="row.phone"></span>
                </div>
              </div>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink">
              <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-surface-muted text-ink-secondary" x-text="row.signed_up_as"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink hidden lg:table-cell">
              <span class="text-caption text-ink-secondary tabular-nums">
                <span x-text="row.candidate.profile_strength + \'% strength\'"></span> ·
                <span x-text="row.candidate.applications + \' applied\'"></span>
              </span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink hidden lg:table-cell">
              <template x-if="row.recruiter.is_active">
                <span class="text-caption text-ink-secondary tabular-nums">
                  <span x-text="row.recruiter.jobs + \' jobs\'"></span> ·
                  <span x-text="row.recruiter.organisations + \' employers\'"></span>
                </span>
              </template>
              <template x-if="!row.recruiter.is_active">
                <span class="text-ink-muted">—</span>
              </template>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-right">
              <span class="text-caption text-ink-muted" x-text="timeAgo(row.created_at)"></span>
            </td>
          </tr>
        </template>',
    ])
  </div>
</template>

{{-- ── detail ────────────────────────────────────────────────────────── --}}
<template x-if="view === 'userDetail'">
  <div class="animate-enter-up">
    <template x-if="userDetail.busy && !userDetail.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading the account…'])
    </template>

    <template x-if="userDetail.data">
      <div>
        @include('admin.partials.back', ['to' => 'users', 'label' => 'Back to accounts'])

        <div class="mb-lg flex flex-wrap items-end justify-between gap-md">
          <div class="flex min-w-0 items-center gap-lg">
            <span aria-hidden="true" x-text="initials(userDetail.data.account.name || userDetail.data.account.phone)"
                  style="font-size:17px"
                  class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-logo bg-primary-light font-semibold text-primary-dark"></span>
            <div class="min-w-0">
              <span class="block text-kicker text-ink-secondary">ACCOUNT</span>
              <h1 class="mt-[2px] text-h2 text-ink truncate"
                  x-text="userDetail.data.account.name || userDetail.data.account.phone"></h1>
              <p class="mt-xs text-bodysm text-ink-secondary"
                 x-text="userDetail.data.account.phone + (userDetail.data.account.email ? ' · ' + userDetail.data.account.email : '')"></p>
            </div>
          </div>
          {{-- The one account-level action worth having: reversible (they sign
               in again with an OTP) and the only remedy for a lost phone. --}}
          <button x-show="canWrite" @click="revokeTokens()"
                  class="inline-flex h-[42px] items-center justify-center gap-sm rounded-button px-md text-btn font-semibold
                         bg-surface text-danger border-btn border-danger transition-[background-color,transform] duration-micro ease-out
                         hover:bg-danger-bg active:scale-[0.97]">
            <span x-html="ICONS.logOut" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            <span>Revoke all sessions</span>
          </button>
        </div>

        <div class="grid grid-cols-1 gap-md lg:grid-cols-3">
          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
            <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Account</h2></div>
            <div class="grid gap-lg grid-cols-1 sm:grid-cols-2">
              @include('admin.partials.field', ['label' => 'Signed up as', 'value' => 'userDetail.data.account.signed_up_as'])
              @include('admin.partials.field', ['label' => 'Phone verified', 'value' => "userDetail.data.account.phone_verified_at ? fmtDate(userDetail.data.account.phone_verified_at) : null"])
              @include('admin.partials.field', ['label' => 'Last login', 'value' => 'timeAgo(userDetail.data.account.last_login_at)'])
              @include('admin.partials.field', ['label' => 'Joined', 'value' => 'fmtDate(userDetail.data.account.created_at)'])
              @include('admin.partials.field', ['label' => 'Devices', 'value' => 'userDetail.data.account.devices.length'])
            </div>
          </div>

          <template x-if="userDetail.data.candidate">
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
              <div class="flex items-end gap-md mb-md"><h2 class="flex-1 text-h3 text-ink">Candidate</h2></div>
              <div class="mb-lg">
                <div class="flex items-center justify-between text-caption text-ink-secondary mb-xs">
                  <span>Profile strength</span>
                  <span class="tabular-nums font-semibold text-ink" x-text="userDetail.data.candidate.profile_strength + '%'"></span>
                </div>
                <div class="h-[6px] overflow-hidden rounded-chip bg-surface-muted" role="progressbar">
                  <div class="h-full origin-left rounded-chip bg-primary animate-grow-x"
                       :style="`width:${userDetail.data.candidate.profile_strength}%`"></div>
                </div>
              </div>
              <div class="grid gap-lg grid-cols-1 sm:grid-cols-2">
                @include('admin.partials.field', ['label' => 'Qualification', 'value' => 'userDetail.data.candidate.qualification'])
                @include('admin.partials.field', ['label' => 'Experience', 'value' => 'userDetail.data.candidate.experience'])
                @include('admin.partials.field', ['label' => 'Home city', 'value' => 'userDetail.data.candidate.home_city'])
                @include('admin.partials.field', ['label' => 'Resume', 'value' => "userDetail.data.candidate.has_resume ? 'On file' : null"])
              </div>
              <div class="mt-lg">
                <span class="block text-kicker text-ink-secondary">SKILLS</span>
                <div class="mt-sm flex flex-wrap gap-xs">
                  <template x-if="(userDetail.data.candidate.skills || []).length === 0">
                    <span class="text-bodysm text-ink-muted">—</span>
                  </template>
                  <template x-for="skill in userDetail.data.candidate.skills" :key="skill">
                    <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap bg-surface-muted text-ink-secondary" x-text="skill"></span>
                  </template>
                </div>
              </div>
            </div>
          </template>

          <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
            <div class="border-b border-hairline-divider px-lg py-md">
              <h2 class="text-h4 text-ink">Applications
                <span class="text-ink-muted font-normal" x-text="'(' + userDetail.data.applications.length + ')'"></span>
              </h2>
            </div>
            <template x-if="userDetail.data.applications.length === 0">
              <p class="p-lg text-bodysm text-ink-muted">This account has never applied to anything.</p>
            </template>
            <ul class="max-h-[420px] overflow-y-auto thin-scrollbar">
              <template x-for="row in userDetail.data.applications" :key="row.reference">
                <li class="border-b border-hairline-divider last:border-0">
                  <button @click="openApplication(row.reference)"
                          class="flex w-full items-center justify-between gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                    <span class="min-w-0">
                      <span class="block truncate text-bodysm font-semibold text-ink" x-text="row.job?.title || '—'"></span>
                      <span class="block truncate text-caption text-ink-muted" x-text="timeAgo(row.applied_at)"></span>
                    </span>
                    <span x-html="statusDot(row.status, 'application')"></span>
                  </button>
                </li>
              </template>
            </ul>
          </div>
        </div>

        {{-- The recruiter half, shown only when this account has one — an
             empty employer panel on a candidate's page is noise. --}}
        <template x-if="userDetail.data.recruiter.organisations.length || userDetail.data.recruiter.jobs.length">
          <div class="mt-md grid grid-cols-1 gap-md lg:grid-cols-2">
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="border-b border-hairline-divider px-lg py-md"><h2 class="text-h4 text-ink">Employers</h2></div>
              <ul>
                <template x-for="org in userDetail.data.recruiter.organisations" :key="org.id">
                  <li class="border-b border-hairline-divider last:border-0">
                    <button @click="openOrganisation(org.id)"
                            class="flex w-full items-center justify-between gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                      <span class="min-w-0 truncate text-bodysm font-semibold text-ink" x-text="org.name"></span>
                      <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap"
                            :class="org.verified ? 'bg-primary-light text-primary-dark' : 'bg-surface-muted text-ink-secondary'"
                            x-text="org.verified ? 'Verified' : 'Unverified'"></span>
                    </button>
                  </li>
                </template>
              </ul>
            </div>
            <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card flex flex-col">
              <div class="border-b border-hairline-divider px-lg py-md"><h2 class="text-h4 text-ink">Postings</h2></div>
              <ul>
                <template x-for="job in userDetail.data.recruiter.jobs" :key="job.id">
                  <li class="border-b border-hairline-divider last:border-0">
                    <button @click="openJob(job.id)"
                            class="flex w-full items-center justify-between gap-md px-lg py-md text-left transition-colors duration-micro hover:bg-canvas-alt">
                      <span class="min-w-0">
                        <span class="block truncate text-bodysm font-semibold text-ink" x-text="job.title"></span>
                        <span class="block truncate text-caption text-ink-muted" x-text="job.city"></span>
                      </span>
                      <span x-html="statusDot(job.status, 'job')"></span>
                    </button>
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
