{{-- One work-experience entry's fields (the app's ExperienceFields) — the
     Experience sheet and Create profile's experience step. Binds to `pf.exp`.
     Month + year pickers compose the `Mar 2023` the API stores. --}}
<div class="space-y-lg">
  @include('site.ui.combo', ['label' => 'Company / Hospital / Laboratory', 'model' => 'pf.exp.organization', 'items' => "opt('institutes')", 'required' => true, 'placeholder' => 'e.g. Apollo Hospitals, Metro Diagnostics…'])
  @include('site.ui.combo', ['label' => 'Designation', 'model' => 'pf.exp.designation', 'items' => "opt('designations')", 'required' => true, 'placeholder' => 'Select or add your designation'])
  @include('site.ui.combo', ['label' => 'Department', 'model' => 'pf.exp.department', 'items' => "opt('departments')", 'placeholder' => 'Select or add a department'])
  {{-- A role can be worked across more than one city. --}}
  @include('site.ui.multi-add', ['label' => 'Location', 'model' => 'pf.exp.cities', 'items' => "opt('cities')", 'placeholder' => '+ Add a city'])

  <div>
    <span class="wa-label">Joining date @include('site.ui.mark', ['required' => true])</span>
    <div class="grid grid-cols-2 gap-sm">
      <select x-model="pf.exp.sm" class="wa-input cursor-pointer" aria-label="Joining month">
        <option value="">Month</option>
        <template x-for="m in PROF_MONTHS" :key="m"><option :value="m" x-text="m" :selected="m === pf.exp.sm"></option></template>
      </select>
      <select x-model="pf.exp.sy" class="wa-input cursor-pointer" aria-label="Joining year">
        <option value="">Year</option>
        <template x-for="y in profExpYears" :key="y"><option :value="y" x-text="y" :selected="y === pf.exp.sy"></option></template>
      </select>
    </div>
  </div>

  <div>
    <span class="wa-label">Last working date @include('site.ui.mark', ['required' => false])</span>
    {{-- "Present" while currently working — the entry saves that as its end. --}}
    <template x-if="pf.exp.currently_working">
      <div class="flex h-[50px] items-center rounded-field bg-surface-muted px-md text-input text-ink-muted">Present</div>
    </template>
    <template x-if="!pf.exp.currently_working">
      <div class="grid grid-cols-2 gap-sm">
        <select x-model="pf.exp.em" class="wa-input cursor-pointer" aria-label="Last working month">
          <option value="">Month</option>
          <template x-for="m in PROF_MONTHS" :key="m"><option :value="m" x-text="m" :selected="m === pf.exp.em"></option></template>
        </select>
        <select x-model="pf.exp.ey" class="wa-input cursor-pointer" aria-label="Last working year">
          <option value="">Year</option>
          <template x-for="y in profExpYears.filter((y) => !pf.exp.sy || Number(y) >= Number(pf.exp.sy))" :key="y"><option :value="y" x-text="y" :selected="y === pf.exp.ey"></option></template>
        </select>
      </div>
    </template>
  </div>

  <label class="flex cursor-pointer items-center gap-sm">
    <input type="checkbox" x-model="pf.exp.currently_working" class="h-5 w-5 rounded accent-[#EB0401]">
    <span class="text-body text-ink">Currently working here</span>
  </label>
  <p x-show="profExpDuration" class="-mt-sm text-caption text-ink-secondary" x-text="'Duration: ' + profExpDuration"></p>

  <div>
    @include('site.ui.textarea', ['label' => 'Responsibilities', 'model' => 'pf.exp.description', 'rows' => 5, 'placeholder' => 'Briefly describe what you did in this role'])
    {{-- AI Resume Builder: drafts these from the fields above, before saving. --}}
    <div class="mt-xs flex justify-end">
      <button type="button" @click="profWriteExp()" :disabled="!!aiWriting" class="inline-flex items-center gap-xs rounded-button px-sm py-xs text-bodysm font-semibold text-primary hover:bg-primary-light disabled:opacity-60">
        <span x-html="ICONS.sparkles" class="[&>svg]:h-4 [&>svg]:w-4" :class="aiWriting === 'exp' && 'animate-pulse'"></span>
        <span x-text="aiWriting === 'exp' ? 'Writing…' : 'Write with AI'"></span>
      </button>
    </div>
  </div>
</div>
