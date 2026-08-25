<?php

namespace App\Enums;

use App\Models\CandidateProfile;

enum ProfileField: string
{
    case Name = 'name';
    case Qualification = 'qualification';
    case Experience = 'experience';
    case Skills = 'skills';
    case Location = 'location';
    case Specialization = 'specialization';
    case CertificationBls = 'certificationBls';
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
            self::Skills => filled($profile->skills),
            self::Location => filled($profile->location),
            self::Specialization => filled($profile->specialization),
            self::CertificationBls => in_array('BLS', $profile->certifications ?? [], true),
            self::Resume => filled($profile->resume_name),
            self::Gender => filled($profile->gender),
            self::Dob => filled($profile->dob),
            self::Address => filled($profile->address),
        };
    }
}
