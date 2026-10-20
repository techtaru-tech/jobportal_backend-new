{{-- One work-experience entry's fields (the app's ExperienceFields) — the
     Experience sheet and Create profile's experience step. Binds to `pf.exp`.
     Month + year pickers compose the `Mar 2023` the API stores. --}}
<div class="space-y-lg">
  @include('site.ui.combo', ['label' => 'Company / Hospital / Laboratory', 'model' => 'pf.exp.organization', 'items' => "opt('institutes')", 'required' => true, 'placeholder' => 'e.g. Apollo Hospitals, Metro Diagnostics…'])
  @include('site.ui.combo', ['label' => 'Designation', 'model' => 'pf.exp.designation', 'items' => "opt('designations')", 'required' => true, 'placeholder' => 'Select or add your designation'])
  @include('site.ui.combo', ['label' => 'Department (optional)', 'model' => 'pf.exp.department', 'items' => "opt('departments')", 'placeholder' => 'Select or add a department'])
  {{-- A role can be worked across more than one city. --}}
  @include('site.ui.multi-add', ['label' => 'Location', 'model' => 'pf.exp.cities', 'items' => "opt('cities')", 'placeholder' => '+ Add a city'])

  <div>
    <span class="wa-label">Joining date<span class="text-primary"> *</span></span>
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
    <span class="wa-label">Last working date</span>
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

  @include('site.ui.textarea', ['label' => 'Responsibilities (optional)', 'model' => 'pf.exp.description', 'rows' => 3, 'placeholder' => 'Briefly describe what you did in this role'])
</div>
