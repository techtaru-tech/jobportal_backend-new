{{-- The employer helpers (Emp) and one page's component, deferred before Alpine.
     @include('site.employer.partials.script', ['name' => 'employer-jobs']) --}}
@push('page-scripts')
<script defer src="{{ asset('js/site/pages/employer-shared.js') }}?v={{ filemtime(public_path('js/site/pages/employer-shared.js')) }}"></script>
<script defer src="{{ asset('js/site/pages/'.$name.'.js') }}?v={{ filemtime(public_path('js/site/pages/'.$name.'.js')) }}"></script>
@endpush
