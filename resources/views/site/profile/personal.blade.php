{{-- Every personal-information question, in order (the app's PersonalDetailsFields).
     Binds to `pf`. 'essentials' => true keeps to the short set /account/setup asks
     (the rest stay on the profile page). --}}
<div class="space-y-lg">
  @include('site.ui.input', ['label' => 'Full name', 'model' => 'pf.name', 'placeholder' => 'e.g. Priya Sharma', 'required' => true, 'attrs' => 'maxlength=120 autocomplete=name'])

  {{-- Verified by the OTP, so shown rather than asked again. --}}
  <template x-if="me?.phone">
    <div>
      <span class="wa-label">Mobile number</span>
      <div class="flex h-[50px] items-center gap-sm rounded-field bg-surface-muted px-md">
        <span class="flex-1 text-input text-ink" x-text="'+91 ' + me.phone"></span>
        <span x-html="ICONS.badgeCheck" class="text-success [&>svg]:h-4 [&>svg]:w-4"></span>
        <span class="text-caption font-medium text-success">Verified</span>
      </div>
    </div>
  </template>

  @include('site.ui.input', ['label' => 'Email', 'model' => 'pf.email', 'type' => 'email', 'placeholder' => 'you@example.com', 'attrs' => 'maxlength=190 autocomplete=email'])

  @include('site.ui.chips', ['label' => 'Gender', 'model' => 'pf.gender', 'items' => "opt('genders')"])

  <div class="grid gap-lg sm:grid-cols-2">
    <label class="block">
      <span class="wa-label">Date of birth</span>
      <input type="date" x-model="pf.dob" :min="profDobMin" :max="profDobMax" class="wa-input">
    </label>
    <div>
      <span class="wa-label">Age</span>
      <div class="flex h-[50px] items-center gap-sm rounded-field bg-surface-muted px-md text-input"
           :class="profAge == null ? 'text-ink-muted' : 'text-ink'">
        <span x-html="ICONS.clock" class="text-ink-muted [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
        <span x-text="profAge == null ? 'Filled in from your date of birth' : profAge + ' years'"></span>
      </div>
    </div>
  </div>

  {{-- Typed, or the current position. Typing drops a pin taken earlier. --}}
  <div>
    <span class="wa-label">Where you live</span>
    <textarea x-model="pf.address" @input="profAddressTyped()" rows="2" maxlength="255" placeholder="Area, city" class="wa-textarea"></textarea>
    <div class="mt-sm flex flex-wrap items-center gap-sm">
      <button type="button" @click="profUseLocation()" :disabled="profLocating" class="wa-btn-ghost-sm">
        <span x-html="profLocating ? ICONS.loader : ICONS.navigation" :class="profLocating && 'animate-spin'" class="[&>svg]:h-4 [&>svg]:w-4"></span>
        Use current location
      </button>
      <template x-if="profHasPin">
        <span class="wa-pill bg-primary-light text-primary-dark">Pinned</span>
      </template>
    </div>
    <p class="mt-xs text-caption" :class="profHasPin ? 'text-ink-muted' : 'text-warning'">
      Pin it and we can sort jobs by how far they are from you. A typed address still works — it just cannot do that.
    </p>
  </div>

  <div class="grid gap-lg sm:grid-cols-2">
    <label class="block">
      <span class="wa-label">Current state</span>
      <div class="relative">
        <select x-model="pf.home_state" @change="profPickState()" class="wa-input cursor-pointer appearance-none pr-10">
          <option value="">Select your state</option>
          <template x-for="s in opt('states')" :key="s"><option :value="s" x-text="s" :selected="s === pf.home_state"></option></template>
        </select>
        <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
      </div>
    </label>
    <label class="block">
      <span class="wa-label">Current city</span>
      <div class="relative">
        <select x-model="pf.home_city" :disabled="!pf.home_state" class="wa-input cursor-pointer appearance-none pr-10 disabled:cursor-not-allowed disabled:opacity-60">
          <option value="" x-text="pf.home_state ? 'Select your city' : 'Select your state first'"></option>
          <template x-for="c in citiesOf(pf.home_state)" :key="c"><option :value="c" x-text="c" :selected="c === pf.home_city"></option></template>
        </select>
        <span class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">@include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])</span>
      </div>
    </label>
  </div>

  @if (empty($essentials))
  @include('site.ui.chips', ['label' => 'Marital status', 'model' => 'pf.marital_status', 'items' => "opt('marital_statuses')"])

  <div class="grid gap-lg sm:grid-cols-2">
    @include('site.ui.input', ['label' => "Father's name", 'model' => 'pf.father_name', 'placeholder' => "Father's full name", 'attrs' => 'maxlength=120'])
    @include('site.ui.input', ['label' => "Mother's name", 'model' => 'pf.mother_name', 'placeholder' => "Mother's full name", 'attrs' => 'maxlength=120'])
    @include('site.ui.input', ['label' => 'Native place', 'model' => 'pf.native_place', 'placeholder' => 'Hometown', 'attrs' => 'maxlength=120'])
    @include('site.ui.input', ['label' => 'Nationality', 'model' => 'pf.nationality', 'placeholder' => 'e.g. Indian', 'attrs' => 'maxlength=80'])
    @include('site.ui.input', ['label' => 'Alt. mobile', 'model' => 'pf.alternate_phone', 'type' => 'tel', 'placeholder' => '10-digit number', 'attrs' => 'maxlength=10 inputmode=numeric x-on:input="pf.alternate_phone = $event.target.value.replace(/[^0-9]/g, &quot;&quot;).slice(0, 10)"'])
    @include('site.ui.input', ['label' => 'WhatsApp', 'model' => 'pf.whatsapp_number', 'type' => 'tel', 'placeholder' => '10-digit number', 'attrs' => 'maxlength=10 inputmode=numeric x-on:input="pf.whatsapp_number = $event.target.value.replace(/[^0-9]/g, &quot;&quot;).slice(0, 10)"'])
  </div>
  @endif
</div>
