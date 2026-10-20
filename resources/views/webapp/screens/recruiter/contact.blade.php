{{-- Contact details (recruiter_profile/views/recruiter_contact_view.dart). --}}
<template x-if="is('rContact')">
  <div class="wa-narrow">
    @include('webapp.ui.head', ['title' => 'Contact details', 'back' => '/r/profile', 'subtitle' => 'Shared across every company you post jobs for.'])
    <div class="wa-card space-y-lg">
      @include('webapp.ui.input', ['label' => 'Contact person', 'model' => 'rContactForm.contact_person_name', 'placeholder' => 'Who candidates should ask for', 'attrs' => 'autocomplete=name'])
      @include('webapp.ui.input', ['label' => 'Contact email', 'model' => 'rContactForm.contact_email', 'type' => 'email', 'placeholder' => 'hr@yourcompany.com', 'attrs' => 'autocomplete=email'])
      @include('webapp.ui.input', ['label' => 'Contact phone', 'model' => 'rContactForm.contact_phone', 'type' => 'tel', 'placeholder' => '98765 43210', 'attrs' => 'autocomplete=tel'])
      <button type="button" class="wa-btn w-full" :disabled="rContactSaving" @click="rSaveContact()">Save</button>
    </div>
  </div>
</template>
