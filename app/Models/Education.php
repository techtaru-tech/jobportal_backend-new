<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['qualification', 'specialization', 'institute', 'year'])]
class Education extends Model
{
    /** "Education" is uncountable, so Eloquent would guess `education`. */
    protected $table = 'educations';

    protected static function booted(): void
    {
        // An entry is part of the Education section's completeness
        // (`CandidateProfile::sectionParts`), but adding one does not touch
        // the profile row — so without this the stored `profile_strength`
        // would still describe a profile with no education on it.
        //
        // `save()` is what re-runs the profile's own `saving` hook, which is
        // the single place strength is computed.
        $touchProfile = function (self $education) {
            $education->candidateProfile?->save();
        };

        static::created($touchProfile);
        static::deleted($touchProfile);
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
