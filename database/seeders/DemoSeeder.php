<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\ChatMessageStatus;
use App\Enums\ChatSender;
use App\Enums\InterviewType;
use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Enums\OrganisationIndustry;
use App\Enums\OrganisationSize;
use App\Enums\UserRole;
use App\Http\Resources\CandidateProfileResource;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Database\Seeder;

/**
 * Demo data that mirrors the app's MockDataProvider closely enough to develop
 * every screen against a real server. Idempotent — re-running updates in place.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $fortisRecruiter = $this->recruiter('9000000001', 'Priya Menon', 'hr@fortishealthcare.com');
        $apolloRecruiter = $this->recruiter('9000000002', 'Rakesh Gupta', 'hr@apollohospitals.com');

        $fortis = $this->organisation($fortisRecruiter, [
            'name' => 'Fortis Hospital',
            'industry' => OrganisationIndustry::Hospital->value,
            'size' => OrganisationSize::TwoHundredOneToFiveHundred->value,
            'address' => 'Malviya Nagar, Jaipur',
            'website' => 'https://fortishealthcare.com',
            'gst_number' => '08AABCU9603R1ZM',
            'about' => 'A 450-bed multi-speciality hospital.',
            'verified' => true,
        ]);

        $apollo = $this->organisation($apolloRecruiter, [
            'name' => 'Apollo Hospitals',
            'industry' => OrganisationIndustry::Hospital->value,
            'size' => OrganisationSize::FiveHundredPlus->value,
            'address' => 'Sardarpura, Jodhpur',
            'website' => 'https://apollohospitals.com',
            'gst_number' => '08AAACA8441L1ZC',
            'about' => 'A NABH-accredited hospital network.',
            // Deliberately left unverified so the recruiter's Organisations
            // screen has both states to render.
            'verified' => false,
        ]);

        $jobs = $this->jobs($fortisRecruiter, $fortis, $apolloRecruiter, $apollo);

        $candidates = [
            $this->candidate('9876543210', [
                'name' => 'Yash Saraswat',
                'email' => 'yash@example.com',
                'gender' => 'Male',
                'dob' => '1998-04-12',
                'address' => '204, Green Park, Jaipur',
                'home_city' => 'Jaipur',
                'home_pincode' => '302017',
                'home_latitude' => 26.9124,
                'home_longitude' => 75.7873,
                'qualification' => 'B.Sc Nursing',
                'experience' => '3–5 yrs',
                'skills' => ['ICU', 'Patient Care', 'Emergency Care'],
                'skill_levels' => ['ICU' => 'Expert', 'Patient Care' => 'Intermediate'],
                'location' => ['Jaipur'],
                'specialization' => [],
                'preferred_roles' => ['Nurse'],
                'preferred_job_types' => ['Full Time'],
                'preferred_shifts' => ['Day', 'Rotational'],
                'expected_salary' => '35K',
                'certifications' => ['BLS'],
                'certification_years' => ['BLS' => '2024'],
                'languages' => ['Hindi', 'English'],
                'language_levels' => ['Hindi' => 'Native', 'English' => 'Fluent'],
                'about' => 'Registered nurse with 4 years of ICU experience across multi-speciality hospitals.',
                'resume_name' => 'Yash_Saraswat_CV.pdf',
            ], [
                ['qualification' => 'B.Sc Nursing', 'specialization' => 'Critical Care', 'institute' => 'RUHS', 'year' => '2022'],
            ], [
                [
                    'designation' => 'Staff Nurse', 'organization' => 'Fortis Hospital', 'department' => 'ICU',
                    'city' => 'Jaipur', 'start_date' => 'Mar 2023', 'currently_working' => true,
                    'description' => "Monitored ventilated patients across rotational shifts.\nTrained two junior nurses on ICU protocol.",
                ],
            ]),

            $this->candidate('9812345678', [
                'name' => 'Riya Sharma',
                'email' => 'riya.sharma@example.com',
                'gender' => 'Female',
                'home_city' => 'Jaipur',
                'qualification' => 'B.Sc Nursing',
                'experience' => '3–5 yrs',
                'skills' => ['ICU', 'Patient Care'],
                'location' => ['Jaipur', 'Jodhpur'],
                'preferred_roles' => ['Nurse', 'Staff Nurse'],
                'preferred_job_types' => ['Full Time'],
                'preferred_shifts' => ['Rotational'],
                'expected_salary' => '30K',
                'certifications' => ['BLS'],
                'certification_years' => ['BLS' => '2023'],
                'languages' => ['Hindi', 'English'],
                'language_levels' => ['Hindi' => 'Fluent', 'English' => 'Intermediate'],
                'about' => 'Healthcare professional with 3–5 yrs of experience in ICU and Patient Care.',
                'resume_name' => 'Riya_Sharma_Resume.pdf',
            ], [
                ['qualification' => 'B.Sc Nursing', 'institute' => 'SMS Medical College', 'year' => '2021'],
            ], [
                [
                    'designation' => 'Staff Nurse', 'organization' => 'Apollo Hospitals', 'department' => 'Nurse',
                    'city' => 'Jaipur', 'start_date' => '2022', 'currently_working' => true,
                    'description' => 'Handled ICU responsibilities across shifts.',
                ],
            ]),

            $this->candidate('9799001122', [
                'name' => 'Aman Verma',
                'email' => 'aman.verma@example.com',
                'home_city' => 'Jodhpur',
                'qualification' => 'GNM',
                'experience' => '1–3 yrs',
                'skills' => ['OPD', 'Wound Dressing', 'Vaccination'],
                'location' => ['Jodhpur'],
                'preferred_shifts' => ['Day'],
                'expected_salary' => '22K',
                'languages' => ['Hindi'],
                'language_levels' => ['Hindi' => 'Native'],
                'about' => 'GNM nurse focused on outpatient care.',
                'resume_name' => 'Aman_Verma_CV.pdf',
            ], [
                ['qualification' => 'GNM', 'institute' => 'Jodhpur Nursing College', 'year' => '2023'],
            ], [
                [
                    'designation' => 'OPD Nurse', 'organization' => 'City Care Clinic', 'department' => 'OPD',
                    'city' => 'Jodhpur', 'start_date' => 'Jun 2023', 'currently_working' => true,
                ],
            ]),

            $this->candidate('9700112233', [
                'name' => 'Neha Joshi',
                'email' => 'neha.joshi@example.com',
                'home_city' => 'Jaipur',
                'qualification' => 'DMLT',
                'experience' => 'Fresher',
                'skills' => ['Phlebotomy'],
                'location' => ['Jaipur'],
                'expected_salary' => '15K',
                'languages' => ['Hindi', 'English'],
                'language_levels' => ['Hindi' => 'Native', 'English' => 'Basic'],
                'about' => 'Fresh DMLT graduate looking for a lab technician role.',
            ], [
                ['qualification' => 'DMLT', 'institute' => 'Rajasthan Paramedical Institute', 'year' => '2025'],
            ], []),
        ];

        $this->applications($jobs, $candidates);

        $this->command?->info('Seeded '.count($jobs).' jobs, '.count($candidates).' candidates.');
    }

    /** §7.1 — the contact person candidates reach, seeded onto the account. */
    private function recruiter(string $phone, string $contactName, string $contactEmail): User
    {
        $user = User::firstOrNew(['phone' => $phone, 'role' => UserRole::Recruiter->value]);

        $user->fill(['name' => $contactName])
            ->forceFill(['phone_verified_at' => now()])
            ->save();

        $user->contactProfile()->fill([
            'contact_person_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $phone,
        ])->save();

        return $user;
    }

    /** §7.2 — the employer this recruiter posts jobs for. */
    private function organisation(User $recruiter, array $attributes): Organisation
    {
        $organisation = $recruiter->organisations()->firstOrNew(['name' => $attributes['name']]);

        $organisation->fill($attributes);

        if ($attributes['verified'] ?? false) {
            $organisation->verified_at ??= now();
        }

        $organisation->save();

        return $organisation;
    }

    /** @return array<int, JobPosting> */
    private function jobs(User $fortisRecruiter, Organisation $fortis, User $apolloRecruiter, Organisation $apollo): array
    {
        $definitions = [
            [$fortisRecruiter, $fortis, [
                'role' => 'Nurse', 'title' => 'Staff Nurse',
                'organisation_note' => 'Multi-speciality hospital, 450 beds', 'city' => 'Jaipur',
                'salary_min' => 25000, 'salary_max' => 40000, 'experience' => '3–5 yrs',
                'type' => 'Full Time', 'shift' => 'Rotational',
                'qualifications' => ['B.Sc Nursing', 'GNM'],
                'skills' => ['ICU', 'Patient Care', 'Emergency Care'],
                'duties' => ['Monitor patient vitals every 2 hours', 'Administer medication per physician orders', 'Maintain accurate patient records'],
                'benefits' => ['PF', 'Health insurance', 'Night shift allowance'],
                'about' => "We're looking for a compassionate, detail-oriented staff nurse to join our critical care team.",
                'required_fields' => ['qualification', 'experience', 'location', 'resume'],
            ]],
            [$fortisRecruiter, $fortis, [
                'role' => 'Nurse', 'title' => 'ICU Nurse',
                'organisation_note' => 'Multi-speciality hospital, 450 beds', 'city' => 'Jaipur',
                'salary_min' => 30000, 'salary_max' => 50000, 'experience' => '3–5 yrs',
                'type' => 'Full Time', 'shift' => 'Night',
                'qualifications' => ['B.Sc Nursing'], 'skills' => ['ICU', 'Ventilator Care'],
                'duties' => ['Manage ventilated patients', 'Coordinate with intensivists on rounds'],
                'benefits' => ['PF', 'Health insurance'],
                'about' => 'Night-shift ICU nursing role in a 24-bed critical care unit.',
                'required_fields' => ['qualification', 'experience', 'skills', 'certificationBls', 'resume'],
            ]],
            [$apolloRecruiter, $apollo, [
                'role' => 'Nurse', 'title' => 'Staff Nurse',
                'organisation_note' => 'NABH accredited, 300 beds', 'city' => 'Jodhpur',
                'salary_min' => 22000, 'salary_max' => 32000, 'experience' => '1–3 yrs',
                'type' => 'Full Time', 'shift' => 'Day',
                'qualifications' => ['GNM', 'B.Sc Nursing'], 'skills' => ['OPD', 'Patient Care'],
                'duties' => ['Assist physicians during OPD hours', 'Handle patient intake and triage'],
                'benefits' => ['PF', 'Subsidised meals'],
                'about' => 'Day-shift ward nursing role with a strong training programme.',
                'required_fields' => ['qualification', 'experience', 'location'],
            ]],
            [$apolloRecruiter, $apollo, [
                'role' => 'Doctor', 'title' => 'Duty Doctor',
                'city' => 'Jaipur', 'salary_min' => 60000, 'salary_max' => 90000, 'experience' => '1–3 yrs',
                'type' => 'Full Time', 'shift' => 'Rotational',
                'qualifications' => ['MBBS'], 'skills' => ['Emergency Care'],
                'duties' => ['Cover casualty and emergency admissions', 'Stabilise and refer critical cases'],
                'benefits' => ['PF', 'Health insurance', 'Accommodation'],
                'about' => 'Resident medical officer role covering casualty.',
                'required_fields' => ['qualification', 'experience', 'resume'],
            ]],
            [$fortisRecruiter, $fortis, [
                'role' => 'Lab Technician', 'title' => 'Lab Technician',
                'city' => 'Jaipur', 'salary_min' => 15000, 'salary_max' => 24000, 'experience' => 'Fresher',
                'type' => 'Full Time', 'shift' => 'Day',
                'qualifications' => ['DMLT', 'B.Sc MLT'], 'skills' => ['Phlebotomy'],
                'duties' => ['Collect and process samples', 'Maintain lab equipment logs'],
                'benefits' => ['PF'],
                'about' => 'Entry-level pathology lab role — freshers welcome.',
                'required_fields' => ['qualification', 'location'],
            ]],
            [$apolloRecruiter, $apollo, [
                'role' => 'Pharmacist', 'title' => 'Pharmacist',
                'city' => 'Udaipur', 'salary_min' => 18000, 'salary_max' => 28000, 'experience' => '1–3 yrs',
                'type' => 'Full Time', 'shift' => 'Rotational',
                'qualifications' => ['B.Pharm', 'D.Pharm'], 'skills' => [],
                'duties' => ['Dispense prescriptions', 'Maintain controlled-drug registers'],
                'benefits' => ['PF', 'Health insurance'],
                'about' => 'In-house pharmacy role across rotating shifts.',
                'required_fields' => ['qualification', 'experience'],
            ]],
            [$fortisRecruiter, $fortis, [
                'role' => 'Physiotherapist', 'title' => 'Physiotherapist',
                'city' => 'Jaipur', 'salary_min' => 25000, 'salary_max' => 38000, 'experience' => '1–3 yrs',
                'type' => 'Part Time', 'shift' => 'Flexible',
                'qualifications' => ['BPT', 'MPT'], 'skills' => ['Physiotherapy'],
                'duties' => ['Run post-operative rehab sessions'],
                'benefits' => ['Flexible hours'],
                'about' => 'Part-time rehabilitation role, three days a week.',
                'required_fields' => ['qualification', 'experience', 'specialization'],
            ]],
            [$apolloRecruiter, $apollo, [
                'role' => 'Radiology Technician', 'title' => 'CT Technician',
                'city' => 'Jaipur', 'salary_min' => 28000, 'salary_max' => 42000, 'experience' => '3–5 yrs',
                'type' => 'Full Time', 'shift' => 'Rotational',
                'qualifications' => ['B.Sc MLT'], 'skills' => ['CT Scan', 'X-Ray'],
                'duties' => ['Operate CT and X-ray equipment', 'Follow radiation safety protocol'],
                'benefits' => ['PF', 'Health insurance'],
                'about' => 'Imaging department role covering CT and plain radiography.',
                'required_fields' => ['qualification', 'experience', 'skills'],
            ]],
        ];

        $jobs = [];

        foreach ($definitions as $index => [$recruiter, $organisation, $attributes]) {
            $job = JobPosting::firstOrNew([
                'user_id' => $recruiter->id,
                'organisation_id' => $organisation->id,
                'title' => $attributes['title'],
                'city' => $attributes['city'],
            ]);

            $job->fill($attributes + ['organisation' => $organisation->name]);
            $job->posted_at ??= now()->subDays($index + 1);
            $job->save();

            $jobs[] = $job;
        }

        // One paused and one closed posting so the recruiter's Job Management
        // screen has every state to render.
        $jobs[6]->forceFill(['posting_status' => 'paused'])->save();
        $jobs[7]->forceFill(['posting_status' => 'closed'])->save();

        return $jobs;
    }

    private function candidate(string $phone, array $attributes, array $educations, array $experiences): User
    {
        $user = User::firstOrNew(['phone' => $phone, 'role' => UserRole::Candidate->value]);

        $user->fill(['name' => $attributes['name'] ?? null])
            ->forceFill(['phone_verified_at' => now()])
            ->save();

        $profile = $user->profile();
        $profile->fill($attributes)->save();

        $profile->educations()->delete();
        $profile->workExperiences()->delete();

        foreach ($educations as $education) {
            $profile->educations()->create($education);
        }

        foreach ($experiences as $experience) {
            $profile->workExperiences()->create($experience);
        }

        return $user->fresh();
    }

    /**
     * @param  array<int, JobPosting>  $jobs
     * @param  array<int, User>  $candidates
     */
    private function applications(array $jobs, array $candidates): void
    {
        $notifier = app(Notifier::class);

        $plan = [
            // [job index, candidate index, status, days ago]
            [0, 0, ApplicationStatus::Applied, 7],
            [0, 1, ApplicationStatus::Shortlisted, 6],
            [0, 2, ApplicationStatus::Applied, 2],
            [1, 1, ApplicationStatus::Shortlisted, 5],
            [2, 2, ApplicationStatus::Applied, 4],
            [4, 3, ApplicationStatus::Rejected, 9],
        ];

        foreach ($plan as [$jobIndex, $candidateIndex, $status, $daysAgo]) {
            $job = $jobs[$jobIndex];
            $candidate = $candidates[$candidateIndex];

            if ($job->applications()->where('user_id', $candidate->id)->exists()) {
                continue;
            }

            $profile = $candidate->profile()->load(['educations', 'workExperiences', 'user']);
            $appliedAt = now()->subDays($daysAgo);

            $application = Application::create([
                'reference' => Application::mintReference($job),
                'job_posting_id' => $job->id,
                'user_id' => $candidate->id,
                'status' => $status,
                'applied_at' => $appliedAt,
                'stage_updated_at' => $appliedAt,
                'profile_snapshot' => (new CandidateProfileResource($profile))->resolve(),
            ]);

            $application->indexSnapshot(CandidateProfileResource::filePaths($profile));
            $application->save();

            // Walk the pipeline up to the current status so the Track screen has
            // a believable timeline.
            $reached = collect(ApplicationStatus::pipeline())
                ->takeWhile(fn (ApplicationStatus $stage) => $stage !== $status)
                ->push($status);

            foreach ($reached as $offset => $stage) {
                $at = $appliedAt->copy()->addHours($offset * 12);
                $application->recordStage($stage, $at);
                $application->forceFill(['stage_updated_at' => $at])->save();
            }

            $notifier->applicationSubmitted($application->fresh(['jobPosting', 'candidate']));

            if ($status !== ApplicationStatus::Applied) {
                $notifier->applicationStatusChanged($application->fresh(['jobPosting', 'candidate']), $status);
            }
        }

        $this->interviewAndChat($jobs[1]);
    }

    private function interviewAndChat(JobPosting $job): void
    {
        $application = $job->applications()->where('status', ApplicationStatus::Shortlisted)->first();

        if (! $application) {
            return;
        }

        $application->interview()->updateOrCreate([], [
            'date' => now()->addDays(6)->format('Y-m-d'),
            'time' => '11:00 AM',
            'type' => InterviewType::Online->value,
            'location_or_link' => 'https://meet.google.com/abc-defg-hij',
            'notes' => 'Bring original certificates if in-person.',
        ]);

        $conversation = $application->conversation()->firstOrCreate([]);

        if ($conversation->messages()->exists()) {
            return;
        }

        $thread = [
            [ChatSender::Recruiter, 'Hi! Thanks for applying to the ICU Nurse role.', 3],
            [ChatSender::Candidate, 'Thank you! Happy to share anything else you need.', 2],
            [ChatSender::Recruiter, 'Could you join a short video call this week?', 1],
        ];

        foreach ($thread as [$sender, $text, $daysAgo]) {
            $conversation->messages()->create([
                'sender' => $sender->value,
                'text' => $text,
                'sent_at' => now()->subDays($daysAgo),
                'status' => ChatMessageStatus::Read->value,
            ]);
        }

        $conversation->forceFill(['last_message_at' => now()->subDay()])->save();

        app(Notifier::class)->create(
            $application->candidate,
            NotificationAudience::JobSeeker,
            'You have a new message from '.$job->organisation.'.',
            NotificationType::NewMessage,
            ['application_id' => $application->id, 'conversation_id' => $conversation->id],
        );
    }
}
