<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * §3.5 — `designation`, `organization` and `department` are free text, not
 * enums. The app offers the §10 lists as tap-to-fill suggestions only; this
 * portal is not hospital-only, so an unlisted value is never rejected.
 */
#[Fillable([
    'designation', 'organization', 'department', 'city',
    'start_date', 'end_date', 'currently_working', 'description',
])]
class WorkExperience extends Model
{
    protected function casts(): array
    {
        return [
            'currently_working' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // §3.5 — end_date is ignored/blank while the candidate still works there.
        static::saving(function (self $experience) {
            if ($experience->currently_working) {
                $experience->end_date = 'Present';
            }
        });

        // An entry is part of the Experience section's completeness
        // (`CandidateProfile::sectionParts`), but adding one does not touch
        // the profile row — so without this the stored `profile_strength`
        // would still describe a profile with no work history on it. Mirrors
        // `Education::booted()`.
        $touchProfile = function (self $experience) {
            $experience->candidateProfile?->save();
        };

        static::created($touchProfile);
        static::deleted($touchProfile);
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /** "Mar 2023 – Present" — the derived display range (§13). */
    public function period(): string
    {
        return trim(collect([$this->start_date, $this->end_date])
            ->filter()
            ->implode(' – '));
    }
}
