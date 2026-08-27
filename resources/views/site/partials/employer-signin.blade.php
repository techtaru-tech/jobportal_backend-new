{{--
  Employer sign-in: phone, then the OTP.

  The same credential the app uses, deliberately — one account and one identity
  whichever surface it is used from. There is no password to forget and no
  separate "employer account" to reconcile later: `users.role` records the side
  somebody signed up on and is never a permission, so an account that has only
  ever applied for jobs can post one here without anything being migrated.

  Two stages rather than one form. Asking for a code beside a number nobody has
  submitted yet is asking for a code that does not exist.
--}}

{{-- Restoring: a centred spinner, since there is no shape to mimic yet. --}}
<template x-if="isRestoring">
  <div class="flex min-h-screen flex-col items-center justify-center gap-md">
    @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-6 w-6 animate-spin text-primary'])
    <span class="text-bodysm text-ink-secondary">Checking your session…</span>
  </div>
</template>

<template x-if="!user && !isRestoring">
  <div class="hero-wash relative flex min-h-screen items-center justify-center overflow-hidden px-page py-xxl">
    <div class="dot-grid absolute inset-0 opacity-60" aria-hidden="true"></div>

    <div class="relative w-full max-w-[420px] animate-enter-up">
      <div class="mb-xl text-center">
        <a href="{{ route('site.home') }}" class="inline-block">
          @include('admin.partials.logo', ['size' => 48, 'class' => 'mx-auto'])
        </a>
        <span class="mt-lg block text-kicker text-ink-secondary">FOR EMPLOYERS</span>
        <h1 class="mt-xs text-h1 text-ink">Post a job</h1>
        <p class="mt-sm text-bodysm text-ink-secondary">
          Sign in with your phone number — the same one you use in the app.
        </p>
      </div>

      <div class="rounded-card border-hair border-hairline bg-surface p-xl shadow-card">

        {{-- ── stage 1: the number ──────────────────────────────────── --}}
        <template x-if="authStage === 'phone'">
          <form @submit.prevent="sendOtp()" class="space-y-lg">
            <label class="block">
              <span class="mb-[5px] block text-label text-ink-secondary">Mobile number</span>
              <div class="flex items-center gap-sm">
                <span class="inline-flex h-[50px] shrink-0 items-center rounded-field bg-surface-muted px-md text-input text-ink-secondary">+91</span>
                <input type="tel" inputmode="numeric" autocomplete="tel" required autofocus
                       x-model="authPhone" maxlength="15" placeholder="98765 43210"
                       class="h-[50px] w-full rounded-field bg-surface-muted px-md text-input text-ink placeholder:text-ink-muted
                              border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                              focus:border-focus focus:border-primary focus:shadow-glow">
              </div>
            </label>

            <p x-show="authError" x-cloak x-text="authError" role="alert"
               class="rounded-field border-hair border-danger/30 bg-danger-bg px-md py-sm text-bodysm text-danger"></p>

            <button type="submit" :disabled="!phoneValid || authBusy"
                    class="inline-flex h-[50px] w-full items-center justify-center gap-sm rounded-button bg-primary px-md text-btn font-semibold text-ink-onPrimary shadow-button
                           transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]
                           disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
              <template x-if="authBusy">
                @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-[18px] w-[18px] animate-spin'])
              </template>
              <span x-show="!authBusy">Send code</span>
            </button>
          </form>
        </template>

        {{-- ── stage 2: the code ────────────────────────────────────── --}}
        <template x-if="authStage === 'otp'">
          <form @submit.prevent="verifyOtp()" class="space-y-lg">
            <div>
              <p class="text-bodysm text-ink-secondary">
                Code sent to <span class="font-semibold text-ink">+91 <span x-text="authPhone"></span></span>
              </p>
              <button type="button" @click="editPhone()"
                      class="mt-xs text-btnghost font-semibold text-primary-dark transition-colors hover:underline">
                Change number
              </button>
            </div>

            <label class="block">
              <span class="mb-[5px] block text-label text-ink-secondary">Verification code</span>
              <input type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
                     x-model="authOtp" maxlength="6" placeholder="······"
                     class="h-[50px] w-full rounded-field bg-surface-muted px-md text-center text-h3 tracking-[8px] text-ink placeholder:text-ink-muted
                            border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro
                            focus:border-focus focus:border-primary focus:shadow-glow">
            </label>

            <p x-show="authError" x-cloak x-text="authError" role="alert"
               class="rounded-field border-hair border-danger/30 bg-danger-bg px-md py-sm text-bodysm text-danger"></p>

            <button type="submit" :disabled="authOtp.trim().length < 4 || authBusy"
                    class="inline-flex h-[50px] w-full items-center justify-center gap-sm rounded-button bg-primary px-md text-btn font-semibold text-ink-onPrimary shadow-button
                           transition-[background-color,transform] duration-micro ease-out hover:bg-primary-dark active:scale-[0.97]
                           disabled:cursor-not-allowed disabled:bg-hairline disabled:shadow-none">
              <template x-if="authBusy">
                @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-[18px] w-[18px] animate-spin'])
              </template>
              <span x-show="!authBusy">Verify &amp; continue</span>
            </button>

            {{-- Held shut for a moment: the server caps sends per number per
                 window, so an impatient tap now is a lockout later. --}}
            <div class="text-center">
              <button type="button" x-show="resendIn <= 0" @click="sendOtp()"
                      class="text-btnghost font-semibold text-primary-dark transition-colors hover:underline">
                Resend code
              </button>
              <span x-show="resendIn > 0" x-cloak class="text-caption text-ink-muted">
                Resend available in <span x-text="resendIn"></span>s
              </span>
            </div>
          </form>
        </template>
      </div>

      <p class="mt-lg text-center text-caption text-ink-muted">
        Looking for a job instead?
        <a href="{{ route('site.jobs') }}" class="font-semibold text-primary-dark hover:underline">Browse openings</a>
      </p>
    </div>
  </div>
</template>
