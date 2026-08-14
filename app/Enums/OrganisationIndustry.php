<?php

namespace App\Enums;

/** §1.8 `organisation_industry` (§7.2). */
enum OrganisationIndustry: string
{
    case Hospital = 'Hospital';
    case Clinic = 'Clinic';
    case DiagnosticLab = 'Diagnostic Lab';
    case Pharmacy = 'Pharmacy';
    case NursingHome = 'Nursing Home';
    case HomeHealthcare = 'Home Healthcare';
    case MedicalCollege = 'Medical College';
    case StaffingAgency = 'Staffing Agency';
    case Other = 'Other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
