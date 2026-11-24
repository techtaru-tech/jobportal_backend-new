{{-- Toast, confirm dialog and payment sheet — driven by `$store.ui` (public/js/site/core.js). --}}
<div x-data>
  {{-- Not in your area yet (public/js/site/coverage.js). A card, not a
       dialog: they can carry on browsing with it there. --}}
  <div x-show="$store.coverage && $store.coverage.show" x-cloak
       x-transition:enter="transition duration-component ease-out" x-transition:enter-start="opacity-0 translate-y-[8px]"
       x-transition:leave="transition duration-micro" x-transition:leave-end="opacity-0"
       class="fixed inset-x-page bottom-xl z-[95] mx-auto max-w-[460px] lg:left-xl lg:right-auto lg:mx-0" role="status" aria-live="polite">
    <div class="rounded-card border-hair border-hairline bg-surface p-lg shadow-raised">
      <div class="flex items-start gap-md">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary">
          <span x-html="ICONS.mapPin" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-bodysm font-semibold text-ink" x-text="'Inthes is not in ' + ($store.coverage?.state || 'your area') + ' yet'"></p>
          <p class="mt-[2px] text-caption text-ink-secondary">
            We are hiring in <span class="font-semibold text-ink" x-text="$store.coverage?.openList"></span> for now.
            You can still browse these jobs and apply if you are open to relocating.
          </p>
          <div class="mt-md flex flex-wrap gap-sm">
            <a href="{{ url('/jobs') }}" @click="$store.coverage.dismiss()" class="wa-btn-sm">Browse jobs</a>
            <button type="button" @click="$store.coverage.dismiss()" class="wa-btn-quiet">Got it</button>
          </div>
        </div>
        <button type="button" @click="$store.coverage.dismiss()" aria-label="Close" class="-mr-xs -mt-xs inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-ink-muted hover:bg-surface-muted">
          <span x-html="ICONS.x" class="[&>svg]:h-4 [&>svg]:w-4"></span>
        </button>
      </div>
    </div>
  </div>

  {{-- Toast --}}
  <div x-show="$store.ui.toast" x-cloak
       x-transition:enter="transition duration-component ease-out" x-transition:enter-start="opacity-0 translate-y-[6px]"
       x-transition:leave="transition duration-micro" x-transition:leave-end="opacity-0"
       class="fixed inset-x-page bottom-xl z-[99] mx-auto max-w-[420px] lg:left-auto lg:right-xl lg:mx-0" role="status" aria-live="polite">
    <div :class="$store.ui.toastError ? 'border-danger/30 bg-danger-bg text-danger' : 'border-success/30 bg-success-bg text-success'"
         class="flex items-start gap-md rounded-card border-hair px-lg py-md shadow-raised">
      <span x-html="$store.ui.toastError ? ICONS.alert : ICONS.check" class="mt-[1px] shrink-0 [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
      <p class="text-bodysm font-medium" x-text="$store.ui.toast"></p>
    </div>
  </div>

  {{-- Confirm --}}
  <div x-show="$store.ui.confirm.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[97] flex items-center justify-center bg-ink/40 px-page"
       @keydown.escape.window="$store.ui.confirm.open && $store.ui.closeConfirm(false)">
    <div @click.outside="$store.ui.closeConfirm(false)" class="w-full max-w-[420px] rounded-dialog bg-surface p-xl shadow-raised animate-pop-in" role="alertdialog">
      <h3 class="text-h3 text-ink" x-text="$store.ui.confirm.title"></h3>
      <p x-show="$store.ui.confirm.message" class="mt-sm text-bodysm text-ink-secondary" x-text="$store.ui.confirm.message"></p>
      <div class="mt-xl flex justify-end gap-sm">
        <button @click="$store.ui.closeConfirm(false)" class="wa-btn-quiet" x-text="$store.ui.confirm.cancel"></button>
        <button @click="$store.ui.closeConfirm(true)"
                :class="$store.ui.confirm.danger ? '!bg-danger hover:!bg-danger/90 !shadow-none' : ''"
                class="wa-btn-sm" x-text="$store.ui.confirm.ok"></button>
      </div>
    </div>
  </div>

  {{-- Map — "Choose on map" (Inthes.pickOnMap), the app's location picker.
       Tap the map or drag the pin; search moves it to a named place. --}}
  <div x-show="$store.ui.map.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[98] flex items-stretch justify-center bg-ink/60 sm:items-center sm:px-page"
       @keydown.escape.window="$store.ui.map.open && Inthes.closeMap(false)">
    <div role="dialog" aria-modal="true" aria-labelledby="map-title"
         class="flex w-full max-w-[720px] flex-col bg-surface shadow-raised sm:max-h-[90vh] sm:rounded-dialog animate-pop-in">
      <div class="flex items-center justify-between px-lg pb-sm pt-lg">
        <div>
          <h3 id="map-title" class="text-h4 text-ink">Choose on map</h3>
          <p class="text-caption text-ink-muted">Tap the map or drag the pin to the exact spot.</p>
        </div>
        <button type="button" @click="Inthes.closeMap(false)" aria-label="Close" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary hover:bg-surface-muted">
          <span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
      </div>
      <form @submit.prevent="Inthes.searchMap($store.ui.map.q)" class="flex gap-sm px-lg pb-md">
        <div class="relative flex-1">
          <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted"><span x-html="ICONS.search" class="[&>svg]:h-[18px] [&>svg]:w-[18px]"></span></span>
          <input type="search" x-model="$store.ui.map.q" placeholder="Search an area, e.g. Suraram, Hyderabad" class="wa-input h-[46px] pl-[44px]">
        </div>
        <button type="submit" :disabled="$store.ui.map.searching" class="wa-btn-ghost-sm h-[46px]">
          <span x-show="$store.ui.map.searching" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span> Search
        </button>
      </form>
      <div class="relative min-h-[60vh] flex-1 bg-surface-muted sm:h-[440px] sm:min-h-0 sm:flex-none">
        <div id="inthes-map" class="absolute inset-0 z-0"></div>
        <div x-show="$store.ui.map.loading" class="absolute inset-0 z-[500] flex items-center justify-center bg-surface/70">
          <span x-html="ICONS.loader" class="animate-spin text-primary [&>svg]:h-6 [&>svg]:w-6"></span>
        </div>
        <p x-show="$store.ui.map.error" x-text="$store.ui.map.error" class="absolute inset-x-lg top-lg z-[500] rounded-field bg-danger-bg px-md py-sm text-bodysm text-danger"></p>
      </div>
      <div class="flex justify-end gap-sm px-lg py-md">
        <button type="button" @click="Inthes.closeMap(false)" class="wa-btn-quiet">Cancel</button>
        <button type="button" @click="Inthes.closeMap(true)" :disabled="!$store.ui.map.picked" class="wa-btn-sm disabled:opacity-60" data-map-use>
          <span x-html="ICONS.mapPin" class="[&>svg]:h-4 [&>svg]:w-4"></span> Use this location
        </button>
      </div>
    </div>
  </div>

  {{-- Recorder — "Record intro video" (Inthes.recordVideo): the camera and
       microphone in the page, a 60-second count, then watch it back. --}}
  <div x-show="$store.ui.rec.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[98] flex items-end justify-center bg-ink/60 sm:items-center sm:px-page"
       @keydown.escape.window="$store.ui.rec.open && Inthes.closeRecorder(false)">
    <div role="dialog" aria-modal="true" aria-labelledby="rec-title"
         class="w-full max-w-[560px] rounded-t-sheet bg-surface p-lg shadow-raised sm:rounded-dialog animate-pop-in">
      <div class="mb-md flex items-center justify-between">
        <h3 id="rec-title" class="text-h4 text-ink">Record intro video</h3>
        <button type="button" @click="Inthes.closeRecorder(false)" aria-label="Close" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary hover:bg-surface-muted">
          <span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
      </div>
      <div class="relative aspect-video overflow-hidden rounded-card bg-ink">
        <video id="inthes-rec-video" x-show="!$store.ui.rec.clip && !$store.ui.rec.error" autoplay playsinline muted
               class="h-full w-full -scale-x-100 object-cover"></video>
        <template x-if="$store.ui.rec.clip">
          <video :src="$store.ui.rec.clip" controls playsinline class="h-full w-full bg-ink object-contain" data-rec-playback></video>
        </template>
        <div x-show="$store.ui.rec.recording" class="absolute left-md top-md flex items-center gap-xs rounded-full bg-ink/70 px-sm py-[3px] text-caption font-semibold text-white">
          <span class="h-2 w-2 animate-pulse rounded-full bg-primary"></span>
          <span x-text="'0:' + String($store.ui.rec.elapsed).padStart(2, '0') + ' / 1:00'"></span>
        </div>
        <div x-show="$store.ui.rec.starting" class="absolute inset-0 flex flex-col items-center justify-center gap-sm text-white">
          <span x-html="ICONS.loader" class="animate-spin [&>svg]:h-6 [&>svg]:w-6"></span>
          <p class="text-caption">Allow camera and microphone access when your browser asks</p>
        </div>
        <div x-show="$store.ui.rec.error" class="absolute inset-0 flex flex-col items-center justify-center gap-sm px-xl text-center text-white">
          <span x-html="ICONS.video" class="opacity-70 [&>svg]:h-8 [&>svg]:w-8"></span>
          <p class="text-bodysm" x-text="$store.ui.rec.error"></p>
        </div>
      </div>
      <p x-show="!$store.ui.rec.clip && !$store.ui.rec.error" class="mt-sm text-center text-caption text-ink-muted">Look at the camera, good light, 60 seconds at most — it stops on its own.</p>
      <div class="mt-lg flex justify-end gap-sm">
        <template x-if="!$store.ui.rec.clip">
          <div class="flex gap-sm">
            <button type="button" @click="Inthes.closeRecorder(false)" class="wa-btn-quiet">Cancel</button>
            <button type="button" x-show="$store.ui.rec.error" @click="Inthes.recorderFallback()" class="wa-btn-sm">Choose a video</button>
            <button type="button" x-show="!$store.ui.rec.error && !$store.ui.rec.recording" @click="Inthes.startRecording()" :disabled="$store.ui.rec.starting" class="wa-btn-sm disabled:opacity-60" data-rec-start>
              <span class="h-3 w-3 rounded-full bg-white"></span> Start recording
            </button>
            <button type="button" x-show="$store.ui.rec.recording" @click="Inthes.stopRecording()" class="wa-btn-sm" data-rec-stop>
              <span class="h-3 w-3 rounded-[2px] bg-white"></span> Stop
            </button>
          </div>
        </template>
        <template x-if="$store.ui.rec.clip">
          <div class="flex gap-sm">
            <button type="button" @click="Inthes.retakeRecording()" class="wa-btn-quiet">Retake</button>
            <button type="button" @click="Inthes.closeRecorder(true)" class="wa-btn-sm" data-rec-use>Use this video</button>
          </div>
        </template>
      </div>
    </div>
  </div>

  {{-- Camera — "Take photo" on a computer or a phone (Inthes.takePhoto).
       The live preview is mirrored like a mirror; the photo saved is not. --}}
  <div x-show="$store.ui.camera.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[98] flex items-end justify-center bg-ink/60 sm:items-center sm:px-page"
       @keydown.escape.window="$store.ui.camera.open && Inthes.closeCamera(false)">
    <div role="dialog" aria-modal="true" aria-labelledby="camera-title"
         class="w-full max-w-[520px] rounded-t-sheet bg-surface p-lg shadow-raised sm:rounded-dialog animate-pop-in">
      <div class="mb-md flex items-center justify-between">
        <h3 id="camera-title" class="text-h4 text-ink">Take a photo</h3>
        <button type="button" @click="Inthes.closeCamera(false)" aria-label="Close" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary hover:bg-surface-muted">
          <span x-html="ICONS.x" class="[&>svg]:h-5 [&>svg]:w-5"></span>
        </button>
      </div>
      <div class="relative aspect-[4/3] overflow-hidden rounded-card bg-ink">
        <video id="inthes-camera-video" x-show="!$store.ui.camera.shot && !$store.ui.camera.error" autoplay playsinline muted
               class="h-full w-full -scale-x-100 object-cover"></video>
        <template x-if="$store.ui.camera.shot">
          <img :src="$store.ui.camera.shot" alt="Your photo" class="h-full w-full object-cover">
        </template>
        <div x-show="$store.ui.camera.starting" class="absolute inset-0 flex flex-col items-center justify-center gap-sm text-ink-onPrimary">
          <span x-html="ICONS.loader" class="animate-spin [&>svg]:h-6 [&>svg]:w-6"></span>
          <p class="text-caption">Allow camera access when your browser asks</p>
        </div>
        <div x-show="$store.ui.camera.error" class="absolute inset-0 flex flex-col items-center justify-center gap-sm px-xl text-center text-ink-onPrimary">
          <span x-html="ICONS.camera" class="opacity-70 [&>svg]:h-8 [&>svg]:w-8"></span>
          <p class="text-bodysm" x-text="$store.ui.camera.error"></p>
        </div>
      </div>
      <p x-show="!$store.ui.camera.shot && !$store.ui.camera.error" class="mt-sm text-center text-caption text-ink-muted">Face centred, plain background, good light</p>
      <div class="mt-lg flex justify-end gap-sm">
        <template x-if="!$store.ui.camera.shot">
          <div class="flex gap-sm">
            <button type="button" @click="Inthes.closeCamera(false)" class="wa-btn-quiet">Cancel</button>
            <button type="button" x-show="$store.ui.camera.error" @click="Inthes.cameraFallback()" class="wa-btn-sm">
              <span x-html="ICONS.image" class="[&>svg]:h-4 [&>svg]:w-4"></span> Choose a photo
            </button>
            <button type="button" x-show="!$store.ui.camera.error" @click="Inthes.captureCamera()" :disabled="$store.ui.camera.starting || !!$store.ui.camera.error" class="wa-btn-sm disabled:opacity-60">
              <span x-html="ICONS.camera" class="[&>svg]:h-4 [&>svg]:w-4"></span> Capture
            </button>
          </div>
        </template>
        <template x-if="$store.ui.camera.shot">
          <div class="flex gap-sm">
            <button type="button" @click="Inthes.retakeCamera()" class="wa-btn-quiet">Retake</button>
            <button type="button" @click="Inthes.closeCamera(true)" class="wa-btn-sm">Use photo</button>
          </div>
        </template>
      </div>
    </div>
  </div>

  {{-- Wrong kind of account (one account, one job). Opened by
       Inthes.openSwitch / requireRole / guardArea, and by any API call that
       belongs to the other kind of account (js/site/core.js). --}}
  <div x-show="$store.ui.roleSwitch.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[96] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page"
       @keydown.escape.window="$store.ui.roleSwitch.open && Inthes.cancelSwitch()">
    <div @click.outside="$store.ui.roleSwitch.open && Inthes.cancelSwitch()" role="alertdialog" aria-modal="true" aria-labelledby="role-switch-title" data-role-switch
         class="w-full max-w-[440px] rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog animate-pop-in">
      <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-primary-light text-primary">
        <span x-html="$store.ui.roleSwitch.role === 'recruiter' ? ICONS.briefcase : ICONS.user" class="[&>svg]:h-6 [&>svg]:w-6"></span>
      </span>
      <h3 id="role-switch-title" class="mt-lg text-h3 text-ink"
          x-text="$store.ui.roleSwitch.role === 'recruiter' ? 'Posting jobs needs an employer account' : 'Applying for jobs needs a job seeker account'"></h3>
      <p class="mt-sm text-bodysm text-ink-secondary"
         x-text="$store.ui.roleSwitch.role === 'recruiter'
           ? 'You\'ll sign in again with your mobile number as an employer — it\'s a separate account.'
           : 'You\'ll sign in again with your mobile number as a job seeker — it\'s a separate account.'"></p>
      <p class="mt-sm text-caption text-ink-muted">
        You're now signed in with your <span x-text="$store.ui.roleSwitch.role === 'recruiter' ? 'job seeker' : 'employer'"></span> account.
        It stays as it is — sign back in to it any time.
      </p>
      <div class="mt-xl flex flex-col-reverse gap-sm sm:flex-row sm:justify-end">
        <button type="button" @click="Inthes.cancelSwitch()" class="wa-btn-quiet">Cancel</button>
        <button type="button" @click="Inthes.continueSwitch()" data-role-switch-continue class="wa-btn-sm"
                x-text="$store.ui.roleSwitch.role === 'recruiter' ? 'Continue as employer' : 'Continue as job seeker'"></button>
      </div>
    </div>
  </div>

  {{-- Payment method --}}
  <div x-show="$store.ui.pay.open" x-cloak x-transition.opacity
       class="fixed inset-0 z-[97] flex items-end justify-center bg-ink/40 sm:items-center sm:px-page">
    <div @click.outside="$store.ui.closePay(false)" class="w-full max-w-[440px] rounded-t-sheet bg-surface p-xl shadow-raised sm:rounded-dialog animate-pop-in">
      <h3 class="text-h3 text-ink">Payment method</h3>
      <p class="mt-xs text-bodysm text-ink-secondary">Choose how you'd like to pay.</p>
      <div class="mt-lg space-y-sm">
        <template x-for="m in $store.ui.pay.methods" :key="m.key">
          <label :class="$store.ui.pay.method === m.key ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted'"
                 class="flex cursor-pointer items-center gap-md rounded-field border-chip px-lg py-md transition-colors">
            <input type="radio" class="accent-[#EB0401]" :value="m.key" x-model="$store.ui.pay.method">
            <span x-html="ICONS[m.key === 'card' ? 'creditCard' : (m.key === 'upi' ? 'smartphone' : 'building')]" class="text-ink-secondary [&>svg]:h-5 [&>svg]:w-5"></span>
            <span class="text-body font-medium text-ink" x-text="m.label"></span>
          </label>
        </template>
      </div>
      <button @click="$store.ui.closePay(true)" :disabled="!$store.ui.pay.method" class="wa-btn mt-xl w-full">
        Pay <span x-text="$store.ui.pay.amount"></span>
      </button>
      <button @click="$store.ui.closePay(false)" class="wa-btn-quiet mt-sm w-full">Cancel</button>
    </div>
  </div>
</div>
