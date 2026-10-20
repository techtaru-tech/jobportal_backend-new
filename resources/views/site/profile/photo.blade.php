{{-- The profile photo control — the picture as it is and what you can do to
     it (the app's ProfilePhotoField). 'label' => false where the step heading
     already says "Your photo". --}}
<div>
  @if ($label ?? true)
    <span class="wa-label">Profile photo</span>
    <p class="mb-md text-caption text-ink-muted">Recruiters see this on every application you send.</p>
  @endif
  <div class="flex flex-col items-center">
    <div class="relative flex h-[108px] w-[108px] items-center justify-center overflow-hidden rounded-full bg-surface-muted text-h2 font-semibold text-ink-secondary ring-1 ring-hairline">
      <template x-if="me?.photo_url"><img :src="me.photo_url" alt="Your photo" class="h-full w-full object-cover"></template>
      <template x-if="!me?.photo_url && profInitials(pf.name || me?.name)">
        <span x-text="profInitials(pf.name || me?.name)"></span>
      </template>
      <template x-if="!me?.photo_url && !profInitials(pf.name || me?.name)">
        <span x-html="ICONS.user" class="text-ink-muted [&>svg]:h-10 [&>svg]:w-10"></span>
      </template>
      <div x-show="profPhotoBusy" class="absolute inset-0 flex items-center justify-center bg-ink/40 text-ink-onPrimary">
        <span x-html="ICONS.loader" class="animate-spin [&>svg]:h-6 [&>svg]:w-6"></span>
      </div>
    </div>
    <p class="mt-sm text-center text-caption text-ink-muted">Passport-style: face centred, plain background</p>
  </div>
  <div class="mx-auto mt-lg grid max-w-[420px] grid-cols-2 gap-sm">
    <label class="wa-btn-ghost-sm cursor-pointer" :class="profPhotoBusy && 'pointer-events-none opacity-60'">
      <span x-html="ICONS.image" class="[&>svg]:h-4 [&>svg]:w-4"></span>
      <span x-text="me?.photo ? 'Change photo' : 'Choose photo'"></span>
      <input type="file" accept="image/jpeg,image/png" class="sr-only" @change="profPickPhoto($event.target)">
    </label>
    {{-- A profile photo is usually a selfie; on a phone this opens the camera. --}}
    <label class="wa-btn-ghost-sm cursor-pointer" :class="profPhotoBusy && 'pointer-events-none opacity-60'">
      <span x-html="ICONS.camera" class="[&>svg]:h-4 [&>svg]:w-4"></span>
      Take photo
      <input type="file" accept="image/jpeg,image/png" capture="user" class="sr-only" @change="profPickPhoto($event.target)">
    </label>
  </div>
  <template x-if="me?.photo">
    <div class="mt-sm text-center">
      <button type="button" @click="profDeletePhoto()" :disabled="profPhotoBusy" class="inline-flex items-center gap-xs rounded-button px-md py-sm text-bodysm font-medium text-danger hover:bg-danger-bg disabled:opacity-60">
        <span x-html="ICONS.trash" class="[&>svg]:h-4 [&>svg]:w-4"></span> Remove photo
      </button>
    </div>
  </template>
</div>
