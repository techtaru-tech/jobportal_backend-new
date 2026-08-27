@extends('site.layout')

@section('title')
    @if ($filters['q'] !== ''){{ $filters['q'] }} jobs
    @elseif ($filters['role'] !== ''){{ $filters['role'] }} jobs
    @else Browse healthcare jobs
    @endif
    @if ($filters['city'] !== '') in {{ $filters['city'] }} @endif
    — Inthes
@endsection

@section('description', 'Filter nursing, lab, pharmacy and allied healthcare openings by city, shift and salary. Verified employers only.')

@section('content')
@php
    // Which filters are actually on, for the removable chips. Built here rather
    // than in the view body so the "no filters" case is a simple emptiness check
    // instead of four nested conditionals.
    $active = collect([
        'q' => $filters['q'] ? 'Search: '.$filters['q'] : null,
        'role' => $filters['role'] ?: null,
        'city' => $filters['city'] ?: null,
        'type' => $filters['type'] ?: null,
        'shift' => $filters['shift'] ?: null,
        'min_salary' => $filters['min_salary'] ? '₹'.number_format((int) $filters['min_salary']).'+' : null,
    ])->filter()->all();
@endphp

<div class="mx-auto max-w-[1200px] px-page py-xl lg:px-xl">

    <nav aria-label="Breadcrumb" class="mb-lg flex items-center gap-xs text-caption text-ink-muted">
        <a href="{{ route('site.home') }}" class="transition-colors hover:text-ink">Home</a>
        @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-3 w-3'])
        <span class="text-ink-secondary">Jobs</span>
    </nav>

    <div class="mb-lg">
        <span class="block text-kicker text-ink-secondary">
            {{ number_format($jobs->total()) }} {{ Str::plural('opening', $jobs->total()) }}
        </span>
        <h1 class="mt-[2px] text-h1 text-ink">
            @if ($filters['role'] !== ''){{ $filters['role'] }} jobs
            @elseif ($filters['q'] !== '')Results for “{{ $filters['q'] }}”
            @else All healthcare jobs
            @endif
        </h1>
    </div>

    {{--
      A plain GET form. Every control submits it, so the resulting list is
      always a URL somebody can bookmark, share or land on from a search
      engine — which is also why the filters are not component state.
    --}}
    <form action="{{ route('site.jobs') }}" method="GET"
          class="rounded-card border-hair border-hairline bg-surface p-md shadow-card">
        <div class="grid grid-cols-1 gap-sm sm:grid-cols-2 lg:grid-cols-5">
            <div class="relative lg:col-span-2">
                <span aria-hidden="true" class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">
                    @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[18px] w-[18px]'])
                </span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Role, employer or keyword…"
                       class="h-[46px] w-full rounded-field bg-surface-muted pl-[42px] pr-md text-input text-ink placeholder:text-ink-muted
                              border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                              focus:border-focus focus:border-primary focus:shadow-glow">
            </div>

            @foreach ([
                ['name' => 'role', 'label' => 'Any role', 'values' => $options['roles']],
                ['name' => 'city', 'label' => 'Any city', 'values' => $options['cities']],
                ['name' => 'type', 'label' => 'Any type', 'values' => $options['types']],
            ] as $select)
                <div class="relative">
                    <select name="{{ $select['name'] }}" @change="submitFilters($event)"
                            class="h-[46px] w-full cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink
                                   border-hair border-hairline outline-none transition-[border-color,box-shadow] duration-micro
                                   focus:border-focus focus:border-primary focus:shadow-glow">
                        <option value="">{{ $select['label'] }}</option>
                        @foreach ($select['values'] as $value)
                            <option value="{{ $value }}" @selected($filters[$select['name']] === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                    <span aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 text-ink-muted">
                        @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-5 w-5'])
                    </span>
                </div>
            @endforeach
        </div>

        <div class="mt-sm flex flex-col gap-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-sm">
                <div class="relative">
                    <select name="shift" @change="submitFilters($event)"
                            class="h-[42px] cursor-pointer appearance-none rounded-field bg-surface-muted pl-md pr-10 text-bodysm text-ink
                                   border-hair border-hairline outline-none focus:border-primary">
                        <option value="">Any shift</option>
                        @foreach ($options['shifts'] as $value)
                            <option value="{{ $value }}" @selected($filters['shift'] === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                    <span aria-hidden="true" class="pointer-events-none absolute right-sm top-1/2 -translate-y-1/2 text-ink-muted">
                        @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-4 w-4'])
                    </span>
                </div>

                <div class="relative">
                    <select name="min_salary" @change="submitFilters($event)"
                            class="h-[42px] cursor-pointer appearance-none rounded-field bg-surface-muted pl-md pr-10 text-bodysm text-ink
                                   border-hair border-hairline outline-none focus:border-primary">
                        <option value="">Any salary</option>
                        @foreach ([15000, 20000, 25000, 30000, 40000, 50000] as $step)
                            <option value="{{ $step }}" @selected((string) $filters['min_salary'] === (string) $step)>
                                ₹{{ number_format($step) }}+
                            </option>
                        @endforeach
                    </select>
                    <span aria-hidden="true" class="pointer-events-none absolute right-sm top-1/2 -translate-y-1/2 text-ink-muted">
                        @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-4 w-4'])
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-sm">
                <div class="relative">
                    <select name="sort" @change="submitFilters($event)"
                            class="h-[42px] cursor-pointer appearance-none rounded-field bg-surface-muted pl-md pr-10 text-bodysm text-ink
                                   border-hair border-hairline outline-none focus:border-primary">
                        <option value="">Newest first</option>
                        <option value="salary" @selected($filters['sort'] === 'salary')>Highest salary</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
                    </select>
                    <span aria-hidden="true" class="pointer-events-none absolute right-sm top-1/2 -translate-y-1/2 text-ink-muted">
                        @include('admin.partials.icon', ['name' => 'chevronDown', 'class' => 'h-4 w-4'])
                    </span>
                </div>
                <button type="submit"
                        class="inline-flex h-[42px] items-center justify-center rounded-button bg-primary px-lg text-btn font-semibold text-ink-onPrimary shadow-button
                               transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]">
                    Apply
                </button>
            </div>
        </div>
    </form>

    {{-- Active filters as removable chips. Clearing one has to leave the others
         alone, which is why each is its own link rather than a form reset. --}}
    @if ($active)
        <div class="mt-md flex flex-wrap items-center gap-sm">
            @foreach ($active as $name => $label)
                <button type="button" @click="clearFilter(@js($name))"
                        class="inline-flex items-center gap-xs rounded-chip border-chip border-primary bg-primary-light px-md py-[6px] text-chip font-semibold text-primary-dark
                               transition-colors hover:bg-primary-light/70">
                    {{ $label }}
                    @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-[13px] w-[13px]'])
                </button>
            @endforeach
            <a href="{{ route('site.jobs') }}" class="text-btnghost font-semibold text-ink-muted transition-colors hover:text-primary-dark">
                Clear all
            </a>
        </div>
    @endif

    {{-- ── results ──────────────────────────────────────────────────────── --}}
    @if ($jobs->isEmpty())
        <div class="mt-xl overflow-hidden rounded-card border-hair border-hairline bg-surface">
            <div class="flex flex-col items-center justify-center p-xxxl text-center">
                <div class="red-wash flex h-[76px] w-[76px] items-center justify-center rounded-card border-hair border-hairline text-ink-muted">
                    @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-[28px] w-[28px]'])
                </div>
                <h3 class="mt-lg text-h4 text-ink">No openings match that</h3>
                <p class="mt-xs max-w-sm text-bodysm text-ink-secondary">
                    Try widening the city or removing the salary floor — new postings go live daily.
                </p>
                <a href="{{ route('site.jobs') }}"
                   class="mt-xl inline-flex h-[42px] items-center justify-center rounded-button border-btn border-primary bg-surface px-lg text-btn font-semibold text-primary
                          transition-colors hover:bg-primary-light">
                    Clear filters
                </a>
            </div>
        </div>
    @else
        <div class="stagger mt-xl grid grid-cols-1 gap-md md:grid-cols-2 lg:grid-cols-3">
            @foreach ($jobs as $job)
                @include('site.partials.job-card', ['job' => $job])
            @endforeach
        </div>

        <div class="mt-xl">
            {{ $jobs->links('site.partials.pagination') }}
        </div>
    @endif
</div>
@endsection
