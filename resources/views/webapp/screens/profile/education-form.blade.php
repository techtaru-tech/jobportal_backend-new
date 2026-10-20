{{-- One education entry's fields (the app's EducationFields) — the Education
     sheet and Create profile's education step. Binds to `pf.edu`. --}}
<div class="space-y-lg">
  @include('webapp.screens.profile.pick', ['label' => 'Highest qualification', 'model' => 'pf.edu.qualification', 'items' => "opt('qualifications')", 'required' => true, 'placeholder' => 'Select a qualification'])
  @include('webapp.ui.combo', ['label' => 'Specialization', 'model' => 'pf.edu.specialization', 'items' => "opt('specializations')", 'placeholder' => 'Select or add your subject'])
  @include('webapp.ui.combo', ['label' => 'Institute', 'model' => 'pf.edu.institute', 'items' => "opt('institutes')", 'placeholder' => 'Select or add your institute'])

  <div class="grid grid-cols-2 gap-md">
    <label class="block">
      <span class="wa-label">Joining year</span>
      <div class="relative">
        <select x-model="pf.edu.start" @change="profEduStart(pf.edu.start)" class="wa-input cursor-pointer appearance-none pr-10">
          <option value="">Select a year</option>
          <template x-for="y in profYearsDesc" :key="y"><option :value="y" x-text="y" :selected="y === pf.edu.start"></option></template>
        </select>
        <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
      </div>
    </label>
    <label class="block">
      <span class="wa-label">Passing year<span class="text-primary"> *</span></span>
      <div class="relative">
        <select x-model="pf.edu.end" class="wa-input cursor-pointer appearance-none pr-10">
          <option value="">Select a year</option>
          <template x-for="y in profEndYears(pf.edu.start)" :key="y"><option :value="y" x-text="y" :selected="y === pf.edu.end"></option></template>
        </select>
        <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
      </div>
    </label>
  </div>

  <div class="grid gap-lg sm:grid-cols-2">
    @include('webapp.ui.input', ['label' => 'Percentage / CGPA', 'model' => 'pf.edu.percentage', 'placeholder' => 'e.g. 78 or 8.2', 'attrs' => 'inputmode=decimal maxlength=6 x-on:input="if (!/^\d{0,3}(\.\d{0,2})?$/.test($event.target.value)) { $event.target.value = pf.edu.percentage = $event.target.value.replace(/[^0-9.]/g, &quot;&quot;).replace(/(\..*)\./g, &quot;$1&quot;).slice(0, 6) }"'])
    @include('webapp.ui.chips', ['label' => 'Mode', 'model' => 'pf.edu.course_type', 'items' => "opt('education_types')"])
  </div>
</div>
