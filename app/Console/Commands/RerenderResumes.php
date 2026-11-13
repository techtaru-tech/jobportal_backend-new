<?php

namespace App\Console\Commands;

use App\Models\CandidateProfile;
use App\Services\ResumeRenderer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-renders every stored resume in the current design.
 *
 * A resume is only re-rendered when its profile changes, so after the design
 * changes (the plain text PDF → the app's design, ResumePdf) the files
 * already on disk keep the old look until each candidate next edits their
 * profile. Run once after deploying a design change.
 */
class RerenderResumes extends Command
{
    protected $signature = 'resumes:rerender';

    protected $description = 'Re-render every stored resume PDF in the current design';

    public function handle(ResumeRenderer $renderer): int
    {
        $done = 0;
        $failed = 0;

        CandidateProfile::query()
            ->whereNotNull('resume_path')
            ->with(['educations', 'workExperiences', 'user'])
            ->chunkById(50, function ($profiles) use ($renderer, &$done, &$failed) {
                foreach ($profiles as $profile) {
                    try {
                        $renderer->generate($profile);
                        $done++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->warn("Profile {$profile->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Re-rendered {$done} resume".($done === 1 ? '' : 's').($failed ? ", {$failed} failed" : '').'.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
