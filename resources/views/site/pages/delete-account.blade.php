{{--
  /delete-account — the public page Google Play and the App Store ask for:
  how to delete an INTHES account and what that removes. Signing in (the
  site's usual phone + OTP popup) proves the account is yours; the delete
  itself is DELETE /api/v1/auth/account (App\Services\AccountDeletion).
--}}
@extends('site.layout')

@section('title', 'Delete your account — Inthes')
@section('description', 'How to delete your INTHES job seeker or employer account and the data stored with it.')

@section('content')
<section class="mx-auto max-w-[720px] px-page py-xxxl"
         x-data="{
           confirmText: '',
           busy: false,
           deleted: false,
           error: '',
           async remove() {
             if (this.busy || this.confirmText.trim() !== 'DELETE') return
             this.busy = true
             this.error = ''
             try {
               await Inthes.api('/auth/account', { method: 'DELETE', body: { confirm: 'DELETE' } })
               Inthes.signOut({ quiet: true })
               this.deleted = true
               window.scrollTo({ top: 0, behavior: 'smooth' })
             } catch (e) { this.error = e.message || 'Could not delete the account. Please try again.' }
             this.busy = false
           },
         }">
  <span class="text-kicker uppercase tracking-[1.2px] text-ink-secondary">Account</span>
  <h1 class="mt-xs text-h1 text-ink">Delete your INTHES account</h1>
  <p class="mt-sm text-body text-ink-secondary">
    You can delete your INTHES account and the data stored with it at any time, from the app or from this page.
  </p>

  {{-- Done --}}
  <template x-if="deleted">
    <div class="mt-xl rounded-card border-hair border-success/30 bg-success-bg p-lg" data-account-deleted>
      <p class="flex items-center gap-sm text-h5 text-success">
        <span x-html="ICONS.checkCircle" class="[&>svg]:h-5 [&>svg]:w-5"></span> Your account has been deleted
      </p>
      <p class="mt-xs text-bodysm text-ink-secondary">Your profile and its data have been removed. You can sign up again with the same mobile number at any time — it will be a new, empty account.</p>
    </div>
  </template>

  <div x-show="!deleted" class="mt-xl space-y-lg">
    {{-- What is deleted --}}
    <div class="wa-card !p-lg sm:!p-xl">
      <h2 class="text-h4 text-ink">What gets deleted</h2>
      <div class="mt-md grid gap-lg sm:grid-cols-2">
        <div>
          <p class="text-bodysm font-semibold text-ink">Job seeker account</p>
          <ul class="mt-xs list-disc space-y-[2px] pl-lg text-bodysm text-ink-secondary">
            <li>Your profile: name, contact details, address and location</li>
            <li>Education, experience, skills and languages</li>
            <li>Profile photo, resumes and intro video</li>
            <li>Applications, chats with employers, saved jobs and job alerts</li>
            <li>Notifications, plan and payment records</li>
          </ul>
        </div>
        <div>
          <p class="text-bodysm font-semibold text-ink">Employer account</p>
          <ul class="mt-xs list-disc space-y-[2px] pl-lg text-bodysm text-ink-secondary">
            <li>Your contact details</li>
            <li>Your companies, their logos and verification documents</li>
            <li>Your job posts and the applications and chats on them</li>
            <li>Job plans, credits, payment records and notifications</li>
          </ul>
        </div>
      </div>
      <p class="mt-lg text-caption text-ink-muted">
        Deletion is permanent and happens immediately — it cannot be undone. A job seeker account and an employer account on the same number are separate accounts: deleting one does not delete the other.
      </p>
    </div>

    {{-- In the app --}}
    <div class="wa-card !p-lg sm:!p-xl">
      <h2 class="text-h4 text-ink">Delete from the app</h2>
      <ol class="mt-sm list-decimal space-y-[2px] pl-lg text-bodysm text-ink-secondary">
        <li>Open the INTHES app and sign in with your mobile number.</li>
        <li>Go to <b>Profile</b> and open <b>Delete account</b>.</li>
        <li>Confirm, and the account is deleted.</li>
      </ol>
    </div>

    {{-- On the web --}}
    <div class="wa-card !p-lg sm:!p-xl">
      <h2 class="text-h4 text-ink">Delete here</h2>

      <template x-if="!$store.auth.signedIn">
        <div>
          <p class="mt-sm text-bodysm text-ink-secondary">Sign in with the mobile number of the account you want to delete — we'll send you a one-time code to prove it's yours.</p>
          <div class="mt-lg grid gap-sm sm:grid-cols-2">
            <button type="button" class="wa-btn-ghost" @click="Inthes.signIn('to delete your job seeker account', { role: 'candidate', lock: true })">Sign in as job seeker</button>
            <button type="button" class="wa-btn-ghost" @click="Inthes.signIn('to delete your employer account', { role: 'recruiter', lock: true })">Sign in as employer</button>
          </div>
        </div>
      </template>

      <template x-if="$store.auth.signedIn">
        <div>
          <p class="mt-sm text-bodysm text-ink">
            Signed in as the <b x-text="$store.auth.isEmployer ? 'employer' : 'job seeker'"></b> account
            <template x-if="$store.auth.phone"><span>on <b x-text="'+91 ' + $store.auth.phone"></b></span></template>.
          </p>
          <label class="mt-lg block">
            <span class="wa-label">Type DELETE to confirm</span>
            <input type="text" x-model="confirmText" autocomplete="off" placeholder="DELETE" class="wa-input" data-delete-confirm>
          </label>
          <p x-show="error" x-text="error" role="alert" class="mt-md rounded-field bg-danger-bg px-md py-sm text-bodysm text-danger"></p>
          <div class="mt-lg flex flex-col gap-sm sm:flex-row">
            <button type="button" @click="remove()" :disabled="busy || confirmText.trim() !== 'DELETE'"
                    class="inline-flex h-[50px] items-center justify-center gap-sm rounded-button bg-danger px-xl text-btn font-semibold text-white disabled:opacity-50" data-delete-account>
              <span x-show="busy" x-html="ICONS.loader" class="animate-spin [&>svg]:h-5 [&>svg]:w-5"></span>
              Delete my account permanently
            </button>
            <button type="button" @click="Inthes.signOut()" class="wa-btn-quiet h-[50px]">Sign out instead</button>
          </div>
        </div>
      </template>
    </div>

    <p class="text-caption text-ink-muted">
      Can't sign in? Write to us from the <a href="{{ route('site.page', 'contact') }}" class="font-semibold text-primary-dark hover:underline">Contact Us</a> page with your mobile number and we'll delete the account within 7 days.
      Read more in our <a href="{{ route('site.page', 'privacy') }}" class="font-semibold text-primary-dark hover:underline">Privacy Policy</a>.
    </p>
  </div>
</section>
@endsection
