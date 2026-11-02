{{--
  Sign in — a popup over whatever page the visitor is on, so they never lose
  their place: Apply, Save, Post a job all open this and carry on after.
  Phone + OTP, the same credential as the app. Driven by `$store.auth.modal`
  and `Inthes.sendOtp / verifyOtp / closeSignIn` (public/js/site/core.js).

  One account, one job: the visitor signs in either as a job seeker or as an
  employer — separate accounts, even on the same number. The picker shows
  which one; it is fixed (`lockRole`) when the action needs one kind.
--}}
<div x-data x-show="$store.auth.modal.open" x-cloak
     class="fixed inset-0 z-[95] flex items-end justify-center bg-ink/50 backdrop-blur-[2px] sm:items-center sm:px-page"
     x-transition.opacity.duration.200ms
     @keydown.escape.window="$store.auth.modal.open && Inthes.closeSignIn()">
  <div @click.outside="Inthes.closeSignIn()" role="dialog" aria-modal="true" aria-labelledby="signin-title"
       class="relative w-full max-w-[420px] overflow-hidden rounded-t-sheet bg-surface shadow-raised sm:rounded-dialog animate-pop-in">
    <button type="button" @click="Inthes.closeSignIn()" aria-label="Close"
            class="absolute right-md top-md inline-flex h-9 w-9 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted hover:text-ink">
      @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
    </button>

    <div class="hero-wash px-xl pb-lg pt-xl">
      @include('admin.partials.logo', ['size' => 40])
      <h2 id="signin-title" class="mt-lg text-h2 text-ink"
          x-text="$store.auth.modal.stage !== 'phone' ? 'Enter the 6-digit code'
            : ($store.auth.modal.lockRole ? ($store.auth.modal.role === 'recruiter' ? 'Sign in as an employer' : 'Sign in as a job seeker') : 'Sign in or create an account')"></h2>
      <p class="mt-xs text-bodysm text-ink-secondary" x-show="$store.auth.modal.stage === 'phone'"
         x-text="$store.auth.modal.switching
           ? ($store.auth.modal.role === 'recruiter' ? 'Your employer account is separate from your job seeker account. New here? You\'ll set it up next.' : 'Your job seeker account is separate from your employer account. New here? You\'ll set up your profile next.')
           : ($store.auth.modal.reason ? 'Sign in ' + $store.auth.modal.reason + '. It takes one OTP.' : 'Use your mobile number — the same account works in the Inthes app.')"></p>
      <p class="mt-xs text-bodysm text-ink-secondary" x-show="$store.auth.modal.stage === 'otp'">
        <span x-text="$store.auth.modal.role === 'recruiter' ? 'Employer account' : 'Job seeker account'" class="font-semibold text-ink"></span> ·
        sent to <span class="font-semibold text-ink">+91 <span x-text="$store.auth.modal.phone"></span></span> ·
        <button type="button" @click="$store.auth.modal.stage = 'phone'; $store.auth.modal.error = ''" class="font-semibold text-primary-dark hover:underline">Change</button>
      </p>
    </div>

    <div class="px-xl pb-xl">
      {{-- Phone --}}
      <form x-show="$store.auth.modal.stage === 'phone'" @submit.prevent="Inthes.sendOtp()" class="space-y-md">
        {{-- Which account --}}
        <div>
          <span class="wa-label" x-text="$store.auth.modal.lockRole ? 'Signing in to your' : 'I want to'">I want to</span>
          <template x-if="!$store.auth.modal.lockRole">
            <div class="grid grid-cols-2 gap-sm" role="radiogroup" aria-label="Account type">
              <template x-for="r in [['candidate', 'Find a job', 'Job seeker account', 'user'], ['recruiter', 'Hire staff', 'Employer account', 'briefcase']]" :key="r[0]">
                <button type="button" role="radio" :aria-checked="$store.auth.modal.role === r[0]" @click="Inthes.pickRole(r[0])" :data-role="r[0]"
                        :class="$store.auth.modal.role === r[0] ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted'"
                        class="flex items-center gap-sm rounded-field border-chip px-md py-sm text-left transition-colors">
                  <span x-html="ICONS[r[3]]" :class="$store.auth.modal.role === r[0] ? 'text-primary' : 'text-ink-secondary'" class="shrink-0 [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
                  <span class="min-w-0">
                    <span class="block text-bodysm font-semibold text-ink" x-text="r[1]"></span>
                    <span class="block truncate text-caption text-ink-muted" x-text="r[2]"></span>
                  </span>
                </button>
              </template>
            </div>
          </template>
          <template x-if="$store.auth.modal.lockRole">
            <div class="flex items-center gap-sm rounded-field border-chip border-primary bg-primary-light px-md py-sm" data-role-locked>
              <span x-html="$store.auth.modal.role === 'recruiter' ? ICONS.briefcase : ICONS.user" class="shrink-0 text-primary [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
              <span class="min-w-0">
                <span class="block text-bodysm font-semibold text-ink" x-text="$store.auth.modal.role === 'recruiter' ? 'Employer account' : 'Job seeker account'"></span>
                <span class="block text-caption text-ink-muted" x-text="$store.auth.modal.role === 'recruiter' ? 'To post jobs and manage applicants' : 'To apply for jobs and track applications'"></span>
              </span>
            </div>
          </template>
        </div>
        <label class="block">
          <span class="wa-label">Mobile number @include('site.ui.mark', ['required' => true])</span>
          <div class="flex items-center gap-sm">
            <span class="inline-flex h-[50px] shrink-0 items-center rounded-field bg-surface-muted px-md text-input text-ink-secondary">+91</span>
            <input type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10-digit mobile number"
                   x-model="$store.auth.modal.phone"
                   x-effect="$store.auth.modal.open && $store.auth.modal.stage === 'phone' && $nextTick(() => $el.focus())"
                   @input="$store.auth.modal.phone = $store.auth.modal.phone.replace(/\D/g, ''); $store.auth.modal.error = ''"
                   class="wa-input">
          </div>
        </label>
        <p x-show="$store.auth.modal.error" x-text="$store.auth.modal.error" role="alert" class="rounded-field bg-danger-bg px-md py-sm text-bodysm text-danger"></p>
        <button type="submit" :disabled="$store.auth.modal.busy" class="wa-btn w-full">
          <span x-show="$store.auth.modal.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
          <span x-show="!$store.auth.modal.busy">Get OTP</span>
        </button>
        <p class="text-center text-caption text-ink-muted">
          By continuing you agree to our
          <a href="{{ route('site.page', 'terms') }}" class="font-semibold text-primary-dark hover:underline">Terms</a> and
          <a href="{{ route('site.page', 'privacy') }}" class="font-semibold text-primary-dark hover:underline">Privacy Policy</a>.
        </p>
      </form>

      {{-- Code --}}
      <form x-show="$store.auth.modal.stage === 'otp'" @submit.prevent="Inthes.verifyOtp()" class="space-y-md">
        <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="······"
               x-model="$store.auth.modal.otp"
               x-effect="$store.auth.modal.open && $store.auth.modal.stage === 'otp' && $nextTick(() => $el.focus())"
               @input="$store.auth.modal.otp = $store.auth.modal.otp.replace(/\D/g, ''); $store.auth.modal.error = ''; if ($store.auth.modal.otp.length === 6) setTimeout(() => Inthes.verifyOtp(), 250)"
               class="wa-input text-center text-h2 tracking-[12px]">
        <p x-show="$store.auth.modal.error" x-text="$store.auth.modal.error" role="alert" class="rounded-field bg-danger-bg px-md py-sm text-bodysm text-danger"></p>
        <button type="submit" :disabled="$store.auth.modal.otp.length !== 6 || $store.auth.modal.busy" class="wa-btn w-full">
          <span x-show="$store.auth.modal.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
          <span x-show="!$store.auth.modal.busy">Verify &amp; continue</span>
        </button>
        <div class="text-center">
          <button type="button" x-show="$store.auth.modal.resendIn <= 0" @click="Inthes.sendOtp()" class="text-btnghost font-semibold text-primary-dark hover:underline">Resend code</button>
          <span x-show="$store.auth.modal.resendIn > 0" class="text-caption text-ink-muted">Resend code in <span x-text="$store.auth.modal.resendIn"></span>s</span>
        </div>
      </form>
    </div>
  </div>
</div>
