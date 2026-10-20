{{--
  Welcome → mobile number → OTP. The app's own three screens and copy.
  One account covers both sides; the side picked on Welcome is a UI setting.
--}}

{{-- ── Welcome ─────────────────────────────────────────────────────── --}}
<template x-if="is('welcome')">
  <div class="hero-wash relative flex min-h-screen items-center justify-center overflow-hidden px-page py-xxl">
    <div class="dot-grid absolute inset-0 opacity-60" aria-hidden="true"></div>
    <div class="relative w-full max-w-[440px] animate-enter-up text-center">
      <a :href="WEBAPP_CONFIG.siteUrl" class="inline-block">@include('admin.partials.logo', ['size' => 64, 'class' => 'mx-auto'])</a>
      <h1 class="mt-xl text-h1 text-ink">Welcome to Inthes</h1>
      <p class="mt-sm text-body text-ink-secondary">Healthcare jobs for nurses, doctors, pharmacists and lab professionals.</p>

      <div class="mt-xxl space-y-md">
        <button @click="chooseSide('jobSeeker')" class="wa-card flex w-full items-center gap-lg text-left transition-[transform,box-shadow] hover:-translate-y-[2px] hover:shadow-raised">
          <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-card bg-primary-light text-primary">
            @include('admin.partials.icon', ['name' => 'search', 'class' => 'h-6 w-6'])
          </span>
          <span class="flex-1">
            <span class="block text-h4 text-ink">Need a Job</span>
            <span class="block text-bodysm text-ink-secondary">Find and apply to healthcare jobs near you</span>
          </span>
          @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-5 w-5 text-ink-muted'])
        </button>
        <button @click="chooseSide('recruiter')" class="wa-card flex w-full items-center gap-lg text-left transition-[transform,box-shadow] hover:-translate-y-[2px] hover:shadow-raised">
          <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-card bg-primary-light text-primary">
            @include('admin.partials.icon', ['name' => 'briefcase', 'class' => 'h-6 w-6'])
          </span>
          <span class="flex-1">
            <span class="block text-h4 text-ink">Post a Job</span>
            <span class="block text-bodysm text-ink-secondary">Hire verified healthcare professionals</span>
          </span>
          @include('admin.partials.icon', ['name' => 'chevronRight', 'class' => 'h-5 w-5 text-ink-muted'])
        </button>
      </div>

      <div class="mt-xxl flex flex-col items-center gap-sm">
        <a href="#/jobs" class="text-btnghost font-semibold text-primary-dark hover:underline">Browse jobs without signing in</a>
        <a :href="playStoreUrl" class="mt-sm inline-flex items-center gap-sm rounded-button bg-surface-dark px-lg py-sm text-ink-onPrimary hover:opacity-90">
          @include('admin.partials.icon', ['name' => 'smartphone', 'class' => 'h-5 w-5'])
          <span class="text-left leading-tight"><span class="block text-[10px] opacity-80">GET IT ON</span><span class="block text-[14px] font-semibold">Google Play</span></span>
        </a>
      </div>
    </div>
  </div>
</template>

{{-- ── Step 1: mobile number ───────────────────────────────────────── --}}
<template x-if="is('auth')">
  <div class="hero-wash relative flex min-h-screen items-center justify-center overflow-hidden px-page py-xxl">
    <div class="dot-grid absolute inset-0 opacity-60" aria-hidden="true"></div>
    <div class="relative w-full max-w-[420px] animate-enter-up">
      <button @click="back('/welcome')" class="mb-lg inline-flex h-10 w-10 items-center justify-center rounded-field bg-surface/80 text-ink shadow-card hover:bg-surface" aria-label="Back">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
      </button>
      <div class="wa-card">
        <span class="text-kicker text-ink-secondary">STEP 1 OF 2</span>
        <h1 class="mt-xs text-h1 text-ink">What's your mobile number?</h1>
        <p class="mt-sm text-bodysm text-ink-secondary">We'll send you a 6-digit code to verify it.</p>

        <form @submit.prevent="sendOtp()" class="mt-xl space-y-lg">
          <div class="flex items-center gap-sm">
            <span class="inline-flex h-[50px] shrink-0 items-center rounded-field bg-surface-muted px-md text-input text-ink-secondary">+91</span>
            <input type="tel" inputmode="numeric" autocomplete="tel-national" autofocus maxlength="10"
                   x-model="authPhone" @input="authPhone = authPhone.replace(/\D/g, ''); authError = ''"
                   placeholder="Mobile number" class="wa-input">
          </div>
          <p x-show="authError" x-text="authError" role="alert" class="rounded-field border-hair border-danger/30 bg-danger-bg px-md py-sm text-bodysm text-danger"></p>
          <button type="submit" :disabled="authBusy" class="wa-btn w-full">
            <template x-if="authBusy">@include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-[18px] w-[18px] animate-spin'])</template>
            <span x-show="!authBusy">Send OTP</span>
          </button>
        </form>
        <p class="mt-lg text-center text-caption text-ink-muted">
          By continuing you agree to our
          <a href="#/page/terms" class="font-semibold text-primary-dark hover:underline">Terms</a> and
          <a href="#/page/privacy" class="font-semibold text-primary-dark hover:underline">Privacy Policy</a>.
        </p>
      </div>
    </div>
  </div>
</template>

{{-- ── Step 2: the code ────────────────────────────────────────────── --}}
<template x-if="is('otp')">
  <div class="hero-wash relative flex min-h-screen items-center justify-center overflow-hidden px-page py-xxl">
    <div class="dot-grid absolute inset-0 opacity-60" aria-hidden="true"></div>
    <div class="relative w-full max-w-[420px] animate-enter-up">
      <button @click="go('/auth', true)" class="mb-lg inline-flex h-10 w-10 items-center justify-center rounded-field bg-surface/80 text-ink shadow-card hover:bg-surface" aria-label="Back">
        @include('admin.partials.icon', ['name' => 'chevronLeft', 'class' => 'h-6 w-6'])
      </button>
      <div class="wa-card">
        <span class="text-kicker text-ink-secondary">STEP 2 OF 2</span>
        <h1 class="mt-xs text-h1 text-ink">Enter the Code</h1>
        <p class="mt-sm text-bodysm text-ink-secondary">
          Sent to <span class="font-semibold text-ink">+91 <span x-text="authPhone"></span></span> ·
          <button type="button" @click="go('/auth', true)" class="font-semibold text-primary-dark hover:underline">Change</button>
        </p>

        <form @submit.prevent="verifyOtp()" class="mt-xl space-y-lg">
          <input type="text" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="6"
                 x-model="authOtp"
                 @input="authOtp = authOtp.replace(/\D/g, ''); authError = ''; if (authOtp.length === 6) setTimeout(() => verifyOtp(), 320)"
                 placeholder="······"
                 class="wa-input text-center text-h2 tracking-[12px]">
          <p x-show="authError" x-text="authError" role="alert" class="rounded-field border-hair border-danger/30 bg-danger-bg px-md py-sm text-bodysm text-danger"></p>
          <button type="submit" :disabled="authOtp.length !== 6 || authBusy" class="wa-btn w-full">
            <template x-if="authBusy">@include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-[18px] w-[18px] animate-spin'])</template>
            <span x-show="!authBusy">Verify</span>
          </button>
          <div class="text-center">
            <button type="button" x-show="resendIn <= 0" @click="sendOtp()" class="text-btnghost font-semibold text-primary-dark hover:underline">Resend code</button>
            <span x-show="resendIn > 0" class="text-caption text-ink-muted">Resend code in <span x-text="resendIn"></span>s</span>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
