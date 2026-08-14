<?php

/*
|--------------------------------------------------------------------------
| Reference / configuration data (API_REQUIREMENTS.md §10)
|--------------------------------------------------------------------------
|
| These lists mirror the hardcoded MockDataProvider lists in the Flutter app.
| They are served verbatim by GET /api/v1/config/options so the app can switch
| off its local constants without any other change. Seed lists are open (a
| recruiter may type freeform qualifications/skills — see §8.1); the closed
| enums live in app/Enums instead.
|
*/

return [

    // §10.1 Job categories ("Browse by role")
    'categories' => [
        'Nurse',
        'Doctor',
        'Pharmacist',
        'Lab Technician',
        'Radiology Technician',
        'Physiotherapist',
        'Dietitian',
        'Medical Officer',
    ],

    // §10.2 Experience bands
    'experience_bands' => [
        'Fresher',
        '0–1 yr',
        '1–3 yrs',
        '3–5 yrs',
        '5–10 yrs',
        '10+ yrs',
    ],

    // §10.3 Qualifications (seed list — not a closed enum)
    'qualifications' => [
        'B.Sc Nursing',
        'GNM',
        'ANM',
        'MBBS',
        'BDS',
        'BAMS',
        'BHMS',
        'B.Pharm',
        'D.Pharm',
        'M.Pharm',
        'BPT',
        'MPT',
        'B.Sc MLT',
        'DMLT',
        'M.Sc Nursing',
    ],

    // §10.4 Common skills (seed list, not exhaustive)
    'skills' => [
        'ICU',
        'Patient Care',
        'Emergency Care',
        'OPD',
        'OT',
        'Ventilator Care',
        'Wound Dressing',
        'Vaccination',
        'Phlebotomy',
        'X-Ray',
        'CT Scan',
        'MRI',
        'Physiotherapy',
        'Counselling',
    ],

    // §10.5 Job types
    'job_types' => [
        'Full Time',
        'Part Time',
        'Contract',
        'Internship',
    ],

    // §10.6 Shifts
    'shifts' => [
        'Day',
        'Night',
        'Rotational',
        'Flexible',
    ],

    // §10.7 Cities — Rajasthan-first for launch, extensible to any Indian city.
    // Not validated as a closed list anywhere; this is picker seed data only.
    'cities' => [
        'Jaipur',
        'Jodhpur',
        'Udaipur',
        'Kota',
        'Ajmer',
        'Bikaner',
        'Alwar',
        'Bharatpur',
    ],

    // §10.8 Certifications (seed list)
    'certifications' => [
        'BLS',
        'ACLS',
        'PALS',
        'NRP',
        'CPR',
    ],

    // §10.9 Languages + proficiency levels
    'languages' => [
        'Hindi',
        'English',
        'Rajasthani',
        'Punjabi',
        'Gujarati',
        'Marwari',
    ],

    'language_levels' => [
        'Basic',
        'Intermediate',
        'Fluent',
        'Native',
    ],

    // §1.8 `skill_level` (§3.6)
    'skill_levels' => [
        'Beginner',
        'Intermediate',
        'Expert',
    ],

    // §1.8 `organisation_industry` (§7.2)
    'organisation_industries' => [
        'Hospital',
        'Clinic',
        'Diagnostic Lab',
        'Pharmacy',
        'Nursing Home',
        'Home Healthcare',
        'Medical College',
        'Staffing Agency',
        'Other',
    ],

    // §1.8 `organisation_size` (§7.2)
    'organisation_sizes' => [
        '1–10',
        '11–50',
        '51–200',
        '201–500',
        '500+',
    ],

    // §10.10 Salary steps (Post Job salary picker)
    'salary_steps' => [
        '10K',
        '15K',
        '20K',
        '25K',
        '30K',
        '35K',
        '40K',
        '50K',
        '60K',
        '75K',
        '1L',
    ],

    // §4.4 Curated typeahead synonyms, merged with live job titles.
    'search_dictionary' => [
        'Staff Nurse',
        'ICU Nurse',
        'Nurse',
        'Head Nurse',
        'Doctor',
        'Medical Officer',
        'Duty Doctor',
        'Pharmacist',
        'Lab Technician',
        'Radiology Technician',
        'Physiotherapist',
        'Dietitian',
        'OT Technician',
        'Emergency Nurse',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload limits (§3.11, §3.12, §3.13, §7.3)
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'resume' => [
            'max_kb' => 5 * 1024,
            'mimes' => ['pdf', 'doc', 'docx'],
        ],
        'photo' => [
            'max_kb' => 3 * 1024,
            'mimes' => ['jpg', 'jpeg', 'png'],
        ],
        'intro_video' => [
            'max_kb' => 50 * 1024,
            'max_seconds' => 60,
            'mimes' => ['mp4', 'mov', 'quicktime'],
        ],
        'organisation_logo' => [
            'max_kb' => 3 * 1024,
            'mimes' => ['jpg', 'jpeg', 'png'],
        ],
        'organisation_document' => [
            'max_kb' => 10 * 1024,
            'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP (§2.1)
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        // Rate limit: 3 sends per phone per 10 minutes.
        'max_sends' => 3,
        'send_window_minutes' => 10,
        // When true, the verify endpoint also accepts this code. Dev only.
        'debug_code' => env('OTP_DEBUG_CODE'),
        'expose_code_in_response' => (bool) env('OTP_EXPOSE_CODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens (§1.2) — 30 days with the app silently re-issuing on 401.
    |--------------------------------------------------------------------------
    */
    'token_ttl_days' => 30,

    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100,
    ],
];
