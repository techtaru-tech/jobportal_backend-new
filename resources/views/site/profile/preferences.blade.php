{{-- The Preferred jobs questions (the app's PreferenceFields). Job type and
     shift are closed sets; cities and roles take one of your own. --}}
<div class="space-y-lg">
  @include('site.ui.multi-add', ['label' => 'Preferred cities', 'model' => 'pf.location', 'items' => "opt('cities')", 'placeholder' => 'Where do you want to work?'])
  @include('site.ui.multi-add', ['label' => 'Preferred roles', 'model' => 'pf.roles', 'items' => "opt('categories')", 'placeholder' => 'Which roles interest you?'])
  @include('site.ui.chips', ['label' => 'Job type', 'model' => 'pf.job_types', 'iconKind' => 'jobType', 'items' => "opt('job_types')", 'multi' => true])
  @include('site.ui.chips', ['label' => 'Preferred shift', 'model' => 'pf.shifts', 'iconKind' => 'shift', 'items' => "opt('shifts')", 'multi' => true])
  @include('site.ui.chips', ['label' => 'Preferred workplace', 'model' => 'pf.org_types', 'iconKind' => 'workplace', 'items' => "opt('organisation_industries')", 'multi' => true])
  @include('site.ui.chips', ['label' => 'Expected salary', 'model' => 'pf.salary', 'items' => 'profSalaryOptions'])
</div>
