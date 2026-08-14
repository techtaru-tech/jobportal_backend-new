<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §1.8 collapsed the seven-stage pipeline to four statuses, and §6.1 now
     * states the same candidate may apply to the same job more than once.
     *
     * The snapshot_* columns are a queryable index over the immutable
     * `profile_snapshot` blob — the recruiter's applicant list filters and sorts
     * on what was actually submitted (§9.1), which no amount of joining against
     * the live profile can answer.
     */
    private const STATUS_MAP = [
        'submitted' => 'applied',
        'received' => 'applied',
        'underReview' => 'applied',
        'interview' => 'shortlisted',
        'shortlisted' => 'shortlisted',
        'selected' => 'selected',
        'rejected' => 'rejected',
    ];

    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // A candidate may apply to the same job more than once (§6.1).
            $table->dropUnique(['job_posting_id', 'user_id']);

            $table->timestamp('stage_updated_at')->nullable()->after('status');

            $table->string('snapshot_name')->nullable();
            $table->string('snapshot_designation')->nullable();
            $table->string('snapshot_qualification')->nullable();
            $table->string('snapshot_experience', 40)->nullable();
            $table->unsignedTinyInteger('snapshot_experience_min_years')->nullable();
            $table->unsignedTinyInteger('snapshot_profile_strength')->default(0);
            $table->json('snapshot_skills')->nullable();
            $table->json('snapshot_location')->nullable();

            // The storage paths the snapshot's files lived at when it was
            // frozen. Signed URLs expire, so they cannot be stored — but the
            // paths must be, or replacing a resume would retroactively change
            // what an employer already received (§9.1). Also the retention
            // record that stops a replaced file from being deleted while an
            // application still points at it.
            $table->json('snapshot_files')->nullable();

            $table->index(['job_posting_id', 'snapshot_profile_strength']);
        });

        foreach (self::STATUS_MAP as $from => $to) {
            if ($from === $to) {
                continue;
            }

            DB::table('applications')->where('status', $from)->update(['status' => $to]);
            DB::table('application_timeline_entries')->where('stage', $from)->update(['stage' => $to]);
        }

        DB::statement('update applications set stage_updated_at = applied_at where stage_updated_at is null');

        $this->collapseDuplicateTimelineEntries();
        $this->backfillSnapshotIndex();
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['job_posting_id', 'snapshot_profile_strength']);
            $table->dropColumn([
                'stage_updated_at', 'snapshot_name', 'snapshot_designation',
                'snapshot_qualification', 'snapshot_experience',
                'snapshot_experience_min_years', 'snapshot_profile_strength',
                'snapshot_skills', 'snapshot_location', 'snapshot_files',
            ]);
            $table->unique(['job_posting_id', 'user_id']);
        });
    }

    /** Remapping can collapse two old stages onto one; keep the earliest. */
    private function collapseDuplicateTimelineEntries(): void
    {
        $duplicates = DB::table('application_timeline_entries')
            ->select('application_id', 'stage', DB::raw('min(id) as keep_id'))
            ->groupBy('application_id', 'stage')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('application_timeline_entries')
                ->where('application_id', $duplicate->application_id)
                ->where('stage', $duplicate->stage)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }
    }

    /** @return array<string, string|null> */
    private static function currentFilePaths(int $userId): array
    {
        $profile = DB::table('candidate_profiles')->where('user_id', $userId)->first();

        return [
            'resume_path' => $profile->resume_path ?? null,
            'photo_path' => $profile->photo_path ?? null,
            'intro_video_path' => null,
            'intro_video_thumbnail_path' => null,
        ];
    }

    private function backfillSnapshotIndex(): void
    {
        DB::table('applications')->orderBy('id')->each(function (object $row) {
            $snapshot = json_decode($row->profile_snapshot ?? '{}', true) ?: [];

            DB::table('applications')->where('id', $row->id)->update([
                'snapshot_name' => $snapshot['name'] ?? null,
                'snapshot_designation' => $snapshot['experiences'][0]['designation'] ?? null,
                'snapshot_qualification' => $snapshot['qualification'] ?? null,
                'snapshot_experience' => $snapshot['experience'] ?? null,
                'snapshot_experience_min_years' => $snapshot['experience_min_years'] ?? null,
                'snapshot_profile_strength' => $snapshot['profile_strength'] ?? 0,
                'snapshot_skills' => json_encode($snapshot['skills'] ?? []),
                'snapshot_location' => json_encode($snapshot['location'] ?? []),
                // Pre-v2 snapshots carried no file paths; fall back to the
                // candidate's current ones, which is the best available answer.
                'snapshot_files' => json_encode(self::currentFilePaths($row->user_id)),
            ]);
        });
    }
};
