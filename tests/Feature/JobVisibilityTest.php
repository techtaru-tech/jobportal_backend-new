<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A brand-new organisation starts unverified (§8.1) — this is the gate that
 * makes that flag mean something. Before it existed, `verified` only drove
 * the "Verified employer" badge; a recruiter's very first posting under a
 * freshly-created employer was exactly as discoverable to a candidate as one
 * from an employer somebody had actually checked. These tests cover the three
 * surfaces that must agree once an employer isn't verified: the public
 * browse listing, direct fetch by id (both to the app and to the web share
 * page), and applying.
 */
class JobVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_from_an_unverified_employer_is_absent_from_the_public_listing(): void
    {
        $recruiter = User::factory()->recruiter()->create();
        $organisation = Organisation::factory()->for($recruiter, 'recruiter')->create(); // verified: false, the factory default
        $job = JobPosting::factory()->create(['organisation_id' => $organisation->id, 'title' => 'Pending Review Nurse']);

        $this->assertFalse($organisation->fresh()->verified);

        $titles = collect($this->getJson("{$this->api}/jobs")->assertOk()->json('data'))->pluck('title');

        $this->assertNotContains('Pending Review Nurse', $titles);
    }

    public function test_verifying_the_employer_makes_its_jobs_appear_without_touching_the_jobs(): void
    {
        $recruiter = User::factory()->recruiter()->create();
        $organisation = Organisation::factory()->for($recruiter, 'recruiter')->create();
        JobPosting::factory()->create(['organisation_id' => $organisation->id, 'title' => 'Newly Verified Nurse']);

        $this->getJson("{$this->api}/jobs")
            ->assertOk()
            ->assertJsonMissing(['title' => 'Newly Verified Nurse']);

        $organisation->markVerified();

        $titles = collect($this->getJson("{$this->api}/jobs")->assertOk()->json('data'))->pluck('title');
        $this->assertContains('Newly Verified Nurse', $titles);
    }

    public function test_unverifying_an_employer_immediately_hides_its_previously_visible_jobs(): void
    {
        $organisation = Organisation::factory()->verified()->create();
        JobPosting::factory()->create(['organisation_id' => $organisation->id, 'title' => 'Was Fine Yesterday']);

        $this->getJson("{$this->api}/jobs")->assertJsonPath('meta.total', 1);

        $organisation->markUnverified();

        $this->getJson("{$this->api}/jobs")->assertJsonPath('meta.total', 0);
    }

    public function test_a_job_from_an_unverified_employer_cannot_be_fetched_by_id_either(): void
    {
        $organisation = Organisation::factory()->create();
        $job = JobPosting::factory()->create(['organisation_id' => $organisation->id]);

        // Hiding it from search while leaving it fetchable by a shared/guessed
        // id would make verification cosmetic — the id is a small sequential
        // number (`j_{id}`), not a secret.
        $this->getJson("{$this->api}/jobs/j_{$job->id}")->assertNotFound();
    }

    public function test_a_candidate_cannot_apply_to_an_unverified_employers_job_by_id(): void
    {
        $organisation = Organisation::factory()->create();
        $job = JobPosting::factory()->create(['organisation_id' => $organisation->id]);
        $this->actingAsCandidate();

        // Both pre-apply endpoints — requirements() and store() — share the
        // same gate, so neither can be used to route around the other.
        $this->getJson("{$this->api}/applications/requirements/j_{$job->id}")->assertNotFound();
        $this->postJson("{$this->api}/applications", ['job_id' => "j_{$job->id}"])->assertNotFound();
    }

    public function test_a_shared_link_to_an_unverified_employers_job_shows_the_gone_page(): void
    {
        $organisation = Organisation::factory()->create();
        $job = JobPosting::factory()->create(['organisation_id' => $organisation->id, 'code' => 'MC-70001']);

        // The web share landing page (app/Http/Controllers/DeepLinkController)
        // and the API's own scope must agree — both read `isPubliclyVisible()`
        // / `scopePubliclyVisible()`, which is exactly what this guards.
        $this->get('/j/MC-70001')->assertNotFound();

        $organisation->markVerified();

        $this->get('/j/MC-70001')->assertOk();
    }

    /**
     * The one thing none of the above may ever touch: a recruiter's own view
     * of their own postings, regardless of their employer's verification
     * state — otherwise the recruiter has no way to see that their job is
     * live-but-pending rather than simply missing.
     */
    public function test_a_recruiters_own_unverified_job_still_shows_in_their_own_list(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $organisation = Organisation::factory()->for($recruiter, 'recruiter')->create();
        JobPosting::factory()->create(['organisation_id' => $organisation->id, 'user_id' => $recruiter->id, 'title' => 'Mine, Just Not Public Yet']);

        $titles = collect($this->getJson("{$this->api}/recruiter/jobs/mine")->assertOk()->json('data'))->pluck('title');
        $this->assertContains('Mine, Just Not Public Yet', $titles);
    }

    /**
     * A job posted before this migration existed (`organisation_id` null) is
     * grandfathered in as visible — the column is nullable exactly so those
     * rows aren't retroactively hidden by a rule that predates them. Every
     * job posted through the API today is required to name an organisation
     * (§8.1), so this path is legacy-only.
     */
    public function test_a_legacy_job_with_no_organisation_on_record_stays_visible(): void
    {
        $job = JobPosting::factory()->create(['organisation_id' => null, 'title' => 'Pre-Verification Era Posting']);

        $titles = collect($this->getJson("{$this->api}/jobs")->assertOk()->json('data'))->pluck('title');
        $this->assertContains('Pre-Verification Era Posting', $titles);
    }

    /*
    |--------------------------------------------------------------------------
    | Telling the recruiter
    |--------------------------------------------------------------------------
    |
    | Hiding an unverified employer's jobs is only half the feature. The other
    | half is the recruiter understanding *why* their postings are getting no
    | applicants — which needs more than a `verified` boolean, because a bare
    | false cannot say whose move it is next or what it is costing them.
    |
    */

    public function test_a_recruiter_only_ever_sees_their_own_organisations(): void
    {
        $mine = $this->actingAsRecruiter();
        Organisation::factory()->for($mine, 'recruiter')->create(['name' => 'Mine']);

        $someoneElse = User::factory()->recruiter()->create();
        Organisation::factory()->for($someoneElse, 'recruiter')->create(['name' => 'Theirs']);

        $names = collect($this->getJson("{$this->api}/recruiter/organisations")->assertOk()->json('data'))
            ->pluck('name');

        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
    }

    /**
     * `pending` and `no_document` are the same `verified = false` but entirely
     * different situations: one is waiting on an admin, the other is waiting on
     * the recruiter. Telling someone to sit tight when the ball is in their
     * court is how an employer stays unverified for a month.
     */
    public function test_the_organisation_payload_distinguishes_waiting_on_us_from_waiting_on_them(): void
    {
        $recruiter = $this->actingAsRecruiter();

        $noDocument = Organisation::factory()->for($recruiter, 'recruiter')->create([
            'name' => 'Nothing Uploaded',
            'document_path' => null,
        ]);
        $pending = Organisation::factory()->for($recruiter, 'recruiter')->create([
            'name' => 'Under Review',
            'document_path' => 'organisations/1/gst.pdf',
        ]);
        $verified = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create([
            'name' => 'Approved',
            'document_path' => 'organisations/2/gst.pdf',
        ]);

        $rows = collect($this->getJson("{$this->api}/recruiter/organisations")->assertOk()->json('data'))
            ->keyBy('name');

        $this->assertSame('no_document', $rows['Nothing Uploaded']['review_state']);
        $this->assertSame('pending', $rows['Under Review']['review_state']);
        $this->assertSame('verified', $rows['Approved']['review_state']);

        $this->assertNotNull($rows['Approved']['verified_at']);
        $this->assertNull($rows['Under Review']['verified_at']);

        // Unused, but asserted so the ids stay addressable from the app.
        $this->assertNotEmpty($noDocument->id.$pending->id.$verified->id);
    }

    /**
     * The number is the point: "pending review" is abstract, "2 of your jobs
     * are not being shown" is something a recruiter can act on.
     */
    public function test_the_organisation_payload_reports_how_many_postings_are_being_withheld(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $organisation = Organisation::factory()->for($recruiter, 'recruiter')->create([
            'document_path' => 'organisations/1/gst.pdf',
        ]);

        JobPosting::factory()->count(2)->create([
            'organisation_id' => $organisation->id,
            'user_id' => $recruiter->id,
            'posting_status' => JobPostingStatus::Active->value,
        ]);
        JobPosting::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $recruiter->id,
            'posting_status' => JobPostingStatus::Paused->value,
        ]);

        $row = $this->getJson("{$this->api}/recruiter/organisations")->assertOk()->json('data.0');

        // Only the live ones are being withheld — a paused posting is hidden
        // because the recruiter paused it, which is not this feature's doing.
        $this->assertSame(2, $row['hidden_postings']);
        $this->assertSame(3, $row['job_count']);
    }

    public function test_a_verified_employer_reports_nothing_hidden(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $organisation = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create();

        JobPosting::factory()->count(2)->create([
            'organisation_id' => $organisation->id,
            'user_id' => $recruiter->id,
            'posting_status' => JobPostingStatus::Active->value,
        ]);

        $row = $this->getJson("{$this->api}/recruiter/organisations")->assertOk()->json('data.0');

        $this->assertSame(0, $row['hidden_postings']);
    }

    /** §7.3 — re-uploading a document re-queues the check, and must say so. */
    public function test_re_uploading_a_document_returns_the_employer_to_pending(): void
    {
        $recruiter = $this->actingAsRecruiter();
        $organisation = Organisation::factory()->verified()->for($recruiter, 'recruiter')->create();

        Storage::fake('local');

        $this->postJson("{$this->api}/recruiter/organisations/org_{$organisation->id}/document", [
            'file' => UploadedFile::fake()->create('gst.pdf', 200, 'application/pdf'),
        ])->assertOk();

        $row = $this->getJson("{$this->api}/recruiter/organisations")->assertOk()->json('data.0');

        $this->assertSame('pending', $row['review_state']);
        $this->assertFalse($row['verified']);
    }
}
