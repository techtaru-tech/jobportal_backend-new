<?php

namespace App\Http\Controllers\Api;

use App\Enums\SkillLevel;
use App\Http\Resources\CandidateProfileResource;
use App\Models\CandidateProfile;
use App\Support\ApiResponse;
use App\Support\Display;
use App\Support\FileRetention;
use App\Support\PrivateFiles;
use App\Support\ResumePdf;
use App\Support\VideoProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** §3 Candidate profile. */
class CandidateProfileController extends ApiController
{
    /** GET /candidate/profile (§3.1) */
    public function show(Request $request): JsonResponse
    {
        return $this->respond($this->profile($request));
    }

    /**
     * PATCH /candidate/profile (§3.2 + §3.3)
     *
     * One partial-update endpoint covers the personal-info screen, the home
     * location, and the Smart Apply fields; only the keys present in the body
     * are touched. Phone is deliberately not updatable here (§3.2).
     */
    public function update(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'gender' => ['sometimes', 'nullable', Rule::in(['Male', 'Female', 'Other'])],
            'dob' => ['sometimes', 'nullable', 'date', 'before:today'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Where the candidate lives — distinct from `location` (§3.3, §3.9),
            // which is where they want to work.
            'home_city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'home_pincode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'home_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'home_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],

            'qualification' => ['sometimes', 'nullable', 'string', 'max:120'],
            'experience' => ['sometimes', 'nullable', 'string', 'max:40'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:80'],
            'location' => ['sometimes', 'nullable', 'array'],
            'location.*' => ['string', 'max:80'],
            'specialization' => ['sometimes', 'nullable', 'array'],
            'specialization.*' => ['string', 'max:80'],
        ]);

        foreach (['skills', 'location', 'specialization'] as $list) {
            if (array_key_exists($list, $validated)) {
                $validated[$list] = Display::cleanList($validated[$list]);
            }
        }

        $profile->fill($validated)->save();

        return $this->respond($profile, 'Profile updated.');
    }

    /** PATCH /candidate/profile/preferences (§3.9) */
    public function updatePreferences(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'preferred_roles' => ['sometimes', 'nullable', 'array'],
            'preferred_roles.*' => ['string', 'max:80'],
            'preferred_job_types' => ['sometimes', 'nullable', 'array'],
            'preferred_job_types.*' => [Rule::in(config('options.job_types'))],
            'preferred_shifts' => ['sometimes', 'nullable', 'array'],
            'preferred_shifts.*' => [Rule::in(config('options.shifts'))],
            'expected_salary' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        foreach (['preferred_roles', 'preferred_job_types', 'preferred_shifts'] as $list) {
            if (array_key_exists($list, $validated)) {
                $validated[$list] = Display::cleanList($validated[$list]);
            }
        }

        $profile->fill($validated)->save();

        return $this->respond($profile, 'Preferences updated.');
    }

    /**
     * PUT /candidate/profile/skills (§3.6) — full replace.
     *
     * The §10.4 seed list is a suggestion shortlist, not a whitelist — any
     * non-empty string is accepted, de-duplicated case-insensitively.
     */
    public function updateSkills(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'skills' => ['present', 'array'],
            'skills.*' => ['string', 'max:80'],
            'skill_levels' => ['sometimes', 'array'],
            'skill_levels.*' => ['nullable', Rule::in(SkillLevel::values())],
        ]);

        $skills = $this->dedupeCaseInsensitive($validated['skills']);

        $profile->fill([
            'skills' => $skills,
            'skill_levels' => collect($validated['skill_levels'] ?? [])
                ->only($skills)
                ->filter(fn ($level) => filled($level))
                ->all(),
        ])->save();

        return $this->respond($profile, 'Skills updated.');
    }

    /** PUT /candidate/profile/certifications (§3.7) — full replace. */
    public function updateCertifications(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'certifications' => ['present', 'array'],
            'certifications.*' => ['string', 'max:40'],
            'certification_years' => ['sometimes', 'array'],
            'certification_years.*' => ['nullable', 'string', 'max:10'],
        ]);

        $certifications = Display::cleanList($validated['certifications']);

        $profile->fill([
            'certifications' => $certifications,
            // Drop year entries for certifications that are no longer held.
            'certification_years' => collect($validated['certification_years'] ?? [])
                ->only($certifications)
                ->filter(fn ($year) => filled($year))
                ->all(),
        ])->save();

        return $this->respond($profile, 'Certifications updated.');
    }

    /** PUT /candidate/profile/languages (§3.8) — full replace. */
    public function updateLanguages(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'languages' => ['present', 'array'],
            'languages.*' => ['string', 'max:40'],
            'language_levels' => ['sometimes', 'array'],
            'language_levels.*' => ['nullable', Rule::in(config('options.language_levels'))],
        ]);

        $languages = Display::cleanList($validated['languages']);

        $profile->fill([
            'languages' => $languages,
            'language_levels' => collect($validated['language_levels'] ?? [])
                ->only($languages)
                ->filter(fn ($level) => filled($level))
                ->all(),
        ])->save();

        return $this->respond($profile, 'Languages updated.');
    }

    /** PATCH /candidate/profile/about (§3.10) */
    public function updateAbout(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'about' => ['present', 'nullable', 'string', 'max:2000'],
        ]);

        $profile->fill($validated)->save();

        return $this->respond($profile, 'About updated.');
    }

    /** POST /candidate/profile/resume (§3.11) */
    public function uploadResume(Request $request): JsonResponse
    {
        $limits = config('options.uploads.resume');

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $limits['mimes']), 'max:'.$limits['max_kb']],
        ], [
            'file.mimes' => 'Upload your resume as a PDF or Word document.',
            'file.max' => 'Your resume must be smaller than 5 MB.',
        ]);

        $profile = $this->profile($request);
        $file = $request->file('file');
        $previousPath = $profile->resume_path;

        $path = $file->store("resumes/{$profile->user_id}", PrivateFiles::DISK);

        $profile->fill([
            'resume_name' => $file->getClientOriginalName(),
            'resume_path' => $path,
        ])->save();

        FileRetention::replacePrivate($previousPath);

        return ApiResponse::data([
            'resume' => $profile->resume_name,
            'resume_url' => PrivateFiles::url($path),
        ], 'Resume uploaded.');
    }

    /**
     * POST /candidate/profile/resume/generate (§3.11)
     *
     * Renders a plain resume from the profile for candidates with no file to
     * upload — the Smart Apply fallback.
     */
    public function generateResume(Request $request): JsonResponse
    {
        $profile = $this->profile($request)->load(['educations', 'workExperiences', 'user']);

        $name = $profile->name ?: 'Candidate';
        $fileName = str($name)->slug('_')->append('_Resume.pdf')->value();
        $path = "resumes/{$profile->user_id}/".uniqid('generated_').'.pdf';
        $previousPath = $profile->resume_path;

        PrivateFiles::disk()->put($path, ResumePdf::render($profile));

        $profile->fill([
            'resume_name' => $fileName,
            'resume_path' => $path,
        ])->save();

        FileRetention::replacePrivate($previousPath);

        return ApiResponse::data([
            'resume' => $fileName,
            'resume_url' => PrivateFiles::url($path),
        ], 'Resume created from your profile.');
    }

    /** POST /candidate/profile/photo (§3.12) */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $limits = config('options.uploads.photo');

        $request->validate([
            'file' => ['required', 'image', 'mimes:'.implode(',', $limits['mimes']), 'max:'.$limits['max_kb']],
        ], [
            'file.mimes' => 'Upload a JPG or PNG image.',
            'file.max' => 'Your photo must be smaller than 3 MB.',
        ]);

        $profile = $this->profile($request);
        $previousPath = $profile->photo_path;

        $path = $request->file('file')->store("photos/{$profile->user_id}", 'public');

        $profile->fill(['photo_path' => $path])->save();

        FileRetention::replacePublic($previousPath);

        return ApiResponse::data([
            'photo_url' => PrivateFiles::publicUrl($path),
        ], 'Photo updated.');
    }

    /**
     * POST /candidate/profile/intro-video (§3.13)
     *
     * The app enforces the ≤60s cap on both camera capture and gallery pick,
     * but some OEM camera apps ignore the requested cap, so duration is
     * re-checked here regardless of what the client already validated.
     */
    public function uploadIntroVideo(Request $request): JsonResponse
    {
        $limits = config('options.uploads.intro_video');

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $limits['mimes']), 'max:'.$limits['max_kb']],
        ], [
            'file.mimes' => 'Upload your intro video as an MP4 or MOV file.',
            'file.max' => 'Your intro video must be smaller than 50 MB.',
        ]);

        $file = $request->file('file');
        $seconds = VideoProbe::durationSeconds($file->getRealPath());

        if ($seconds !== null && $seconds > $limits['max_seconds']) {
            throw ValidationException::withMessages([
                'file' => ['Your intro video must be 60 seconds or shorter.'],
            ])->status(422);
        }

        $profile = $this->profile($request);
        $previousPath = $profile->intro_video_path;
        $previousThumbnail = $profile->intro_video_thumbnail_path;

        $path = $file->store("intro-videos/{$profile->user_id}", PrivateFiles::DISK);

        $profile->fill([
            'intro_video_path' => $path,
            // No thumbnail extraction dependency (ffmpeg) is assumed present —
            // see API_NOTES.md. The poster frame is left null until one is
            // wired up rather than shipping a broken image URL.
            'intro_video_thumbnail_path' => null,
            'intro_video_seconds' => $seconds !== null ? (int) round($seconds) : null,
        ])->save();

        FileRetention::replacePrivate($previousPath);
        FileRetention::replacePublic($previousThumbnail);

        return ApiResponse::data([
            'intro_video_url' => PrivateFiles::url($path),
        ], 'Intro video uploaded.');
    }

    /** DELETE /candidate/profile/intro-video (§3.13) */
    public function deleteIntroVideo(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $previousPath = $profile->intro_video_path;
        $previousThumbnail = $profile->intro_video_thumbnail_path;

        $profile->fill([
            'intro_video_path' => null,
            'intro_video_thumbnail_path' => null,
            'intro_video_seconds' => null,
        ])->save();

        FileRetention::replacePrivate($previousPath);
        FileRetention::replacePublic($previousThumbnail);

        return ApiResponse::message('Intro video removed.');
    }

    private function profile(Request $request): CandidateProfile
    {
        return $request->user()->profile();
    }

    private function dedupeCaseInsensitive(array $values): array
    {
        $seen = [];
        $result = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $key = mb_strtolower($value);

            if ($value !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $value;
            }
        }

        return $result;
    }

    private function respond(CandidateProfile $profile, ?string $message = null): JsonResponse
    {
        $profile->load(['educations', 'workExperiences', 'user']);

        return ApiResponse::data(new CandidateProfileResource($profile), $message);
    }
}
