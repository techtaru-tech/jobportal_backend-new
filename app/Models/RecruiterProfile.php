<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * §7.1 — the contact person candidates reach. One per account, shared across
 * every organisation the recruiter hires for.
 */
#[Fillable(['contact_person_name', 'contact_email', 'contact_phone'])]
class RecruiterProfile extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
