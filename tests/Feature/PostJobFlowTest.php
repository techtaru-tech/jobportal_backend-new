<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\AppNotification;
use App\Models\JobCredit;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reworked Post a Job flow: Job details → Candidate requirements →
 * Company details (the post is saved here) → Choose plan → pay → under
 * review → live once the business document is verified.
 */
class PostJobFlowTest extends TestCase
{
    use RefreshDatabase;

    /** `$freePosts` 0 by default: most tests here are about paying. */
    private function recruiterWithOrg(bool $verified = false, bool $withDocument = true, int $freePosts = 0): array
    {
        $this->setPlan('recruiter_free_jobs', $freePosts);
        $recruiter = $this->actingAsRecruiter();
        $factory = Organisation::factory()->for($recruiter, 'recruiter');
        if ($verified) {
            $factory = $factory->verified();
        }
        $organisation = $factory->create([
            'name' => 'Veldx Labs Pvt Ltd',
            'document_path' => $withDocument ? 'organisations/doc.pdf' : null,
        ]);

        return [$recruiter, $organisation];
    }

    private function payload(Organisation $organisation, array $overrides = []): array
    {
        return array_merge([
            'save_as_draft' => true,
            'role' => 'Lab Technician',
            'title' => 'Lab Technician',
            'organisation_id' => "org_{$organisation->id}",
            'openings' => 10,
            'salary_min' => 10000,
            'salary_max' => 20000,
            'shift' => 'Day',
            'type' => 'Full Time',
            'state' => 'Rajasthan',
            'city' => 'Jaipur',
            'locality' => 'Malviya Nagar',
            'charges_fee' => false,
            'min_education' => 'Graduate',
            'experience' => '1–3 yrs',
            'preferred_gender' => 'Any',
            'skills' => ['Communication', 'Phlebotomy'],
            'contact_mode' => 'calls_whatsapp',
            'contact_phone' => '9000379865',
        ], $overrides);
    }

    private function saveDraft(Organisation $organisation, array $overrides = []): array
    {
        return $this->postJson("{$this->api}/recruiter/jobs", $this->payload($organisation, $overrides))
            ->assertCreated()
            ->json('data');
    }

    private function payFor(string $jobId, string $tier = 'monthly', string $reach = 'district', int $quantity = 1): array
    {
        $order = $this->postJson("{$this->api}/recruiter/jobs/{$jobId}/orders", [
            'tier' => $tier,
            'reach' => $reach,
            'quantity' => $quantity,
            'method' => 'upi',
        ])->assertOk()->json('data.order');

        return $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")
            ->assertOk()
            ->json('data.order');
    }

    public function test_step_three_saves_the_post_without_publishing_or_taking_a_slot(): void
    {
        [, $org] = $this->recruiterWithOrg(verified: true);

        $job = $this->saveDraft($org);

        $this->assertSame('draft', $job['posting_status']);
        $this->assertSame('payment_pending', $job['hiring_stage']);
        $this->assertSame(10, $job['openings']);
        $this->assertSame('Graduate', $job['min_education']);
        $this->assertSame(['Communication', 'Phlebotomy'], $job['skills']);
        $this->assertSame('Malviya Nagar', $job['locality']);

        // Candidates never see a saved, unpaid post.
        $this->getJson("{$this->api}/jobs")->assertJsonCount(0, 'data');

        // A free account's one slot is not used up by a draft.
        $this->saveDraft($org, ['title' => 'Second Technician']);
    }

    public function test_the_plan_step_offers_one_monthly_plan(): void
    {
        $this->recruiterWithOrg();

        $plans = $this->getJson("{$this->api}/recruiter/job-plans")->assertOk()->json('data');

        $this->assertSame(['monthly'], array_column($plans['tiers'], 'id'));
        $this->assertSame(['district'], array_column($plans['reaches'], 'id'));
        $this->assertSame([1], $plans['quantities']);
        $this->assertSame('₹1,999', $plans['prices']['monthly']['district']['1']['price_label']);
        $this->assertSame(199900, $plans['plan']['price_paise']);
        $this->assertSame(30, $plans['plan']['days']);
        $this->assertTrue($plans['plan']['gst_included']);
        $this->assertContains('Unlimited job posts for 30 days', $plans['tiers'][0]['features']);
        $this->assertSame([], $plans['credits']);
        $this->assertNull($plans['active_plan']);
    }

    public function test_the_first_job_post_is_free_and_the_next_needs_the_plan(): void
    {
        [, $org] = $this->recruiterWithOrg(verified: true, freePosts: 1);

        $plans = $this->getJson("{$this->api}/recruiter/job-plans")->json('data');
        $this->assertSame(1, $plans['free_jobs_left']);
        $this->assertSame('free', $plans['credits'][0]['tier']);
        $this->assertSame('Your free job post', $plans['credits'][0]['label']);

        $first = $this->saveDraft($org);
        $this->postJson("{$this->api}/recruiter/jobs/{$first['id']}/use-credit")
            ->assertOk()
            ->assertJsonPath('data.posting_status', 'pending_approval')
            ->assertJsonPath('data.plan_tier', 'free');

        // Free posts are reviewed as before: only paid posts of a verified
        // company skip the queue.
        $saved = JobPosting::find((int) substr($first['id'], 2));
        $this->assertFalse($saved->isPaid());
        $this->assertSame(JobPostingStatus::PendingApproval, $saved->posting_status);

        $this->assertSame(0, $this->getJson("{$this->api}/recruiter/job-plans")->json('data.free_jobs_left'));

        $second = $this->saveDraft($org, ['title' => 'Second Technician']);
        $this->postJson("{$this->api}/recruiter/jobs/{$second['id']}/use-credit")
            ->assertStatus(422)
            ->assertJsonPath('errors.plan.0', "You've used your free job post. Get the Monthly plan (₹1,999 incl. GST) for unlimited job posts for 30 days.");

        // Paying covers it.
        $this->payFor($second['id']);
        $this->assertSame(JobPostingStatus::Active, JobPosting::find((int) substr($second['id'], 2))->posting_status);
    }

    public function test_an_approved_free_post_stays_live_for_the_set_days(): void
    {
        $this->setPlan('job_live_days', 20);
        [, $org] = $this->recruiterWithOrg(freePosts: 1);
        $job = $this->saveDraft($org);
        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/use-credit")->assertOk();

        $saved = JobPosting::find((int) substr($job['id'], 2));
        $saved->markApproved(null);

        $this->assertEqualsWithDelta(20, now()->diffInDays($saved->fresh()->expires_at), 0.01);
    }

    public function test_an_account_that_posted_before_has_had_its_free_post(): void
    {
        [$recruiter, $org] = $this->recruiterWithOrg(freePosts: 1);
        JobPosting::factory()->create([
            'user_id' => $recruiter->id,
            'organisation_id' => $org->id,
            'posting_status' => JobPostingStatus::Active,
        ]);

        $this->assertSame(0, $this->getJson("{$this->api}/recruiter/job-plans")->json('data.free_jobs_left'));
    }

    public function test_raising_the_free_posts_in_admin_reaches_existing_accounts(): void
    {
        [, $org] = $this->recruiterWithOrg(freePosts: 1);
        $job = $this->saveDraft($org);
        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/use-credit")->assertOk();

        $this->setPlan('recruiter_free_jobs', 3);

        $this->assertSame(2, $this->getJson("{$this->api}/recruiter/job-plans")->json('data.free_jobs_left'));
    }

    public function test_paying_sends_the_post_for_review_while_the_document_is_checked(): void
    {
        [$recruiter, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $order = $this->payFor($job['id']);

        $this->assertSame('paid', $order['status']);
        $this->assertSame(199900, $order['amount_paise']);
        $this->assertSame('Monthly plan · unlimited job posts', $order['plan_name']);

        $saved = JobPosting::find((int) substr($job['id'], 2));
        $this->assertSame(JobPostingStatus::PendingApproval, $saved->posting_status);
        $this->assertSame('monthly', $saved->plan_tier);
        $this->assertSame('document_under_review', $saved->hiringStage());
        $this->assertSame('Document submitted – Under review', $saved->hiringStageLabel());

        // The document is already in, so nobody is asked for it again.
        $this->assertFalse(AppNotification::where('user_id', $recruiter->id)
            ->where('text', 'like', '%Upload your company document%')
            ->exists());
    }

    public function test_the_document_is_collected_after_payment_and_the_post_waits_for_it(): void
    {
        // Employer feedback: "Collect business documents after the employer
        // selects a plan and completes payment … we will not post the job
        // until we receive the business document."
        [$recruiter, $org] = $this->recruiterWithOrg(withDocument: false);
        $job = $this->saveDraft($org);

        $order = $this->payFor($job['id']);
        $this->assertSame('paid', $order['status']);

        $saved = JobPosting::find((int) substr($job['id'], 2));
        $this->assertSame(JobPostingStatus::PendingApproval, $saved->posting_status);
        $this->assertSame('paid_document_missing', $saved->hiringStage());
        $this->getJson("{$this->api}/jobs")->assertJsonCount(0, 'data');

        // And the employer is told what is missing.
        $this->assertTrue(AppNotification::where('user_id', $recruiter->id)
            ->where('text', 'like', '%Upload your company document%')
            ->exists());

        // No document, nothing to verify — so nothing goes live.
        $this->actingAsAdmin();
        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/verify")
            ->assertStatus(422)
            ->assertJsonPath('errors.organisation.0', 'This company has not uploaded its business document yet. Verify it once the document is in.');
        $this->assertFalse((bool) $org->fresh()->verified);
        $this->assertSame(JobPostingStatus::PendingApproval, $saved->fresh()->posting_status);
    }

    public function test_a_credit_can_be_spent_before_the_document_is_in(): void
    {
        [$recruiter, $org] = $this->recruiterWithOrg(withDocument: false);
        $first = $this->saveDraft($org);
        $this->payFor($first['id']);
        $second = $this->saveDraft($org);

        $this->postJson("{$this->api}/recruiter/jobs/{$second['id']}/use-credit")->assertOk();

        $saved = JobPosting::find((int) substr($second['id'], 2));
        $this->assertSame('paid_document_missing', $saved->hiringStage());
    }

    public function test_the_plan_covers_every_next_job_with_no_payment(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $first = $this->saveDraft($org);

        $order = $this->payFor($first['id']);
        $this->assertSame(199900, $order['amount_paise']);

        $plans = $this->getJson("{$this->api}/recruiter/job-plans")->json('data');
        $this->assertCount(1, $plans['credits']);
        $this->assertTrue($plans['credits'][0]['unlimited']);
        $this->assertNotNull($plans['active_plan']['expires_at']);

        foreach (['Senior Lab Technician', 'Night Lab Technician', 'Lab Assistant'] as $title) {
            $next = $this->saveDraft($org, ['title' => $title]);
            $this->postJson("{$this->api}/recruiter/jobs/{$next['id']}/use-credit")
                ->assertOk()
                ->assertJsonPath('data.posting_status', 'pending_approval')
                ->assertJsonPath('data.plan_tier', 'monthly');
        }

        $this->assertSame(4, JobCredit::where('tier', 'monthly')->first()->used);

        // My Plan says the same thing.
        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_monthly');
    }

    public function test_the_plan_lapses_after_its_days(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $this->payFor($this->saveDraft($org)['id']);

        $this->travel(31)->days();

        $next = $this->saveDraft($org, ['title' => 'After the month']);
        $this->postJson("{$this->api}/recruiter/jobs/{$next['id']}/use-credit")->assertStatus(422);
        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_free');
    }

    public function test_buying_the_plan_again_adds_to_the_running_one(): void
    {
        [$recruiter] = $this->recruiterWithOrg();
        $this->onEmployerPlan($recruiter);

        $order = $this->postJson("{$this->api}/payments/orders", [
            'audience' => 'recruiter', 'plan_id' => 'recruiter_monthly', 'method' => 'upi',
        ])->assertOk()->json('data.order');
        $this->postJson("{$this->api}/payments/orders/{$order['id']}/confirm")->assertOk();

        $ends = JobCredit::where('tier', 'monthly')->orderByDesc('expires_at')->first()->expires_at;
        $this->assertEqualsWithDelta(60, now()->diffInDays($ends), 0.01);
    }

    public function test_a_refund_ends_the_plan(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $order = $this->payFor($this->saveDraft($org)['id']);

        app(\App\Services\Payments\PaymentRefunds::class)
            ->refund(\App\Models\PaymentOrder::find($order['id']), 'test');

        $this->assertSame([], $this->getJson("{$this->api}/recruiter/job-plans")->json('data.credits'));
        $this->getJson("{$this->api}/subscription")
            ->assertJsonPath('data.active.recruiter.plan_id', 'recruiter_free');
    }

    public function test_using_a_credit_with_none_left_is_refused(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/use-credit")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['plan']]);
    }

    public function test_a_post_cannot_be_paid_for_twice(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);
        $this->payFor($job['id']);

        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/orders", [
            'tier' => 'monthly', 'reach' => 'district', 'quantity' => 1, 'method' => 'card',
        ])->assertStatus(422);
    }

    public function test_a_verified_employers_paid_post_goes_live_straight_away(): void
    {
        [, $org] = $this->recruiterWithOrg(verified: true);
        $job = $this->saveDraft($org);

        $this->payFor($job['id']);

        $saved = JobPosting::find((int) substr($job['id'], 2));
        $this->assertSame(JobPostingStatus::Active, $saved->posting_status);
        $this->assertSame('live', $saved->hiringStage());
        $this->getJson("{$this->api}/jobs")->assertJsonCount(1, 'data');
    }

    public function test_verifying_the_document_publishes_the_paid_posts_waiting_on_it(): void
    {
        [, $org] = $this->recruiterWithOrg(withDocument: true);
        $job = $this->saveDraft($org);
        $this->payFor($job['id']);

        $saved = JobPosting::find((int) substr($job['id'], 2));
        $this->assertSame('document_under_review', $saved->hiringStage());

        $this->actingAsAdmin();
        $this->postJson("{$this->api}/admin/organisations/org_{$org->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.paid_postings_published', 1);

        $this->assertSame(JobPostingStatus::Active, $saved->fresh()->posting_status);
        $this->assertSame('live', $saved->fresh()->load('organisationRecord')->hiringStage());
    }

    public function test_admin_can_filter_and_count_postings_by_stage(): void
    {
        [$recruiter, $org] = $this->recruiterWithOrg();
        $this->saveDraft($org);
        $paid = $this->saveDraft($org, ['title' => 'Paid Technician']);
        $this->payFor($paid['id']);

        $this->actingAsAdmin();

        $stages = collect($this->getJson("{$this->api}/admin/jobs/stages")->assertOk()->json('data.stages'))
            ->keyBy('id');
        $this->assertSame(1, $stages['payment_pending']['count']);
        $this->assertSame(1, $stages['document_under_review']['count']);
        $this->assertSame('Paid – Document not given', $stages['paid_document_missing']['label']);

        $this->getJson("{$this->api}/admin/jobs?stage=document_under_review")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Paid Technician')
            ->assertJsonPath('data.0.stage_label', 'Document submitted – Under review')
            ->assertJsonPath('data.0.plan', 'Monthly · District');
    }

    public function test_the_admin_edited_price_is_what_is_charged(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $this->setPlan('recruiter_price_paise', 150000);
        $job = $this->saveDraft($org);

        $order = $this->payFor($job['id']);

        $this->assertSame(150000, $order['amount_paise']);
    }

    public function test_an_older_client_asking_for_several_postings_pays_for_the_plan_once(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $order = $this->payFor($job['id'], 'monthly', 'district', 6);

        $this->assertSame(199900, $order['amount_paise']);
        $this->assertSame(\App\Services\JobPlanCatalog::UNLIMITED, JobCredit::first()->total);
    }

    public function test_a_retired_tier_cannot_be_bought(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/orders", [
            'tier' => 'gold', 'reach' => 'district', 'quantity' => 1, 'method' => 'upi',
        ])->assertStatus(422);
    }

    public function test_a_number_to_call_is_required_once_calls_are_chosen(): void
    {
        [, $org] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload($org, ['contact_phone' => null]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['contact_phone']]);

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload($org, ['contact_phone' => '90003']))
            ->assertStatus(422);

        // "Apply only" needs no number.
        $this->postJson("{$this->api}/recruiter/jobs", $this->payload($org, [
            'contact_mode' => 'apply_only',
            'contact_phone' => null,
        ]))->assertCreated();
    }

    public function test_the_city_must_be_in_the_picked_state_and_lists_are_closed(): void
    {
        [, $org] = $this->recruiterWithOrg();

        $this->postJson("{$this->api}/recruiter/jobs", $this->payload($org, ['city' => 'Lucknow']))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['city']]);
        $this->postJson("{$this->api}/recruiter/jobs", $this->payload($org, ['min_education' => 'PhD']))
            ->assertStatus(422);
        $this->postJson("{$this->api}/recruiter/jobs", $this->payload($org, ['preferred_gender' => 'Robot']))
            ->assertStatus(422);
    }

    public function test_candidates_see_the_number_only_when_the_employer_asked_for_calls(): void
    {
        [, $org] = $this->recruiterWithOrg(verified: true);
        $calls = $this->saveDraft($org);
        $this->payFor($calls['id']);
        $applyOnly = $this->saveDraft($org, [
            'title' => 'Apply-only Technician',
            'contact_mode' => 'apply_only',
            'contact_phone' => '9000379865',
        ]);
        // Covered by the plan just bought.
        $this->postJson("{$this->api}/recruiter/jobs/{$applyOnly['id']}/use-credit")->assertOk();

        $this->actingAsCandidate();
        $jobs = collect($this->getJson("{$this->api}/jobs")->assertOk()->json('data'))->keyBy('title');

        $this->assertSame('9000379865', $jobs['Lab Technician']['contact_phone']);
        $this->assertSame('9000379865', $jobs['Lab Technician']['whatsapp_phone']);
        $this->assertNull($jobs['Apply-only Technician']['contact_phone']);
        // The employer's own pipeline label never reaches a candidate.
        $this->assertArrayNotHasKey('hiring_stage', $jobs['Lab Technician']);
    }

    public function test_company_details_take_an_email_and_who_the_employer_hires_for(): void
    {
        $this->actingAsRecruiter();

        $this->postJson("{$this->api}/recruiter/organisations", [
            'name' => 'Veldx Labs Pvt Ltd',
            'email' => 'jags@veldxlabs.com',
            'hiring_type' => 'company',
            'size' => 'Small',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'jags@veldxlabs.com')
            ->assertJsonPath('data.hiring_type', 'company');

        $this->postJson("{$this->api}/recruiter/organisations", [
            'name' => 'Bad', 'email' => 'not-an-email',
        ])->assertStatus(422);
        $this->postJson("{$this->api}/recruiter/organisations", [
            'name' => 'Bad', 'hiring_type' => 'freelancer',
        ])->assertStatus(422);
    }

    public function test_the_selection_lists_are_served(): void
    {
        $config = $this->getJson("{$this->api}/config/options")->assertOk()->json('data');

        $this->assertSame(['10th', '10+2', 'Diploma / ITI', 'Graduate', 'Post Graduate'], $config['education_levels']);
        $this->assertSame(['Male', 'Female', 'Any'], $config['gender_preferences']);
        $this->assertSame('Calls & WhatsApp', $config['contact_modes']['calls_whatsapp']);
        $this->assertSame('Company', $config['hiring_types']['company']['label']);
    }

    public function test_someone_elses_saved_post_cannot_be_paid_for(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $this->actingAs(User::factory()->recruiter()->create());

        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/orders", [
            'tier' => 'monthly', 'reach' => 'district', 'quantity' => 1, 'method' => 'upi',
        ])->assertNotFound();
    }

    private function planForm(array $overrides = []): array
    {
        return array_merge([
            'recruiter_plan_name' => 'Monthly', 'recruiter_price' => 1999, 'recruiter_days' => 30,
            'recruiter_free_jobs' => 1, 'job_live_days' => 30,
            'seeker_plan_name' => 'Pro', 'seeker_price' => 99, 'seeker_days' => 30,
            'seeker_free_applications' => 1, 'seeker_includes_resume' => true, 'resume_price' => 20,
        ], $overrides);
    }

    public function test_admin_sets_prices_free_limits_and_days_and_it_is_audited(): void
    {
        $this->actingAsAdmin();

        $this->getJson("{$this->api}/admin/plans")->assertOk()
            ->assertJsonPath('data.form.recruiter_price', 1999)
            ->assertJsonPath('data.form.recruiter_free_jobs', 1)
            ->assertJsonPath('data.form.seeker_price', 99);

        $this->putJson("{$this->api}/admin/plans", $this->planForm([
            'recruiter_price' => 2499, 'recruiter_days' => 45, 'recruiter_free_jobs' => 2,
            'seeker_price' => 149, 'seeker_free_applications' => 3, 'resume_price' => 30,
        ]))->assertOk()
            ->assertJsonPath('data.form.recruiter_price', 2499)
            ->assertJsonPath('data.preview.recruiter.1.price_label', '₹2,499')
            ->assertJsonPath('data.preview.recruiter.1.billing_period', 'for 45 days')
            ->assertJsonPath('data.preview.jobSeeker.1.price_paise', 14900)
            ->assertJsonPath('data.preview.resume.price_paise', 3000);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'plans.update']);

        // What employers are now offered.
        $this->actingAsRecruiter();
        $plans = $this->getJson("{$this->api}/recruiter/job-plans")->json('data');
        $this->assertSame(249900, $plans['plan']['price_paise']);
        $this->assertSame(45, $plans['plan']['days']);
        $this->assertSame(2, $plans['free_jobs_left']);
    }

    public function test_a_plan_price_must_be_positive(): void
    {
        $this->actingAsAdmin();

        $this->putJson("{$this->api}/admin/plans", $this->planForm(['recruiter_price' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('recruiter_price');
    }

    public function test_a_read_only_operator_cannot_change_prices(): void
    {
        $this->actingAsAdmin('viewer');

        $this->getJson("{$this->api}/admin/plans")->assertOk();
        $this->putJson("{$this->api}/admin/plans", $this->planForm())->assertForbidden();
    }
}
