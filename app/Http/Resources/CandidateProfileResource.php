<?php

namespace App\Http\Resources;

use App\Models\CandidateProfile;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flutter model: `CandidateProfile` (§3.1).
 *
 * This same shape is frozen into `applications.profile_snapshot` at apply time
 * and is what the recruiter reads back as an applicant's `profile` (§9.1) —
 * one frozen copy, not two. Signed URLs are re-minted on read rather than
 * stored in the snapshot, since a link captured months ago would be long dead.
 *
 * @mixin CandidateProfile
 */
class CandidateProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->user?->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'dob' => $this->dob?->format('Y-m-d'),
            // Derived, not stored — recalculated on every read so a birthday
            // that passes between two logins is never stale. Null rather
            // than a wrong number when there is no `dob` on record yet.
            'age' => $this->dob?->age,
            'address' => $this->address,
            'marital_status' => $this->marital_status,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,

            // Where they live — distinct from `location`, where they want to work.
            'home_city' => $this->home_city,
            'home_pincode' => $this->home_pincode,
            'home_latitude' => $this->home_latitude,
            'home_longitude' => $this->home_longitude,
            'home_state' => $this->home_state,
            'native_place' => $this->native_place,
            'nationality' => $this->nationality,
            'alternate_phone' => $this->alternate_phone,
            'whatsapp_number' => $this->whatsapp_number,

            'qualification' => $this->qualification,
            'experience' => $this->experience,
            'experience_min_years' => $this->experience_min_years,
            'experience_max_years' => $this->experience_max_years,
            // Informational cross-check, not what Smart Apply gates on — see
            // CandidateProfile::computedExperienceYears.
            'computed_experience_years' => $this->computedExperienceYears(),

            'location' => $this->location ?? [],
            'preferred_roles' => $this->preferred_roles ?? [],
            'preferred_job_types' => $this->preferred_job_types ?? [],
            'preferred_shifts' => $this->preferred_shifts ?? [],
            'preferred_organisation_types' => $this->preferred_organisation_types ?? [],
            'expected_salary' => $this->expected_salary,

            'currently_employed' => $this->currently_employed,
            'notice_period' => $this->notice_period,
            'last_working_date' => $this->last_working_date?->format('Y-m-d'),
            'immediate_joiner' => $this->immediate_joiner,
            'earliest_joining_date' => $this->earliest_joining_date?->format('Y-m-d'),
            'willing_to_relocate' => $this->willing_to_relocate,
            'has_vehicle' => $this->has_vehicle,
            'has_driving_licence' => $this->has_driving_licence,

            'languages' => $this->languages ?? [],
            'language_levels' => (object) ($this->language_levels ?? []),

            'about' => $this->about,
            'hobbies' => $this->hobbies ?? [],
            'skills' => $this->skills ?? [],
            'photo' => $this->hasPhoto(),
            'photo_url' => PrivateFiles::publicUrl($this->photo_path),

            'resume' => $this->resume_name,
            'resume_url' => PrivateFiles::url($this->resume_path),

            // Whether this candidate's own copy renders without the INTHES
            // mark — bought once, or included in their plan. The server
            // decides, so the app never has to work out what has been paid
            // for. See CandidateProfile::hasWatermarkFreeResume.
            'resume_watermark_free' => $this->hasWatermarkFreeResume(),

            'intro_video_url' => PrivateFiles::url($this->intro_video_path),
            'intro_video_thumbnail_url' => PrivateFiles::publicUrl($this->intro_video_thumbnail_path),
            'intro_video_seconds' => $this->intro_video_seconds,

            'educations' => EducationResource::collection($this->whenLoaded('educations')),
            'experiences' => WorkExperienceResource::collection($this->whenLoaded('workExperiences')),

            'profile_strength' => $this->profile_strength,
        ];
    }

    /**
     * The file paths a snapshot must remember, so its links can be re-minted
     * later and so a replaced file is not deleted out from under it.
     *
     * @return array<string, string|null>
     */
    public static function filePaths(CandidateProfile $profile): array
    {
        return [
            'resume_path' => $profile->resume_path,
            'photo_path' => $profile->photo_path,
            'intro_video_path' => $profile->intro_video_path,
            'intro_video_thumbnail_path' => $profile->intro_video_thumbnail_path,
        ];
    }

    /**
     * Re-mints the signed URLs inside a stored snapshot from the paths frozen
     * alongside it. Signed links expire, so they cannot live in the blob — but
     * resolving them against the candidate's *current* files would let a later
     * upload change what an employer already received (§9.1).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, string|null>  $paths
     */
    public static function refreshSnapshotUrls(array $snapshot, array $paths): array
    {
        $snapshot['resume_url'] = PrivateFiles::url($paths['resume_path'] ?? null);
        $snapshot['intro_video_url'] = PrivateFiles::url($paths['intro_video_path'] ?? null);
        $snapshot['intro_video_thumbnail_url'] = PrivateFiles::publicUrl($paths['intro_video_thumbnail_path'] ?? null);
        $snapshot['photo_url'] = PrivateFiles::publicUrl($paths['photo_path'] ?? null);

        return $snapshot;
    }
}
