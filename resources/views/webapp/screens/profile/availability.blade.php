{{-- When the candidate can start (the app's AvailabilityFields). Every answer
     is a tap; the follow-ups appear only where they apply. --}}
<div class="space-y-lg">
  @include('webapp.screens.profile.yn', ['label' => 'Currently employed?', 'key' => 'currently_employed', 'icon' => 'briefcase'])
  <template x-if="pf.currently_employed === true">
    <div class="space-y-lg">
      @include('webapp.ui.chips', ['label' => 'Notice period', 'model' => 'pf.notice_period', 'items' => "opt('notice_periods')"])
      <label class="block">
        <span class="wa-label">Last working date (if resigned)</span>
        <input type="date" x-model="pf.last_working_date" :min="profToday" class="wa-input">
      </label>
    </div>
  </template>
  @include('webapp.screens.profile.yn', ['label' => 'Immediate joiner?', 'key' => 'immediate_joiner', 'icon' => 'zap'])
  <template x-if="pf.immediate_joiner === false">
    <label class="block">
      <span class="wa-label">Earliest joining date</span>
      <input type="date" x-model="pf.earliest_joining_date" :min="profToday" class="wa-input">
    </label>
  </template>
  @include('webapp.screens.profile.yn', ['label' => 'Willing to relocate?', 'key' => 'willing_to_relocate', 'icon' => 'navigation'])
  @include('webapp.screens.profile.yn', ['label' => 'Own vehicle?', 'key' => 'has_vehicle', 'icon' => 'mapPin'])
  @include('webapp.screens.profile.yn', ['label' => 'Driving licence?', 'key' => 'has_driving_licence', 'icon' => 'badgeCheck'])
</div>
