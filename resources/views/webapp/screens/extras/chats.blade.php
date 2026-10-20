{{-- ── Chats ─────────────────────────────────────────────────────────── --}}
<template x-if="is('conversations')">
  <div class="wa-narrow">
    @include('webapp.ui.head', ['title' => 'Chats', 'back' => '/home'])

    <template x-if="!chatListLoaded && chatListBusy">
      <div>@include('webapp.ui.spinner')</div>
    </template>

    <template x-if="chatListLoaded && chatList.length === 0">
      <div class="flex flex-col items-center justify-center rounded-card border-hair border-dashed border-hairline-strong bg-surface px-xl py-xxxl text-center">
        <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-light text-primary">
          @include('admin.partials.icon', ['name' => 'message', 'class' => 'h-6 w-6'])
        </span>
        <p class="mt-lg text-h4 text-ink">No conversations yet</p>
        <p class="mt-xs max-w-[360px] text-bodysm text-ink-secondary"
           x-text="mode === 'recruiter'
             ? 'Once you shortlist or move an applicant forward, you can message them here.'
             : 'Once a recruiter responds to your application, the conversation will show up here.'"></p>
      </div>
    </template>

    <template x-if="chatList.length > 0">
      <div class="overflow-hidden rounded-card border-hair border-hairline bg-surface shadow-card">
        <template x-for="c in chatList" :key="c.conversation_id">
          <a :href="'#/chat/' + encodeURIComponent(c.conversation_id)"
             class="flex items-center gap-md border-b border-hairline-divider px-lg py-md transition-colors last:border-b-0 hover:bg-surface-muted">
            <span class="inline-flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-[14px] bg-primary-light text-[15px] font-semibold text-primary-dark"
                  x-text="initials(c.title)"></span>
            <span class="min-w-0 flex-1">
              <span class="flex items-baseline gap-sm">
                <span class="min-w-0 flex-1 truncate text-bodysm font-bold text-ink" x-text="c.title"></span>
                <span x-show="c.last_message" class="shrink-0 text-caption text-ink-muted" x-text="c.last_message ? timeAgo(c.last_message.sent_at) : ''"></span>
              </span>
              <span class="mt-[2px] block truncate text-caption text-ink-muted" x-text="c.subtitle"></span>
              <template x-if="c.last_message">
                <span class="mt-[3px] flex items-center gap-sm">
                  <span class="min-w-0 flex-1 truncate text-bodysm"
                        :class="c.unread_count > 0 ? 'font-semibold text-ink' : 'text-ink-secondary'"
                        x-text="chatPreview(c)"></span>
                  <span x-show="c.unread_count > 0"
                        class="shrink-0 rounded-full bg-primary px-[7px] py-[2px] text-[11px] font-bold text-ink-onPrimary"
                        x-text="c.unread_count > 9 ? '9+' : c.unread_count"></span>
                </span>
              </template>
            </span>
            @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-4 w-4 shrink-0 text-ink-muted'])
          </a>
        </template>
      </div>
    </template>
  </div>
</template>

{{-- ── One thread ─────────────────────────────────────────────────────
     Keyed by the application id, the same thread from either end. The
     bubbles flip by which side I am in it. --}}
<template x-if="is('chat')">
  <div class="mx-auto w-full max-w-[860px] lg:px-xl lg:pt-xxl">
    <div class="flex flex-col overflow-hidden bg-canvas-alt lg:rounded-card lg:border-hair lg:border-hairline lg:shadow-card"
         :style="`height:${chatHeight}px`">

      {{-- Header: the other party and the job --}}
      <div class="flex shrink-0 items-center gap-sm border-b border-hairline-divider bg-surface px-sm py-sm lg:px-md">
        <button type="button" @click="back('/chats')" aria-label="Back"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
          @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
        </button>
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-primary-light text-[14px] font-semibold text-primary-dark"
              x-text="initials(chatTitle)"></span>
        <div class="min-w-0 flex-1">
          <p class="truncate text-h5 text-ink" x-text="chatTitle"></p>
          <p class="truncate text-caption text-ink-secondary">
            <span x-show="chatOtherTyping" class="font-semibold text-success">typing…</span>
            <span x-show="!chatOtherTyping" x-text="chatInfo?.subtitle || ''"></span>
          </p>
        </div>
        <template x-if="chatApplicantLink">
          <a :href="chatApplicantLink" class="wa-btn-ghost-sm shrink-0 !h-9 !px-md">
            @include('admin.partials.icon', ['name' => 'user', 'class' => 'h-4 w-4'])
            <span class="hidden sm:inline">View profile</span>
          </a>
        </template>
      </div>

      {{-- Messages --}}
      <div id="chat-scroll" class="thin-scrollbar flex-1 overflow-y-auto px-page py-lg">
        <template x-if="!chatLoaded">
          <div>@include('webapp.ui.spinner')</div>
        </template>
        <template x-if="chatLoaded && chatMessages.length === 0">
          <div class="flex h-full flex-col items-center justify-center text-center">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-primary-light text-primary">
              @include('admin.partials.icon', ['name' => 'message', 'class' => 'h-5 w-5'])
            </span>
            <p class="mt-md text-bodysm text-ink-secondary">No messages yet. Start the conversation.</p>
          </div>
        </template>

        <template x-for="row in chatRows" :key="row.key">
          <div>
            <template x-if="row.sep">
              <div class="flex justify-center py-md">
                <span class="rounded-field bg-surface-muted px-md py-[5px] text-caption font-semibold text-ink-secondary" x-text="row.label"></span>
              </div>
            </template>
            <template x-if="!row.sep">
              <div class="mb-sm flex" :class="row.m.sender === chatMe ? 'justify-end' : 'justify-start'">
                <div class="max-w-[78%] px-md pb-[7px] pt-[9px] lg:max-w-[70%]"
                     :class="row.m.sender === chatMe
                       ? 'rounded-[16px] rounded-br-[4px] bg-primary text-ink-onPrimary'
                       : 'rounded-[16px] rounded-bl-[4px] border-hair border-hairline bg-surface text-ink'">
                  <p class="whitespace-pre-wrap break-words text-body leading-[1.35]" x-text="row.m.text"></p>
                  <p class="mt-[3px] flex items-center justify-end gap-xs text-[10.5px]"
                     :class="row.m.sender === chatMe ? 'text-ink-onPrimary/70' : 'text-ink-muted'">
                    <span x-text="chatTime(row.m.sent_at)"></span>
                    {{-- Ticks on my own messages: sending / sent / delivered / read --}}
                    <template x-if="row.m.sender === chatMe">
                      <span class="inline-flex" :class="row.m.status === 'read' ? 'text-[#BFE3FF]' : 'text-ink-onPrimary/70'"
                            :title="row.m.status">
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

        {{-- The other side is typing --}}
        <div x-show="chatOtherTyping" class="mb-sm flex">
          <div class="flex items-center gap-[4px] rounded-[16px] rounded-bl-[4px] border-hair border-hairline bg-surface px-md py-[12px]">
            <span class="h-[6px] w-[6px] animate-bounce rounded-full bg-ink-muted"></span>
            <span class="h-[6px] w-[6px] animate-bounce rounded-full bg-ink-muted [animation-delay:150ms]"></span>
            <span class="h-[6px] w-[6px] animate-bounce rounded-full bg-ink-muted [animation-delay:300ms]"></span>
          </div>
        </div>
      </div>

      {{-- Composer --}}
      <form @submit.prevent="chatSend()" class="flex shrink-0 items-end gap-sm border-t border-hairline-divider bg-surface px-page py-sm">
        <textarea x-model="chatText" @input="chatOnInput()" rows="1" maxlength="2000" placeholder="Message…"
                  @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); chatSend() }"
                  x-effect="chatText; $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                  class="max-h-[120px] min-h-[44px] flex-1 resize-none rounded-[22px] bg-surface-muted px-lg py-[11px] text-input text-ink outline-none placeholder:text-ink-muted focus:shadow-glow"></textarea>
        <button type="submit" :disabled="!chatText.trim()" aria-label="Send"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-ink-onPrimary transition-colors hover:bg-primary-dark disabled:bg-hairline-strong">
          @include('admin.partials.icon', ['name' => 'arrowUp', 'class' => 'h-5 w-5'])
        </button>
      </form>
    </div>
  </div>
</template>
