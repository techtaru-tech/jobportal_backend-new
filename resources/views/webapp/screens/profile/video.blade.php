{{-- The 1-minute intro video (the app's IntroVideoField): record or pick a
     clip, its length checked here (the server re-measures), watched before
     it is sent. 'heading' => false where the step title already says it. --}}
<div>
  @if ($heading ?? true)
    <span class="wa-label">1-minute intro video</span>
  @endif
  <p class="text-caption text-ink-muted">Recruiters are more likely to shortlist candidates with a short intro video — it helps them see how you communicate before they even call. Keep it to 60 seconds: who you are, what you've done, and the kind of role you want.</p>

  <div class="mt-md">
    {{-- Sending --}}
    <template x-if="profVideoBusy && profVideoDraft">
      <div class="rounded-field bg-surface-muted p-lg">
        <div class="flex items-center gap-sm text-bodysm font-semibold text-ink">
          <span x-html="ICONS.loader" class="animate-spin text-primary [&>svg]:h-5 [&>svg]:w-5"></span> Uploading
        </div>
        <div class="mt-md h-[6px] overflow-hidden rounded-full bg-hairline"><div class="h-full w-1/3 animate-pulse rounded-full bg-primary"></div></div>
        <p class="mt-sm text-caption text-ink-muted">Keep this screen open until it finishes.</p>
      </div>
    </template>

    {{-- Picked, being reviewed before it goes out --}}
    <template x-if="profVideoDraft && !profVideoBusy">
      <div>
        <video :src="profVideoDraft.url" controls playsinline class="aspect-video w-full rounded-field bg-ink"></video>
        <div class="mt-md grid grid-cols-2 gap-sm">
          <button type="button" @click="profUploadVideo()" class="wa-btn-sm">Use this video</button>
          <label class="wa-btn-ghost-sm cursor-pointer">
            Choose another
            <input type="file" accept="video/mp4,video/quicktime,.mp4,.mov" class="sr-only" @change="profPickVideo($event.target)">
          </label>
        </div>
        <button type="button" @click="profDiscardVideo()" class="wa-btn-quiet mt-xs w-full">Cancel</button>
      </div>
    </template>

    {{-- On the profile --}}
    <template x-if="!profVideoDraft && me?.intro_video_url">
      <div>
        <video :src="me.intro_video_url" :poster="me.intro_video_thumbnail_url || ''" controls playsinline preload="metadata"
               x-on:error="profVideoBroken = true" class="aspect-video w-full rounded-field bg-ink"></video>
        <p x-show="profVideoBroken" class="mt-xs text-caption text-warning">Your video is saved, but it could not be previewed here.</p>
        <div class="mt-md flex items-center gap-sm">
          <label class="wa-btn-ghost-sm flex-1 cursor-pointer" :class="profVideoBusy && 'pointer-events-none opacity-60'">
            <span x-html="ICONS.video" class="[&>svg]:h-4 [&>svg]:w-4"></span> Re-record
            <input type="file" accept="video/mp4,video/quicktime,.mp4,.mov" capture="user" class="sr-only" @change="profPickVideo($event.target)">
          </label>
          <button type="button" @click="profRemoveVideo()" :disabled="profVideoBusy" aria-label="Remove intro video"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-field text-danger hover:bg-danger-bg disabled:opacity-60">
            <span x-html="ICONS.trash" class="[&>svg]:h-5 [&>svg]:w-5"></span>
          </button>
        </div>
      </div>
    </template>

    {{-- None yet --}}
    <template x-if="!profVideoDraft && !me?.intro_video_url">
      <div>
        <div class="flex flex-col items-center rounded-field bg-surface-muted p-lg">
          <span x-html="ICONS.video" class="text-ink-muted [&>svg]:h-7 [&>svg]:w-7"></span>
          <p class="mt-sm text-bodysm font-semibold text-ink">No intro video yet</p>
        </div>
        <div class="mt-md rounded-field border-hair border-hairline bg-surface p-md">
          <p class="text-kicker uppercase text-ink-secondary">Read this while recording</p>
          <p class="mt-xs text-bodysm text-ink" x-text="profIntroScript"></p>
        </div>
        <div class="mt-md grid gap-sm sm:grid-cols-2">
          <label class="wa-btn-sm cursor-pointer">
            <span x-html="ICONS.video" class="[&>svg]:h-4 [&>svg]:w-4"></span> Record intro video
            <input type="file" accept="video/mp4,video/quicktime,.mp4,.mov" capture="user" class="sr-only" @change="profPickVideo($event.target)">
          </label>
          <label class="wa-btn-ghost-sm cursor-pointer">
            Choose from gallery
            <input type="file" accept="video/mp4,video/quicktime,.mp4,.mov" class="sr-only" @change="profPickVideo($event.target)">
          </label>
        </div>
      </div>
    </template>
  </div>
</div>
