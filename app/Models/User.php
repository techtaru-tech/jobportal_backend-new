<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['phone', 'role', 'name', 'email', 'phone_verified_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function recruiterProfile(): HasOne
    {
        return $this->hasOne(RecruiterProfile::class);
    }

    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    /**
     * Every app install that has registered for push, on any platform.
     * Cascade-deletes with the account (see the migration), so a closed
     * account never leaves a token FCM would still try to reach.
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * At most one row per audience (unique on `user_id, audience`), so this is
     * a `hasMany` of at most two: one account can be a free job seeker and a
     * paying recruiter at the same time, and neither says anything about the
     * other. `SubscriptionService` reads a single side directly; this exists
     * for the places that need both at once.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The side this account signed up on.
     *
     * A **default**, not a permission. One account holds both sides of the
     * marketplace — the same person posts jobs and applies for them — so
     * nothing gates on this; it only decides which tab the app opens on for a
     * brand-new account. Anything that needs to know which side a user is
     * acting as must read it from the thing being acted on (who owns the job,
     * who owns the application) or from the mode the app passes in.
     */
    public function signedUpAs(): UserRole
    {
        return $this->role;
    }

    /**
     * The name to show this user by, on the given side of the marketplace.
     *
     * `users.name` is only ever populated by the seeder — OTP signup (§2.2)
     * asks for a phone and nothing else, so for every real account the name
     * lives on the profile the user actually filled in. Anything rendering a
     * person's name must go through here, or it renders an empty string.
     *
     * The side is a parameter because it is no longer derivable from the
     * account: the same user is "Dr. Yash Saraswat" to a recruiter reading
     * their application and "Sunrise Multispecialty" to a candidate reading
     * their job posting.
     */
    public function displayName(string $fallback = '', UserRole $as = UserRole::Candidate): string
    {
        $name = $as === UserRole::Recruiter
            ? $this->recruiterProfile?->contact_person_name
            : $this->candidateProfile?->name;

        return (string) (filled($name) ? $name : (filled($this->name) ? $this->name : $fallback));
    }

    /** Candidates always have a profile row; create it lazily on first touch. */
    public function profile(): CandidateProfile
    {
        return $this->candidateProfile()->firstOrCreate([], [
            'name' => $this->name,
            'email' => $this->email,
        ]);
    }

    /** Recruiters likewise — the §7.1 contact card, seeded from the account. */
    public function contactProfile(): RecruiterProfile
    {
        return $this->recruiterProfile()->firstOrCreate([], [
            'contact_person_name' => $this->name,
            'contact_email' => $this->email,
            'contact_phone' => $this->phone,
        ]);
    }
}
