{{-- The app's Post a Job step bar: four numbered circles — Job Details,
     Candidate Requirements, Company Details, Choose Plan. 'current' is a JS
     expression for the step on screen (0–3); a past step can be clicked when
     the page defines wizGo(i).
     @include('site.employer.partials.post-steps', ['current' => 'wiz'])
     @include('site.employer.partials.post-steps', ['current' => '3', 'static' => true]) --}}
@php($static = !empty($static))
<ol class="mt-lg grid grid-cols-4" aria-label="Post a job steps">
  @foreach (['Job Details', 'Candidate Requirements', 'Company Details', 'Choose Plan'] as $i => $label)
    <li class="relative flex flex-col items-center text-center">
      @if ($i > 0)
        <span class="absolute right-1/2 top-[15px] h-[2px] w-full -translate-y-1/2"
              :class="({{ $current }}) >= {{ $i }} ? 'bg-primary' : 'bg-hairline'"></span>
      @endif
      <button type="button" @if (!$static) @click="wizGo({{ $i }})" :disabled="({{ $current }}) <= {{ $i }}" @else disabled @endif
              class="relative z-[1] flex h-[30px] w-[30px] items-center justify-center rounded-full border-2 text-caption font-bold transition-colors enabled:cursor-pointer"
              :class="({{ $current }}) > {{ $i }} ? 'border-primary bg-primary text-white' : (({{ $current }}) === {{ $i }} ? 'border-primary bg-surface text-primary' : 'border-hairline bg-surface text-ink-muted')"
              aria-label="{{ $label }}">
        <span x-show="({{ $current }}) > {{ $i }}" x-html="ICONS.check" class="[&>svg]:h-[14px] [&>svg]:w-[14px]"></span>
        <span x-show="({{ $current }}) <= {{ $i }}">{{ $i + 1 }}</span>
      </button>
      <span class="mt-xs px-[2px] text-[11px] leading-tight"
            :class="({{ $current }}) === {{ $i }} ? 'font-semibold text-ink' : 'text-ink-muted'">{!! str_replace(' ', '<br>', $label) !!}</span>
    </li>
  @endforeach
</ol>
