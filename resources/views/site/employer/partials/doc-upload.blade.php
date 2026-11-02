{{-- "Upload document" for a paid posting — opens the file picker in place and
     uploads the business document (BusinessDocument.pickAndUpload) instead of
     sending the employer off to the company form. PDF, JPG or PNG, ≤ 10 MB.
     @include('site.employer.partials.doc-upload', ['change' => 'jobUploadDoc(job, $event)', 'busy' => 'jobBusy === job.id', 'disabled' => '!!jobBusy', 'text' => "'Upload document'", 'class' => 'text-caption font-bold'])
     'icon' => true adds the upload glyph. --}}
<label class="{{ $class ?? '' }} cursor-pointer" @click.stop :class="({{ $disabled ?? $busy }}) && 'pointer-events-none opacity-70'">
  <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="{{ $change }}" :disabled="{{ $disabled ?? $busy }}">
  @if (!empty($icon))<span x-html="({{ $busy }}) ? ICONS.loader : ICONS.upload" :class="({{ $busy }}) && 'animate-spin'" class="shrink-0 [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>@endif
  <span x-text="({{ $busy }}) ? 'Uploading…' : {{ $text ?? "'Upload document'" }}"></span>
</label>
