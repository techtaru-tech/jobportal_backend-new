<?php

namespace App\Enums;

use App\Models\CandidateProfile;

/**
 * The fields a job can refuse an application without.
 *
 * `skills` and `specialization` used to be here. Skills is gone from the
 * product. Specialization is still a profile section but an **optional** one,
 * and an optional section cannot also be a thing an application is refused
 * without — the app has no Create profile step for it, so a job demanding it
 * could only produce a flow that never unblocks.
 *
 * A posting whose stored `required_fields` still lists either is not an
 * error: the value simply no longer resolves, and the gate ignores it.
 */
enum ProfileField: string
{
    case Name = 'name';
    case Qualification = 'qualification';
    case Experience = 'experience';
    case Location = 'location';
    case Resume = 'resume';
    case Gender = 'gender';
    case Dob = 'dob';
    case Address = 'address';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * §3.2 personal info — required before applying to *any* job, regardless
     * of what that job's own `required_fields` (§4.1) configures. A candidate
     * must say who they are before Smart Apply starts asking what they can do.
     *
     * @return list<self>
     */
    public static function alwaysRequired(): array
    {
        return [self::Name, self::Gender, self::Dob, self::Address];
    }

    /**
     * Whether a candidate profile already satisfies this requirement — the
     * Smart Apply gate. A field that is satisfied is never asked for.
     */
    public function isSatisfiedBy(CandidateProfile $profile): bool
    {
        return match ($this) {
            self::Name => filled($profile->name),
            self::Qualification => filled($profile->qualification),
            self::Experience => filled($profile->experience),
            self::Location => filled($profile->location),
            self::Resume => filled($profile->resume_name),
            self::Gender => filled($profile->gender),
            self::Dob => filled($profile->dob),
            self::Address => filled($profile->address),
        };
    }
}
