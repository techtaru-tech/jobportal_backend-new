<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One account, one job.
 *
 * Reverses 2026_08_17 at the client's request: a job seeker's account applies
 * for jobs and an employer's account posts them. The same mobile number may
 * hold one of each, but they are separate accounts with their own sign-in,
 * registration and data — `unique(phone, role)` again.
 *
 * An account that has been doing both is split. It keeps the side it signed
 * up on (`users.role`); everything belonging to the other side moves to a new
 * account on the same, already-verified phone:
 *
 *  - employer side: recruiter profile, companies, job postings, job credits,
 *    recruiter payments/subscriptions/notifications;
 *  - job seeker side: candidate profile (its educations and experiences come
 *    with it), applications, saved jobs, job alerts, job-seeker
 *    payments/subscriptions/notifications.
 *
 * A side with nothing real on it is not split off — every account used to get
 * an empty candidate profile at sign-in, and that alone is not a job seeker.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index first: the split inserts a second account on the same phone,
        // which `unique(phone)` would refuse.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->unique(['phone', 'role']);
        });

        $this->splitDualAccounts();
    }

    public function down(): void
    {
        $duplicates = DB::table('users')->select('phone')->groupBy('phone')
            ->havingRaw('count(*) > 1')->pluck('phone');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot go back to one account per phone: these phones now hold a job seeker '
                .'and an employer account — '.$duplicates->implode(', ')
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone', 'role']);
            $table->unique('phone');
        });
    }

    /** Public so the split can be tested on its own. */
    public function splitDualAccounts(): void
    {
        DB::transaction(function () {
            foreach (DB::table('users')->orderBy('id')->get() as $user) {
                if ($user->role === 'candidate' && $this->hasEmployerSide($user->id)) {
                    $this->moveEmployerSide($user, $this->twin($user, 'recruiter'));
                }
                if ($user->role === 'recruiter' && $this->hasJobSeekerSide($user->id)) {
                    $this->moveJobSeekerSide($user, $this->twin($user, 'candidate'));
                }
            }
        });
    }

    private function hasEmployerSide(int $userId): bool
    {
        return DB::table('organisations')->where('user_id', $userId)->exists()
            || DB::table('job_postings')->where('user_id', $userId)->exists()
            || DB::table('job_credits')->where('user_id', $userId)->exists()
            || DB::table('payment_orders')->where('user_id', $userId)->where('audience', 'recruiter')->exists()
            || DB::table('subscriptions')->where('user_id', $userId)->where('audience', 'recruiter')->exists();
    }

    private function hasJobSeekerSide(int $userId): bool
    {
        $profileId = DB::table('candidate_profiles')->where('user_id', $userId)->value('id');

        return DB::table('applications')->where('user_id', $userId)->exists()
            || DB::table('saved_jobs')->where('user_id', $userId)->exists()
            || DB::table('job_alerts')->where('user_id', $userId)->exists()
            || DB::table('payment_orders')->where('user_id', $userId)->where('audience', 'jobSeeker')->exists()
            || DB::table('subscriptions')->where('user_id', $userId)->where('audience', 'jobSeeker')->exists()
            || ($profileId !== null && (
                DB::table('educations')->where('candidate_profile_id', $profileId)->exists()
                || DB::table('work_experiences')->where('candidate_profile_id', $profileId)->exists()
            ));
    }

    /** The other-role account on the same phone, created as already verified. */
    private function twin(object $user, string $role): int
    {
        $now = now();

        return DB::table('users')->insertGetId([
            'phone' => $user->phone,
            'role' => $role,
            'name' => $user->name,
            // `email` is unique; the original keeps it.
            'email' => null,
            'password' => $user->password,
            'phone_verified_at' => $user->phone_verified_at,
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function moveEmployerSide(object $from, int $to): void
    {
        foreach (['recruiter_profiles', 'organisations', 'job_postings', 'job_credits'] as $table) {
            DB::table($table)->where('user_id', $from->id)->update(['user_id' => $to]);
        }
        foreach (['payment_orders', 'subscriptions', 'app_notifications'] as $table) {
            DB::table($table)->where('user_id', $from->id)->where('audience', 'recruiter')
                ->update(['user_id' => $to]);
        }
    }

    private function moveJobSeekerSide(object $from, int $to): void
    {
        foreach (['candidate_profiles', 'applications', 'saved_jobs', 'job_alerts'] as $table) {
            DB::table($table)->where('user_id', $from->id)->update(['user_id' => $to]);
        }
        foreach (['payment_orders', 'subscriptions', 'app_notifications'] as $table) {
            DB::table($table)->where('user_id', $from->id)->where('audience', 'jobSeeker')
                ->update(['user_id' => $to]);
        }
    }
};
