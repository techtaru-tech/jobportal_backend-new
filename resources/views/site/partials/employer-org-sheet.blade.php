{{--
  Add an employer.

  Only `name` is required, and that is deliberate. A recruiter is here to post a
  job, and a long company form standing between them and that is where people
  leave. Everything else can be filled in later from the app — including the GST
  document, which is the one thing that actually matters and the one thing the
  web cannot do (a private-disk upload with a signed URL, handled in-app).

  Which is why the copy names verification as the next step rather than implying
  the employer is finished: an unverified employer's postings do not reach
  candidates even once an admin approves them.
--}}
<div x-show="orgOpen" x-cloak
     @keydown.escape.window="orgOpen = false"
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
     role="dialog" aria-modal="true" aria-label="Add an employer">

  <div x-show="orgOpen" @click="orgOpen = false"
       x-transition:enter="transition-opacity duration-component" x-transition:enter-start="opacity-0"
       x-transition:leave="transition-opacity duration-micro" x-transition:leave-end="opacity-0"
       class="absolute inset-0 bg-black/45"></div>

  <div x-show="orgOpen"
       x-transition:enter="transition duration-component ease-out"
       x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95 sm:translate-y-0"
       x-transition:leave="transition duration-micro"
       x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95 sm:translate-y-0"
       class="relative max-h-[92vh] w-full max-w-[480px] overflow-y-auto thin-scrollbar rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog">

    <div class="flex items-start justify-between gap-md">
      <div class="min-w-0">
        <span class="block text-kicker text-ink-secondary">EMPLOYER</span>
        <h2 class="mt-xs text-h2 text-ink">Add an employer</h2>
        <p class="mt-sm text-bodysm text-ink-secondary">
          Only the name is needed to post. The rest can wait.
        </p>
      </div>
      <button @click="orgOpen = false" aria-label="Close"
              class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-muted text-ink transition-colors hover:bg-hairline">
        <span x-html="ICONS.x" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      </button>
    </div>

    <form @submit.prevent="saveOrganisation()" class="mt-xl space-y-lg">
      <label class="block">
        <span class="mb-[5px] block text-label text-ink-secondary">Employer name *</span>
        <input x-model="orgForm.name" maxlength="150" required autofocus placeholder="e.g. Fortis Hospital"
               class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                      border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                      focus:border-focus focus:border-primary focus:shadow-glow">
      </label>

      <div class="grid grid-cols-1 gap-lg sm:grid-cols-2">
        <label class="block">
          <span class="mb-[5px] block text-label text-ink-secondary">City</span>
          <input x-model="orgForm.city" maxlength="80" placeholder="Jaipur"
                 class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                        border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                        focus:border-focus focus:border-primary focus:shadow-glow">
        </label>

        <label class="block">
          <span class="mb-[5px] block text-label text-ink-secondary">GST number</span>
          <input x-model="orgForm.gst_number" maxlength="30" placeholder="15 characters"
                 class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input tabular-nums text-ink placeholder:text-ink-muted
                        border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                        focus:border-focus focus:border-primary focus:shadow-glow">
        </label>
      </div>

      <label class="block">
        <span class="mb-[5px] block text-label text-ink-secondary">Website</span>
        {{-- `type="url"` because the API validates it as one, and being told by
             the browser beats being told by a 422. --}}
        <input x-model="orgForm.website" type="url" maxlength="255" placeholder="https://…"
               class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                      border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                      focus:border-focus focus:border-primary focus:shadow-glow">
      </label>

      <label class="block">
        <span class="mb-[5px] block text-label text-ink-secondary">About</span>
        <textarea x-model="orgForm.about" rows="3" maxlength="2000"
                  placeholder="What kind of facility this is, how many beds, which specialities…"
                  class="w-full resize-y rounded-field bg-surface-muted px-md py-md text-input leading-normal text-ink placeholder:text-ink-muted
                         border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                         focus:border-focus focus:border-primary focus:shadow-glow"></textarea>
      </label>

      <div class="rounded-field bg-warning-bg px-md py-sm">
        <p class="text-caption text-ink">
          <span class="font-semibold text-warning">One more step after this:</span>
          upload the GST certificate in the app. Until an employer is verified, its postings do not
          reach candidates — even once approved.
        </p>
      </div>

      <button type="submit" :disabled="!orgForm.name.trim() || orgBusy"
              class="inline-flex h-[50px] w-full items-center justify-center gap-sm rounded-button bg-primary px-md text-btn font-semibold text-ink-onPrimary shadow-button
                     transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]
                     disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
        <template x-if="orgBusy">
          @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-[18px] w-[18px] animate-spin'])
        </template>
        <span x-show="!orgBusy">Save employer</span>
      </button>
    </form>
  </div>
</div>
