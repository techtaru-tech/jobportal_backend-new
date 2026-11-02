<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_11_02 split: an account that was both applying and hiring keeps
 * its signup side, and the other side moves to a new account on the same
 * phone.
 */
class OneAccountPerRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function saveJob(User $user): int
    {
        return DB::table('saved_jobs')->insertGetId([
            'user_id' => $user->id,
            'job_posting_id' => JobPosting::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function split(): void
    {
        (require database_path('migrations/2026_11_02_000001_one_account_per_role.php'))->splitDualAccounts();
    }

    public function test_a_job_seeker_who_was_also_hiring_gets_an_employer_account(): void
    {
        $person = User::factory()->candidate()->create(['phone' => '9811111111']);
        $org = Organisation::factory()->for($person, 'recruiter')->create();
        $job = JobPosting::factory()->for($person, 'recruiter')->create(['organisation_id' => $org->id]);
        $saved = $this->saveJob($person);

        $this->split();

        $employer = User::where('phone', '9811111111')->where('role', 'recruiter')->sole();
        $this->assertSame($employer->id, $org->fresh()->user_id);
        $this->assertSame($employer->id, $job->fresh()->user_id);
        // The job seeker side stays where it was.
        $this->assertSame($person->id, DB::table('saved_jobs')->find($saved)->user_id);
        $this->assertNotNull($employer->phone_verified_at);
    }

    public function test_an_employer_who_was_also_applying_gets_a_job_seeker_account(): void
    {
        $person = User::factory()->recruiter()->create(['phone' => '9822222222']);
        $org = Organisation::factory()->for($person, 'recruiter')->create();
        $saved = $this->saveJob($person);

        $this->split();

        $seeker = User::where('phone', '9822222222')->where('role', 'candidate')->sole();
        $this->assertSame($seeker->id, DB::table('saved_jobs')->find($saved)->user_id);
        $this->assertSame($person->id, $org->fresh()->user_id);
    }

    public function test_an_account_that_only_did_one_thing_is_left_alone(): void
    {
        $person = User::factory()->recruiter()->create(['phone' => '9833333333']);
        $person->profile(); // the empty candidate profile every sign-in used to create
        Organisation::factory()->for($person, 'recruiter')->create();

        $this->split();

        $this->assertSame(1, User::where('phone', '9833333333')->count());
    }
}
