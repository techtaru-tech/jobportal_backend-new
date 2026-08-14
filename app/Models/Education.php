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

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
