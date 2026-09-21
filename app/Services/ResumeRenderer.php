<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Support\FileRetention;
use App\Support\PrivateFiles;
use App\Support\ResumePdf;

/**
 * Keeps the stored resume in step with the profile it is rendered from.
 *
 * The resume used to be built by a button. That put the candidate in charge
 * of something they had no way to judge: the document a recruiter opens was
 * whatever the profile looked like the last time somebody remembered to tap
 * "Rebuild", and every profile edit after that silently made it wrong.
 *
 * So it is maintained instead of requested — [refresh] runs after the writes
 * that change what a resume prints, and the button is gone from the app.
 */
class ResumeRenderer
{
    /**
     * Re-entrancy guard.
     *
     * [generate] saves the profile to store the new path, which fires the
     * same model event that called us. Without this the first render would
     * recurse until the stack gave out.
     */
    private static bool $rendering = false;

    /**
     * Renders the resume and stores it, replacing any previous file.
     *
     * @return array{0: string, 1: string} file name, signed URL
     */
    public function generate(CandidateProfile $profile): array
    {
        $profile->loadMissing(['educations', 'workExperiences', 'user']);

        $name = $profile->name ?: 'Candidate';
        $fileName = str($name)->slug('_')->append('_Resume.pdf')->value();
        $path = "resumes/{$profile->user_id}/".uniqid('generated_').'.pdf';
        $previousPath = $profile->resume_path;

        PrivateFiles::disk()->put($path, ResumePdf::render($profile));

        self::$rendering = true;
        try {
            $profile->fill([
                'resume_name' => $fileName,
                'resume_path' => $path,
            ])->save();
        } finally {
            self::$rendering = false;
        }

        FileRetention::replacePrivate($previousPath);

        return [$fileName, PrivateFiles::url($path)];
    }

    /**
     * Brings the stored resume up to date after a profile change.
     *
     * Creates one where there is none and the profile can now fill it, which
     * is what makes a resume appear for somebody who skipped Create profile
     * and filled their details in later. Does nothing at all for a profile
     * too sparse to print — a PDF holding only a mobile number is worse than
     * no PDF, because it reads to the candidate as a finished resume.
     */
    public function refresh(CandidateProfile $profile): void
    {
        if (self::$rendering) {
            return;
        }

        if (! $profile->canBuildResume()) {
            return;
        }

        $this->generate($profile);
    }
}
