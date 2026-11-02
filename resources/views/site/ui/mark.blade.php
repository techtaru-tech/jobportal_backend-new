{{-- What follows a field label (the app's FieldLabel): a red " *" when the
     field is required, a muted " (Optional)" when it is not — every field
     says which it is, so nobody finds out by pressing Save. Which one is
     decided by the same validation that refuses the submit.
     'optional' => false drops "(Optional)" — filters, search boxes, read-only
     values and headings over a group whose own fields carry markers.
     @include('site.ui.mark', ['required' => true]) --}}
@if (!empty($required))<span class="text-primary" aria-hidden="true"> *</span><span class="sr-only"> (required)</span>@elseif (($optional ?? true) !== false)<span class="text-[12px] font-normal text-ink-muted"> (Optional)</span>@endif
