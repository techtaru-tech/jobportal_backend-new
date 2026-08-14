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

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
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
        };
    }
}
