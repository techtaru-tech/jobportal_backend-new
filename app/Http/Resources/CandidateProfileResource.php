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
            'address' => $this->address,

            // Where they live — distinct from `location`, where they want to work.
            'home_city' => $this->home_city,
            'home_pincode' => $this->home_pincode,
            'home_latitude' => $this->home_latitude,
            'home_longitude' => $this->home_longitude,

            'qualification' => $this->qualification,
            'experience' => $this->experience,
            'experience_min_years' => $this->experience_min_years,
            'experience_max_years' => $this->experience_max_years,
            'skills' => $this->skills ?? [],
            'skill_levels' => (object) ($this->skill_levels ?? []),
            'specialization' => $this->specialization ?? [],

            'location' => $this->location ?? [],
            'preferred_roles' => $this->preferred_roles ?? [],
            'preferred_job_types' => $this->preferred_job_types ?? [],
            'preferred_shifts' => $this->preferred_shifts ?? [],
            'expected_salary' => $this->expected_salary,

            'certifications' => $this->certifications ?? [],
            'certification_years' => (object) ($this->certification_years ?? []),
            'languages' => $this->languages ?? [],
            'language_levels' => (object) ($this->language_levels ?? []),

            'about' => $this->about,
            'photo' => $this->hasPhoto(),
            'photo_url' => PrivateFiles::publicUrl($this->photo_path),

            'resume' => $this->resume_name,
            'resume_url' => PrivateFiles::url($this->resume_path),

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
