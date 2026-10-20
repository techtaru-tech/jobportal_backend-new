{{-- "Have you managed a team?" and, on Yes, where and how big (the app's
     TeamFields). The server keeps only the yes/no; size and organisation live
     as one line of the About text — see TeamSummary in profile.js. --}}
<div>
  <p class="mb-xs text-caption text-ink-secondary">Team management</p>
  @include('site.profile.yn', ['label' => 'Have you managed a team?', 'icon' => 'users', 'value' => 'pf.managed_team', 'click' => 'profPickTeam(true)', 'clickNo' => 'profPickTeam(false)'])
  <template x-if="pf.managed_team === true">
    <div class="mt-lg space-y-lg">
      <label class="block">
        <span class="wa-label">Where did you manage it?</span>
        <input type="text" x-model="pf.team_org" @change="profTeamChanged()" list="prof-team-orgs" placeholder="Select the organisation" class="wa-input" autocomplete="off">
        <datalist id="prof-team-orgs"><template x-for="o in profTeamOrgs" :key="o"><option :value="o"></option></template></datalist>
      </label>
      <div>
        <span class="wa-label">Team size</span>
        <div class="flex items-center gap-md">
          <button type="button" @click="profTeamStep(-1)" :disabled="pf.team_size <= 1" aria-label="Fewer"
                  class="inline-flex h-11 w-11 items-center justify-center rounded-field bg-primary-light text-h4 text-primary disabled:bg-surface-muted disabled:text-ink-muted">−</button>
          <input type="text" inputmode="numeric" maxlength="3" x-model="pf.team_size" @change="profTeamChanged()" class="wa-input h-11 w-[72px] text-center">
          <button type="button" @click="profTeamStep(1)" :disabled="pf.team_size >= 100" aria-label="More"
                  class="inline-flex h-11 w-11 items-center justify-center rounded-field bg-primary-light text-h4 text-primary disabled:bg-surface-muted disabled:text-ink-muted">+</button>
          <span class="text-body text-ink" x-text="Number(pf.team_size) === 1 ? 'person' : 'people'"></span>
        </div>
        <p class="mt-sm text-caption text-ink-secondary">Adds to your summary: "<span x-text="profTeamPreview"></span>"</p>
      </div>
    </div>
  </template>
</div>
