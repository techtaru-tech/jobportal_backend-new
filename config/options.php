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

    /*
    | Salary filter chips (browse filter sheet)
    |
    | Deliberately separate from `salary_steps`: those are Post-a-Job picker
    | values and include '1L', which the client parses for a filter threshold
    | by pulling the leading digits — '1L' would read as ₹1,000. These are the
    | only values safe to filter on.
    */
    'salary_filters' => [
        '₹10K+',
        '₹20K+',
        '₹30K+',
        '₹50K+',
        '₹75K+',
    ],

    /*
    | Category → the skills a recruiter is offered once Post a Job's category
    | is picked. A category absent from here falls back to the flat `skills`
    | list above.
    */
    'skills_by_category' => [
        'Nurse' => [
            'Patient Care', 'ICU', 'Emergency Care', 'Ventilator Care',
            'Wound Dressing', 'Vaccination', 'OT', 'Post-Op Care',
        ],
        'Doctor' => [
            'OPD', 'Diagnosis', 'Emergency Care', 'Critical Care',
            'Clinical Documentation', 'Surgery Assistance', 'Patient Management',
        ],
        'Pharmacist' => [
            'Dispensing', 'Inventory Management', 'Prescription Review',
            'Drug Interaction Checks', 'Billing', 'Cold Chain Handling',
        ],
        'Lab Technician' => [
            'Phlebotomy', 'Sample Collection', 'Biochemistry', 'Haematology',
            'Microbiology', 'Report Generation', 'Quality Control',
        ],
        'Radiology Technician' => [
            'X-Ray', 'CT Scan', 'MRI', 'Ultrasound', 'Radiation Safety',
            'Patient Positioning',
        ],
        'Physiotherapist' => [
            'Physiotherapy', 'Rehabilitation', 'Exercise Therapy',
            'Electrotherapy', 'Post-Op Mobility', 'Pain Management',
        ],
        'Dietitian' => [
            'Diet Planning', 'Nutrition Counselling', 'Clinical Nutrition',
            'Patient Assessment', 'Therapeutic Diets',
        ],
        'Medical Officer' => [
            'OPD', 'Emergency Care', 'Patient Management', 'Diagnosis',
            'Clinical Documentation', 'Ward Rounds',
        ],
    ],

    /*
    | Clinical specialisations — the `specialization` Smart Apply field, which
    | a job asks for only when its `required_fields` name it. Distinct from the
    | specialisation attached to a single education entry.
    */
    'specializations' => [
        'Critical Care',
        'Emergency',
        'Paediatrics',
        'Cardiology',
        'Oncology',
        'Orthopaedics',
        'Neurology',
        'Obstetrics & Gynaecology',
        'X-Ray',
        'CT Scan',
        'MRI',
        'Ultrasound',
    ],

    /*
    | Designation suggestions for the candidate's work-experience form.
    | A suggestion shortlist, never a whitelist — the field accepts freeform.
    */
    'designations' => [
        'Staff Nurse',
        'Senior Staff Nurse',
        'ICU Nurse',
        'Nursing Supervisor',
        'Head Nurse',
        'Duty Doctor',
        'Medical Officer',
        'Resident Doctor',
        'Pharmacist',
        'Chief Pharmacist',
        'Lab Technician',
        'Senior Lab Technician',
        'Radiology Technician',
        'Physiotherapist',
        'Dietitian',
        'OT Technician',
    ],

    /*
    | Employer / institute suggestions, shared by the work-experience
    | ("organization") and education ("institute") forms. Freeform is accepted;
    | this only saves typing for the common cases.
    */
    'institutes' => [
        'Fortis Hospital',
        'Fortis Escorts',
        'Apollo Hospitals',
        'SMS Hospital',
        'AIIMS Jodhpur',
        'Narayana Hospital',
        'Manipal Hospital',
        'Metro Diagnostics',
        'Rajasthan University of Health Sciences',
        'MedPlus',
    ],

    /*
    | Hospital departments, for the candidate's work-experience form.
    | A suggestion shortlist like 'designations' — freeform is accepted.
    |
    | Served from here rather than from the app so a new department is a config
    | change, not an app release.
    */
    'departments' => [
        'ICU',
        'Emergency',
        'OPD',
        'OT',
        'Ward',
        'Pharmacy',
        'Lab',
        'Radiology',
        'Administration',
    ],

    /*
    | Genders offered on the candidate's personal-information form.
    |
    | Unlike most lists here this one IS closed — CandidateProfileController
    | validates `dob`'s sibling `gender` with Rule::in(). Keep the two in step:
    | anything added here has to be added to that rule, or the app will offer a
    | value the API then rejects.
    */
    'genders' => [
        'Male',
        'Female',
        'Other',
    ],

    /*
    | How far the education / certification year pickers reach.
    |
    | Rendered into a concrete list of years by ConfigController from the
    | server's clock, so it never goes stale the way a literal list of years
    | would every January. 'ahead' covers students graduating this session.
    */
    'passing_years' => [
        'ahead' => 1,
        'back' => 50,
    ],

    /*
    | Approximate city-centre coordinates.
    |
    | Distance-based recommendation reads a job's own latitude/longitude when it
    | has them, which only happens when the recruiter posted through the map
    | picker. This is the fallback for every manually-entered location, so a job
    | still participates in "near me" sorting instead of dropping out of it.
    */
    'city_coordinates' => [
        'Jaipur' => ['lat' => 26.9124, 'lng' => 75.7873],
        'Jodhpur' => ['lat' => 26.2389, 'lng' => 73.0243],
        'Udaipur' => ['lat' => 24.5854, 'lng' => 73.7125],
        'Kota' => ['lat' => 25.2138, 'lng' => 75.8648],
        'Ajmer' => ['lat' => 26.4499, 'lng' => 74.6399],
        'Bikaner' => ['lat' => 28.0229, 'lng' => 73.3119],
        'Alwar' => ['lat' => 27.5530, 'lng' => 76.6346],
        'Bharatpur' => ['lat' => 27.2152, 'lng' => 77.5030],
        'Delhi' => ['lat' => 28.7041, 'lng' => 77.1025],
        'Ahmedabad' => ['lat' => 23.0225, 'lng' => 72.5714],
        'Mumbai' => ['lat' => 19.0760, 'lng' => 72.8777],
        'Pune' => ['lat' => 18.5204, 'lng' => 73.8567],
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
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        // Rate limit: 3 sends per phone per 10 minutes. Overridable so local
        // dev can raise the cap while re-testing a login flow — production
        // keeps the §2.1 default by simply not setting the env vars.
        'max_sends' => (int) env('OTP_MAX_SENDS', 3),
        'send_window_minutes' => (int) env('OTP_SEND_WINDOW_MINUTES', 10),
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
