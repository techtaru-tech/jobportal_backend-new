<?php

namespace App\Models;

use App\Enums\OrganisationIndustry;
use App\Enums\OrganisationSize;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * §7.2 — an employer a recruiter posts jobs for. `verified` is server-owned:
 * see `markUnverified()` and the note on the controller.
 */
#[Fillable([
    'name', 'industry', 'size', 'address', 'website', 'gst_number', 'about',
    'logo_path', 'document_name', 'document_path',
])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'industry' => OrganisationIndustry::class,
            'size' => OrganisationSize::class,
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function hasDocument(): bool
    {
        return filled($this->document_path);
    }

    /**
     * §7.3 — re-uploading a document on an already-verified organisation resets
     * verification and re-queues the check.
     */
    public function markUnverified(): void
    {
        $this->forceFill(['verified' => false, 'verified_at' => null])->save();
    }

    /** Only an admin or an automated GST check may call this. */
    public function markVerified(): void
    {
        $this->forceFill(['verified' => true, 'verified_at' => now()])->save();
    }
}
