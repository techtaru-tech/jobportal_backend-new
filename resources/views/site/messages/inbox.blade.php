{{--
  The two-pane messages inbox, shared by /account/messages (candidate) and
  /employer/messages (recruiter):

    @include('site.messages.inbox', ['side' => 'candidate'])

  Left: conversations with search. Right: the thread in `?c=`. Below `md` the
  list fills the page and an open thread covers the screen with a back arrow.
  Behaviour in js/site/pages/messages.js (pushed here).
--}}
@push('page-scripts')
<script defer src="{{ asset('js/site/pages/messages.js') }}?v={{ filemtime(public_path('js/site/pages/messages.js')) }}"></script>
@endpush

@php $isRecruiter = ($side ?? 'candidate') === 'recruiter'; @endphp

<div x-data="inbox(@js(['side' => $isRecruiter ? 'recruiter' : 'candidate']))"
     x-effect="document.documentElement.classList.toggle('overflow-hidden', !!activeId && window.innerWidth < 768)"
     class="overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card md:grid md:h-[calc(100vh-140px)] md:min-h-[520px] md:grid-cols-[300px_1fr] lg:grid-cols-[340px_1fr]">

  {{-- ── Conversation list ─────────────────────────────────────────── --}}
  <aside class="flex min-h-[60vh] flex-col md:min-h-0 md:border-r md:border-hairline-divider">
    <div class="shrink-0 border-b border-hairline-divider px-lg pb-md pt-lg">
      <h1 class="text-h3 text-ink">Messages</h1>
      <p class="mt-[2px] text-caption text-ink-secondary">{{ $isRecruiter ? 'Conversations with your applicants' : 'Conversations with recruiters about your applications' }}</p>
      <label class="relative mt-md block">
        <span class="pointer-events-none absolute left-md top-1/2 -translate-y-1/2 text-ink-muted">
          @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-4 w-4'])
        </span>
        <input type="search" x-model="search" placeholder="{{ $isRecruiter ? 'Search applicants or jobs' : 'Search jobs or employers' }}"
               class="wa-input h-10 pl-[38px] text-bodysm">
      </label>
    </div>

    <div class="thin-scrollbar min-h-0 flex-1 overflow-y-auto">
      <template x-if="!listLoaded">
        <div>@include('site.ui.spinner')</div>
      </template>

      <template x-if="listLoaded && listError">
        <div class="px-lg py-xl text-center">
          <p class="text-bodysm text-ink-secondary" x-text="listError"></p>
          <button type="button" class="wa-btn-ghost-sm mt-md" @click="loadList()">Try again</button>
        </div>
      </template>

      <template x-if="listLoaded && !listError && list.length === 0">
        <div class="px-xl py-xxxl text-center">
          <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
            @include('admin.partials.icon', ['name' => 'message', 'class' => 'h-6 w-6'])
          </span>
          <p class="mt-lg text-h4 text-ink">No conversations yet</p>
          @if ($isRecruiter)
            <p class="mt-xs text-bodysm text-ink-secondary">Each applicant gets a thread here. Open an applicant from your jobs to start one.</p>
            <a href="{{ url('/employer/jobs') }}" class="wa-btn-sm mt-lg">Go to your jobs</a>
          @else
            <p class="mt-xs text-bodysm text-ink-secondary">When you apply for a job, you can talk to the recruiter here.</p>
            <a href="{{ route('site.jobs') }}" class="wa-btn-sm mt-lg">Find jobs</a>
          @endif
        </div>
      </template>

      <template x-if="listLoaded && list.length > 0 && filtered.length === 0">
        <p class="px-lg py-xl text-center text-bodysm text-ink-secondary">No conversations match “<span x-text="search"></span>”.</p>
      </template>

      <template x-for="c in filtered" :key="c.conversation_id">
        <button type="button" @click="select(c.conversation_id)"
                :class="c.conversation_id === activeId ? 'bg-primary-light/60' : 'hover:bg-surface-muted'"
                class="relative flex w-full items-center gap-md border-b border-hairline-divider px-lg py-md text-left transition-colors">
          <span x-show="c.conversation_id === activeId" class="absolute inset-y-0 left-0 w-[3px] bg-primary"></span>
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-light text-[14px] font-semibold text-primary-dark"
                x-html="initials(c.title) || ICONS.user" :class="!initials(c.title) && '[&>svg]:h-5 [&>svg]:w-5'"></span>
          <span class="min-w-0 flex-1">
            <span class="flex items-baseline gap-sm">
              <span class="min-w-0 flex-1 truncate text-bodysm font-semibold text-ink" x-text="c.title"></span>
              <span class="shrink-0 text-[11.5px]" :class="c.unread_count > 0 ? 'font-semibold text-primary-dark' : 'text-ink-muted'"
                    x-text="listTime(c.last_message_at)"></span>
            </span>
            <span class="block truncate text-caption text-ink-muted" x-text="c.subtitle"></span>
            <span class="mt-[2px] flex items-center gap-sm">
              <span class="min-w-0 flex-1 truncate text-[13px]"
                    :class="c.unread_count > 0 ? 'font-semibold text-ink' : (c.last_message ? 'text-ink-secondary' : 'italic text-ink-muted')"
                    x-text="preview(c)"></span>
              <span x-show="c.unread_count > 0"
                    class="shrink-0 rounded-full bg-primary px-[7px] py-[1px] text-[11px] font-bold text-ink-onPrimary"
                    x-text="c.unread_count > 99 ? '99+' : c.unread_count"></span>
            </span>
          </span>
        </button>
      </template>
    </div>
  </aside>

  {{-- ── Thread ────────────────────────────────────────────────────── --}}
  <section :class="activeId ? 'fixed inset-0 z-[60] flex md:static md:z-auto' : 'hidden md:flex'"
           class="min-h-0 flex-col bg-canvas-alt">

    {{-- Nothing selected (desktop) --}}
    <template x-if="!activeId && list.length > 0">
      <div class="flex flex-1 flex-col items-center justify-center px-xl text-center">
        <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-surface text-primary shadow-card">
          @include('admin.partials.icon', ['name' => 'message', 'class' => 'h-7 w-7'])
        </span>
        <p class="mt-lg text-h4 text-ink">Select a conversation</p>
        <p class="mt-xs max-w-[320px] text-bodysm text-ink-secondary">
          {{ $isRecruiter ? 'Pick an applicant on the left to read and reply.' : 'Pick a conversation on the left to read and reply to the recruiter.' }}
        </p>
      </div>
    </template>

    <template x-if="activeId">
      <div class="flex min-h-0 flex-1 flex-col">
        {{-- Header: the other party, the job, a link out --}}
        <div class="flex shrink-0 items-center gap-sm border-b border-hairline-divider bg-surface px-sm py-sm md:px-lg">
          <button type="button" @click="backToList()" aria-label="Back to conversations"
                  class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted md:hidden">
            @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
          </button>
          <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-light text-[14px] font-semibold text-primary-dark"
                x-html="initials(title) || ICONS.user" :class="!initials(title) && '[&>svg]:h-5 [&>svg]:w-5'"></span>
          <div class="min-w-0 flex-1">
            <p class="flex items-center gap-sm">
              <span class="truncate text-h5 text-ink" x-text="title"></span>
              <span x-show="active?.status" class="wa-pill hidden shrink-0 bg-surface-muted text-ink-secondary sm:inline-flex" x-text="statusLabel(active?.status)"></span>
            </p>
            <p class="truncate text-caption text-ink-secondary">
              <span x-show="otherTyping" class="font-semibold text-success">typing…</span>
              <span x-show="!otherTyping" x-text="active?.subtitle || ''"></span>
            </p>
          </div>
          <template x-if="profileLink">
            <a :href="profileLink" class="wa-btn-ghost-sm shrink-0 !h-9 !px-md">
              @include('admin.partials.icon', ['name' => $isRecruiter ? 'user' : 'clipboard', 'class' => 'h-4 w-4'])
              <span class="hidden sm:inline">{{ $isRecruiter ? 'View applicant' : 'View application' }}</span>
            </a>
          </template>
        </div>

        {{-- Messages --}}
        <div x-ref="scroller" class="thin-scrollbar min-h-0 flex-1 overflow-y-auto px-page py-lg md:px-xl">
          <template x-if="!threadLoaded">
            <div>@include('site.ui.spinner')</div>
          </template>
          <template x-if="threadLoaded && threadError">
            <div class="flex h-full flex-col items-center justify-center text-center">
              <p class="text-bodysm text-ink-secondary" x-text="threadError"></p>
            </div>
          </template>
          <template x-if="threadLoaded && !threadError && messages.length === 0">
            <div class="flex h-full flex-col items-center justify-center text-center">
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-surface text-primary shadow-card">
                @include('admin.partials.icon', ['name' => 'message', 'class' => 'h-5 w-5'])
              </span>
              <p class="mt-md text-bodysm text-ink-secondary">No messages yet. Start the conversation.</p>
            </div>
          </template>

          <template x-for="row in rows" :key="row.key">
            <div>
              <template x-if="row.sep">
                <div class="flex justify-center py-md">
                  <span class="rounded-full bg-surface px-md py-[4px] text-caption font-semibold text-ink-secondary shadow-card" x-text="row.label"></span>
                </div>
              </template>
              <template x-if="!row.sep">
                <div class="mb-sm flex" :class="row.m.sender === side ? 'justify-end' : 'justify-start'">
                  <div class="max-w-[80%] px-md pb-[6px] pt-[8px] lg:max-w-[64%]"
                       :class="row.m.sender === side
                         ? 'rounded-[16px] rounded-br-[4px] bg-primary text-ink-onPrimary'
                         : 'rounded-[16px] rounded-bl-[4px] border-hair border-hairline bg-surface text-ink'">
                    <p class="whitespace-pre-wrap break-words text-[14.5px] leading-[1.4]" x-text="row.m.text"></p>
                    <p class="mt-[2px] flex items-center justify-end gap-xs text-[10.5px]"
                       :class="row.m.sender === side ? 'text-ink-onPrimary/75' : 'text-ink-muted'">
                      <span x-text="clock(row.m.sent_at)"></span>
                      {{-- My messages: sending / sent / delivered / read --}}
                      <template x-if="row.m.sender === side">
                        <span class="inline-flex" :class="row.m.status === 'read' ? 'text-[#BFE3FF]' : ''" :title="row.m.status">
                          <template x-if="row.m.status === 'sending'">
                            <span x-html="ICONS.clock" class="[&>svg]:h-[11px] [&>svg]:w-[11px]"></span>
                          </template>
                          <template x-if="row.m.status === 'sent'">
                            <span x-html="ICONS.check" class="[&>svg]:h-[13px] [&>svg]:w-[13px]"></span>
                          </template>
                          <template x-if="row.m.status === 'delivered' || row.m.status === 'read'">
                            <svg viewBox="0 0 24 24" class="h-[13px] w-[16px]" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M2 13l4 4L15 7"/><path d="M10 16l1 1L21 7"/></svg>
                          </template>
                        </span>
                      </template>
                    </p>
                  </div>
                </div>
              </template>
            </div>
          </template>

          <div x-show="otherTyping" class="mb-sm flex">
            <div class="flex items-center gap-[4px] rounded-[16px] rounded-bl-[4px] border-hair border-hairline bg-surface px-md py-[12px]">
              <span class="h-[6px] w-[6px] animate-bounce rounded-full bg-ink-muted"></span>
              <span class="h-[6px] w-[6px] animate-bounce rounded-full bg-ink-muted [animation-delay:150ms]"></span>
              <span class="h-[6px] w-[6px] animate-bounce rounded-full bg-ink-muted [animation-delay:300ms]"></span>
            </div>
          </div>
        </div>

        {{-- Composer --}}
        <form @submit.prevent="send()" x-show="!threadError"
              class="flex shrink-0 items-end gap-sm border-t border-hairline-divider bg-surface px-page py-sm md:px-lg">
          <textarea x-ref="composer" x-model="text" @input="onInput()" @keydown.enter="onEnter($event)" rows="1" maxlength="2000"
                    placeholder="Write a message…" aria-label="Message"
                    x-effect="text; $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 140) + 'px'"
                    class="max-h-[140px] min-h-[44px] flex-1 resize-none rounded-[22px] border-hair border-hairline bg-surface-muted px-lg py-[11px] text-input text-ink outline-none placeholder:text-ink-muted focus:border-primary focus:shadow-glow"></textarea>
          <button type="submit" :disabled="!text.trim()" aria-label="Send"
                  class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-ink-onPrimary transition-colors hover:bg-primary-dark disabled:bg-hairline-strong">
            @include('admin.partials.icon', ['name' => 'send', 'class' => 'h-5 w-5'])
          </button>
        </form>
        <p class="hidden shrink-0 bg-surface px-lg pb-sm text-[11px] text-ink-muted md:block" x-show="!threadError">Enter to send · Shift + Enter for a new line</p>
      </div>
    </template>
  </section>
</div>
