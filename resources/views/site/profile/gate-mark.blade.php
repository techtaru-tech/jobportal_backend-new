{{-- A field's marker that a job can change: " *" while Create profile is
     gating an application that needs it (profGateReq — the app's
     isGateRequired), " (Optional)" otherwise.
     @include('site.profile.gate-mark', ['field' => 'dob']) --}}
<span x-show="profGateReq('{{ $field }}')" x-cloak><span class="text-primary" aria-hidden="true"> *</span><span class="sr-only"> (required)</span></span><span x-show="!profGateReq('{{ $field }}')" class="text-[12px] font-normal text-ink-muted"> (Optional)</span>
