{{-- A profile card's title row: icon, title, completeness, and the card's
     action (Edit, or "+ Add" for a list). Hidden while the card is editing.
     @include('site.profile.card-head', ['id' => 'languages', 'title' => 'Languages', 'icon' => 'languages', 'edit' => 'languages'])
     @include('site.profile.card-head', [..., 'add' => 'Add education', 'addClick' => "startEdit('edu')"]) --}}
<div class="flex items-start gap-md">
  <span class="mt-[2px] inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-field bg-primary-light text-primary">
    @include('admin.partials.icon', ['name' => $icon, 'class' => 'h-[18px] w-[18px]'])
  </span>
  <div class="min-w-0 flex-1">
    <h2 class="text-h4 text-ink">{{ $title }}</h2>
    <p class="mt-[1px] flex items-center gap-xs text-caption"
       :class="section('{{ $id }}').complete ? 'text-success' : 'text-ink-muted'">
      <span x-show="section('{{ $id }}').complete" x-html="ICONS.checkCircle" class="[&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
      <span x-text="section('{{ $id }}').status"></span>
    </p>
  </div>
  @isset($edit)
    <button type="button" x-show="editing !== '{{ $edit }}'" @click="startEdit('{{ $edit }}')" class="wa-btn-quiet -mr-sm shrink-0" aria-label="Edit {{ $title }}">
      @include('admin.partials.icon', ['name' => 'pencil', 'class' => 'h-4 w-4']) <span class="hidden sm:inline">Edit</span>
    </button>
  @endisset
  @isset($add)
    <button type="button" x-show="!editing.startsWith('{{ $addKey }}')" @click="{{ $addClick }}" class="wa-btn-quiet -mr-sm shrink-0 text-primary hover:text-primary-dark">
      @include('admin.partials.icon', ['name' => 'plus', 'class' => 'h-4 w-4']) <span class="hidden sm:inline">{{ $add }}</span><span class="sm:hidden">Add</span>
    </button>
  @endisset
</div>
