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

    public function isCandidate(): bool
    {
        return $this->role === UserRole::Candidate;
    }

    public function isRecruiter(): bool
    {
        return $this->role === UserRole::Recruiter;
    }

    /**
     * The name to show this user by.
     *
     * `users.name` is only ever populated by the seeder — OTP signup (§2.2)
     * asks for a phone and nothing else, so for every real account the name
     * lives on the profile the user actually filled in. Anything rendering a
     * person's name must go through here, or it renders an empty string.
     */
    public function displayName(string $fallback = ''): string
    {
        $name = $this->isRecruiter()
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
