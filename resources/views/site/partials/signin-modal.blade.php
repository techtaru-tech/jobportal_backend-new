{{--
  Sign in — the app's own three screens, over whatever page the visitor is
  on (so they never lose their place: Apply, Save, Post a job all open this
  and carry on after):

    welcome → "Where healthcare finds its people", Need a Job / Post a Job
              (WelcomeView — skipped when the action already names the side)
    phone   → Step 1 of 2: I want to (Find a job / Hire) + mobile number (AuthView)
    otp     → Step 2 of 2: Enter the Code, six boxes (OtpView)

  Full screen on a phone, like the app; a card on a computer. Driven by
  `$store.auth.modal` and `Inthes.sendOtp / verifyOtp / closeSignIn /
  startAs / backToWelcome` (public/js/site/core.js).

  One account, one job: a job seeker and an employer are separate accounts,
  even on the same number. The picker shows which one; it is fixed
  (`lockRole`) when the action needs one kind.
--}}
<div x-data x-show="$store.auth.modal.open" x-cloak
     class="fixed inset-0 z-[95] flex items-stretch justify-center bg-ink/50 backdrop-blur-[2px] sm:items-center sm:px-page"
     x-transition.opacity.duration.200ms
     @keydown.escape.window="$store.auth.modal.open && Inthes.closeSignIn()">
  <div @click.outside="Inthes.closeSignIn()" role="dialog" aria-modal="true" aria-labelledby="signin-title"
       class="relative flex w-full flex-col overflow-y-auto bg-surface shadow-raised sm:max-h-[92vh] sm:max-w-[440px] sm:rounded-dialog animate-pop-in">

    {{-- ── Welcome ───────────────────────────────────────────────────── --}}
    <div x-show="$store.auth.modal.stage === 'welcome'"
         class="relative flex min-h-[100dvh] flex-col px-xl pb-xl pt-xl sm:min-h-[640px]"
         style="background: linear-gradient(160deg, #3A0F10 0%, #1C1C1E 55%, #141416 100%);">
      <button type="button" @click="Inthes.closeSignIn()" aria-label="Close"
              class="absolute right-md top-md inline-flex h-9 w-9 items-center justify-center rounded-field text-white/70 hover:bg-white/10 hover:text-white">
        @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
      </button>
      @include('admin.partials.logo', ['size' => 48])
      <div class="mt-auto pt-xxxl">
        <h2 id="signin-title" class="text-[34px] font-bold leading-[1.12] tracking-[-0.5px] text-white">
          Where healthcare<br><span class="text-primary">finds its people.</span>
        </h2>
        <p class="mt-md text-bodysm text-white/70">
          Thousands of hospital, clinic and pharmacy roles — and the people to fill them. One account works for both.
        </p>
        <ul class="mt-lg space-y-md">
          @foreach ([
            ['zap', 'Apply in one tap once your profile is set up'],
            ['badgeCheck', 'Every employer is verified before their jobs go live'],
            ['bell', 'Job alerts the moment a matching role is posted'],
          ] as [$icon, $text])
            <li class="flex items-center gap-md">
              <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
                @include('admin.partials.icon', ['name' => $icon, 'class' => 'h-[15px] w-[15px]'])
              </span>
              <span class="text-bodysm text-white/80">{{ $text }}</span>
            </li>
          @endforeach
        </ul>
        <div class="mt-xxl space-y-sm">
          <button type="button" @click="Inthes.startAs('candidate')" class="wa-btn w-full" data-welcome="candidate">Need a Job</button>
          <button type="button" @click="Inthes.startAs('recruiter')" data-welcome="recruiter"
                  class="inline-flex h-[54px] w-full items-center justify-center rounded-button border border-white/35 text-btn font-semibold text-white hover:bg-white/10">
            Post a Job
          </button>
        </div>
        <p class="mt-md text-center text-caption text-white/60">You'll sign in with your mobile number next.</p>
      </div>
    </div>

    {{-- ── Step 1 of 2 · mobile number ───────────────────────────────── --}}
    <div x-show="$store.auth.modal.stage === 'phone'" class="flex min-h-[100dvh] flex-col px-xl pb-xl pt-lg sm:min-h-0">
      <div class="flex items-center justify-between">
        <button type="button" x-show="$store.auth.modal.canWelcome" @click="Inthes.backToWelcome()" aria-label="Back"
                class="-ml-sm inline-flex h-10 w-10 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
          @include('admin.partials.icon', ['name' => 'arrowLeft', 'class' => 'h-5 w-5'])
        </button>
        <span x-show="!$store.auth.modal.canWelcome"></span>
        <button type="button" @click="Inthes.closeSignIn()" aria-label="Close"
                class="-mr-sm inline-flex h-10 w-10 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted hover:text-ink">
          @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
        </button>
      </div>
      <p class="mt-lg text-kicker uppercase tracking-[1.2px] text-primary">Step 1 of 2</p>
      <h2 class="mt-sm text-h1 text-ink">Enter your mobile number</h2>
      <p class="mt-xs text-bodysm text-ink-secondary"
         x-text="$store.auth.modal.switching
           ? ($store.auth.modal.role === 'recruiter' ? 'Your employer account is separate from your job seeker account. New here? You\'ll set it up next.' : 'Your job seeker account is separate from your employer account. New here? You\'ll set up your profile next.')
           : ($store.auth.modal.reason ? 'Sign in ' + $store.auth.modal.reason + '. We\'ll send a 6-digit code to verify it.' : 'We\'ll send a 6-digit code to verify it.')"></p>

      <form @submit.prevent="Inthes.sendOtp()" class="mt-xl flex flex-1 flex-col">
        {{-- Which account (the app's IconChoiceChip pair) --}}
        <span class="wa-label">I want to @include('site.ui.mark', ['required' => true])</span>
        <div class="grid grid-cols-2 gap-sm" role="radiogroup" aria-label="Account type">
          <template x-for="r in [['candidate', 'Find a job', 'Job seeker account', 'search'], ['recruiter', 'Hire', 'Employer account', 'briefcase']]" :key="r[0]">
            <button type="button" role="radio" :aria-checked="$store.auth.modal.role === r[0]" @click="Inthes.pickRole(r[0])" :data-role="r[0]"
                    :disabled="$store.auth.modal.lockRole && $store.auth.modal.role !== r[0]"
                    :class="$store.auth.modal.role === r[0] ? 'border-primary bg-primary-light' : 'border-hairline hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-transparent'"
                    class="flex flex-col items-start gap-sm rounded-card border-chip p-md text-left transition-colors">
              <span class="inline-flex h-10 w-10 items-center justify-center rounded-full"
                    :class="$store.auth.modal.role === r[0] ? 'bg-primary text-white' : 'bg-surface-muted text-ink-secondary'">
                <span x-html="ICONS[r[3]]" class="[&>svg]:h-5 [&>svg]:w-5"></span>
              </span>
              <span class="min-w-0">
                <span class="block text-h5 text-ink" x-text="r[1]"></span>
                <span class="block text-caption text-ink-muted" x-text="r[2]"></span>
              </span>
            </button>
          </template>
        </div>
        <p class="mt-sm text-caption text-ink-muted">These are separate accounts — the same number can have both.</p>

        <label class="mt-xl block">
          <span class="wa-label">Mobile number @include('site.ui.mark', ['required' => true])</span>
          <div class="flex items-center gap-sm">
            <span class="inline-flex h-[54px] shrink-0 items-center gap-xs rounded-field bg-surface-muted px-md text-input text-ink">
              @include('admin.partials.icon', ['name' => 'smartphone', 'class' => 'h-[18px] w-[18px] text-ink-muted']) +91
            </span>
            <input type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="98765 43210"
                   x-model="$store.auth.modal.phone"
                   x-effect="$store.auth.modal.open && $store.auth.modal.stage === 'phone' && $nextTick(() => $el.focus())"
                   @input="$store.auth.modal.phone = $store.auth.modal.phone.replace(/\D/g, ''); $store.auth.modal.error = ''"
                   class="wa-input h-[54px] text-[18px] tracking-[1px]">
          </div>
        </label>
        <p x-show="$store.auth.modal.error" x-text="$store.auth.modal.error" role="alert" class="mt-md rounded-field bg-danger-bg px-md py-sm text-bodysm text-danger"></p>

        <div class="mt-auto pt-xxl">
          <button type="submit" :disabled="$store.auth.modal.busy" class="wa-btn w-full">
            <span x-show="$store.auth.modal.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            <span x-show="!$store.auth.modal.busy">Send OTP</span>
          </button>
          <p class="mt-md text-center text-caption text-ink-muted">
            By continuing you agree to the
            <a href="{{ route('site.page', 'terms') }}" class="font-semibold text-primary-dark hover:underline">Terms of Use</a> and
            <a href="{{ route('site.page', 'privacy') }}" class="font-semibold text-primary-dark hover:underline">Privacy Policy</a>.
          </p>
        </div>
      </form>
    </div>

    {{-- ── Step 2 of 2 · the code ────────────────────────────────────── --}}
    <div x-show="$store.auth.modal.stage === 'otp'" class="flex min-h-[100dvh] flex-col px-xl pb-xl pt-lg sm:min-h-0">
      <div class="flex items-center justify-between">
        <button type="button" @click="$store.auth.modal.stage = 'phone'; $store.auth.modal.error = ''" aria-label="Back"
                class="-ml-sm inline-flex h-10 w-10 items-center justify-center rounded-field text-ink hover:bg-surface-muted">
          @include('admin.partials.icon', ['name' => 'arrowLeft', 'class' => 'h-5 w-5'])
        </button>
        <button type="button" @click="Inthes.closeSignIn()" aria-label="Close"
                class="-mr-sm inline-flex h-10 w-10 items-center justify-center rounded-field text-ink-muted hover:bg-surface-muted hover:text-ink">
          @include('admin.partials.icon', ['name' => 'x', 'class' => 'h-5 w-5'])
        </button>
      </div>
      <p class="mt-lg text-kicker uppercase tracking-[1.2px] text-primary">Step 2 of 2</p>
      <h2 class="mt-sm text-h1 text-ink">Enter the Code</h2>
      <p class="mt-xs text-bodysm text-ink-secondary">
        Sent to <span class="font-semibold text-ink">+91 <span x-text="$store.auth.modal.phone"></span></span> ·
        <span x-text="$store.auth.modal.role === 'recruiter' ? 'Employer account' : 'Job seeker account'"></span> ·
        <button type="button" @click="$store.auth.modal.stage = 'phone'; $store.auth.modal.error = ''" class="font-semibold text-primary-dark hover:underline">Change</button>
      </p>

      <form @submit.prevent="Inthes.verifyOtp()" class="mt-xl flex flex-1 flex-col">
        {{-- Six boxes over one real input, so paste and SMS autofill still work. --}}
        <label class="relative block cursor-text" aria-label="6-digit code">
          <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                 x-model="$store.auth.modal.otp"
                 x-effect="$store.auth.modal.open && $store.auth.modal.stage === 'otp' && $nextTick(() => $el.focus())"
                 @input="$store.auth.modal.otp = $store.auth.modal.otp.replace(/\D/g, '').slice(0, 6); $store.auth.modal.error = ''; if ($store.auth.modal.otp.length === 6) setTimeout(() => Inthes.verifyOtp(), 250)"
                 class="absolute inset-0 z-10 h-full w-full cursor-text opacity-0" data-otp-input>
          <div class="grid grid-cols-6 gap-sm">
            <template x-for="i in 6" :key="i">
              <span class="flex h-[58px] items-center justify-center rounded-field border-chip text-h2 text-ink transition-colors"
                    :class="($store.auth.modal.otp.length === i - 1) ? 'border-primary bg-surface' : ($store.auth.modal.otp.length >= i ? 'border-primary/40 bg-primary-light' : 'border-transparent bg-surface-muted')"
                    x-text="$store.auth.modal.otp[i - 1] || ''"></span>
            </template>
          </div>
        </label>
        <p x-show="$store.auth.modal.error" x-text="$store.auth.modal.error" role="alert" class="mt-md rounded-field bg-danger-bg px-md py-sm text-bodysm text-danger"></p>
        <div class="mt-lg text-center">
          <button type="button" x-show="$store.auth.modal.resendIn <= 0" @click="Inthes.sendOtp()" class="text-btnghost font-semibold text-primary-dark hover:underline">Resend code</button>
          <span x-show="$store.auth.modal.resendIn > 0" class="text-caption text-ink-muted">Resend code in <span x-text="$store.auth.modal.resendIn"></span>s</span>
        </div>

        <div class="mt-auto pt-xxl">
          <button type="submit" :disabled="$store.auth.modal.otp.length !== 6 || $store.auth.modal.busy" class="wa-btn w-full">
            <span x-show="$store.auth.modal.busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-[18px] [&>svg]:w-[18px]"></span>
            <span x-show="!$store.auth.modal.busy">Verify &amp; continue</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
