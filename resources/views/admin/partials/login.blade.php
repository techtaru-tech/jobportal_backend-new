{{--
  Operator sign-in. Mirrors admin_panel/src/pages/Login.tsx: the mark over a
  Kicker eyebrow and an h1, then a single Card holding the form.
--}}
<template x-if="!admin && !isRestoring">
  <div class="flex min-h-screen items-center justify-center bg-canvas-alt p-page">
    <div class="w-full max-w-[400px] animate-enter-up">
      <div class="mb-xl text-center">
        @include('admin.partials.logo', ['size' => 48, 'class' => 'mx-auto mb-lg'])
        <span class="block text-kicker text-ink-secondary">INTHES</span>
        <h1 class="mt-xs text-h1 text-ink">Admin panel</h1>
        <p class="mt-sm text-bodysm text-ink-secondary">
          Operator access. App accounts sign in on the phone, not here.
        </p>
      </div>

      <div class="rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card p-lg">
        <form @submit.prevent="login()" class="space-y-lg">
          <label class="block">
            <span class="mb-[5px] block text-label text-ink-secondary">Email</span>
            <input type="email" autocomplete="username" required autofocus
                   x-model="loginForm.email" placeholder="you@example.com"
                   class="w-full rounded-field bg-surface-muted px-md h-[50px] text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
          </label>

          <label class="block">
            <span class="mb-[5px] block text-label text-ink-secondary">Password</span>
            <input type="password" autocomplete="current-password" required
                   x-model="loginForm.password" placeholder="••••••••"
                   class="w-full rounded-field bg-surface-muted px-md h-[50px] text-input text-ink placeholder:text-ink-muted border-hair border-transparent outline-none transition-[border-color,box-shadow] duration-micro focus:border-focus focus:border-primary focus:shadow-glow">
          </label>

          <p x-show="loginError" x-text="loginError" role="alert"
             class="rounded-field bg-danger-bg border-hair border-danger/30 px-md py-sm text-bodysm text-danger"></p>

          {{-- Loading keeps the button at full opacity and swaps its content
               for a spinner, so it does not appear to switch off mid-request. --}}
          <button type="submit" :disabled="loginBusy"
                  class="inline-flex w-full h-[50px] items-center justify-center gap-sm rounded-button font-semibold text-btn px-md
                         bg-primary text-ink-onPrimary shadow-button transition-[background-color,box-shadow,transform] duration-micro ease-out
                         hover:bg-primary-dark active:scale-[0.97] disabled:cursor-not-allowed">
            <template x-if="loginBusy">
              @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-[18px] w-[18px] animate-spin'])
            </template>
            <template x-if="!loginBusy">
              <span class="inline-flex items-center gap-sm">
                @include('admin.partials.icon', ['name' => 'logIn', 'class' => 'h-[18px] w-[18px]'])
                <span>Sign in</span>
              </span>
            </template>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

{{-- Session restore: a centred spinner, since there is no shape to mimic. --}}
<template x-if="isRestoring">
  <div class="flex min-h-screen flex-col items-center justify-center gap-md bg-canvas-alt p-xxxl">
    @include('admin.partials.icon', ['name' => 'loader', 'class' => 'h-6 w-6 animate-spin text-primary'])
    <span class="text-bodysm text-ink-secondary">Checking your session…</span>
  </div>
</template>
