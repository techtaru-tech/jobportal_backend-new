<?php

namespace Tests\Feature;

use App\Enums\JobPostingStatus;
use App\Models\AppNotification;
use App\Models\JobCredit;
use App\Models\JobPlanPrice;
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

    private function recruiterWithOrg(bool $verified = false, bool $withDocument = false): array
    {
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

    private function payFor(string $jobId, string $tier = 'silver', string $reach = 'district', int $quantity = 1): array
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

    public function test_the_plan_step_lists_tiers_reaches_and_prices(): void
    {
        $this->recruiterWithOrg();

        $plans = $this->getJson("{$this->api}/recruiter/job-plans")->assertOk()->json('data');

        $this->assertSame(['basic', 'silver', 'gold'], array_column($plans['tiers'], 'id'));
        $this->assertSame(['district', 'state'], array_column($plans['reaches'], 'id'));
        $this->assertSame([1, 2, 3, 4], $plans['quantities']);
        $this->assertSame('₹2,299', $plans['prices']['silver']['district']['1']['price_label']);
        $this->assertSame('₹2,999', $plans['prices']['silver']['district']['1']['mrp_label']);
        $this->assertSame(23, $plans['prices']['silver']['district']['1']['discount_percent']);
        $this->assertSame([], $plans['credits']);
    }

    public function test_paying_sends_the_post_for_review_and_asks_for_the_business_document(): void
    {
        [$recruiter, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $order = $this->payFor($job['id'], 'silver', 'district', 1);

        $this->assertSame('paid', $order['status']);
        $this->assertSame(229900, $order['amount_paise']);
        $this->assertSame('Silver · District · 1 posting', $order['plan_name']);

        $saved = JobPosting::find((int) substr($job['id'], 2));
        $this->assertSame(JobPostingStatus::PendingApproval, $saved->posting_status);
        $this->assertSame('silver', $saved->plan_tier);
        $this->assertSame('paid_document_missing', $saved->hiringStage());
        $this->assertSame('Paid – Document not given', $saved->hiringStageLabel());

        $this->assertTrue(AppNotification::where('user_id', $recruiter->id)
            ->where('text', 'like', '%Upload your business verification document%')
            ->exists());
    }

    public function test_a_pack_leaves_postings_for_the_next_job_with_no_payment(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $first = $this->saveDraft($org);

        $order = $this->payFor($first['id'], 'gold', 'state', 3);
        $this->assertSame(279900 * 3, $order['amount_paise']);

        $credits = $this->getJson("{$this->api}/recruiter/job-plans")->json('data.credits');
        $this->assertCount(1, $credits);
        $this->assertSame(2, $credits[0]['remaining']);

        $second = $this->saveDraft($org, ['title' => 'Senior Lab Technician']);
        $this->postJson("{$this->api}/recruiter/jobs/{$second['id']}/use-credit")
            ->assertOk()
            ->assertJsonPath('data.posting_status', 'pending_approval')
            ->assertJsonPath('data.plan_tier', 'gold')
            ->assertJsonPath('data.plan_reach', 'state');

        $this->assertSame(2, JobCredit::first()->used);
    }

    public function test_using_a_credit_with_none_left_is_refused(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/use-credit")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['credit_id']]);
    }

    public function test_a_post_cannot_be_paid_for_twice(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);
        $this->payFor($job['id']);

        $this->postJson("{$this->api}/recruiter/jobs/{$job['id']}/orders", [
            'tier' => 'basic', 'reach' => 'district', 'quantity' => 1, 'method' => 'card',
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
        $this->assertSame(1, $stages['paid_document_missing']['count']);
        $this->assertSame('Paid – Document not given', $stages['paid_document_missing']['label']);

        $this->getJson("{$this->api}/admin/jobs?stage=paid_document_missing")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Paid Technician')
            ->assertJsonPath('data.0.stage_label', 'Paid – Document not given')
            ->assertJsonPath('data.0.plan', 'Silver · District');
    }

    public function test_the_admin_edited_price_is_what_is_charged(): void
    {
        [, $org] = $this->recruiterWithOrg();
        JobPlanPrice::where(['tier' => 'basic', 'reach' => 'state', 'quantity' => 2])
            ->update(['price_paise' => 150000]);
        $job = $this->saveDraft($org);

        $order = $this->payFor($job['id'], 'basic', 'state', 2);

        $this->assertSame(300000, $order['amount_paise']);
    }

    public function test_four_plus_postings_are_charged_at_the_largest_rows_rate(): void
    {
        [, $org] = $this->recruiterWithOrg();
        $job = $this->saveDraft($org);

        $order = $this->payFor($job['id'], 'basic', 'district', 6);

        $this->assertSame(189900 * 6, $order['amount_paise']);
        $this->assertSame(6, JobCredit::first()->total);
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
        $this->payFor($applyOnly['id']);

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
            'size' => '11–50',
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
            'tier' => 'basic', 'reach' => 'district', 'quantity' => 1, 'method' => 'upi',
        ])->assertNotFound();
    }

    public function test_admin_edits_the_price_grid_and_it_is_audited(): void
    {
        $this->actingAsAdmin();

        $grid = $this->getJson("{$this->api}/admin/job-plans")->assertOk()->json('data');
        $this->assertCount(3 * 2 * 4, $grid['prices']);

        $this->putJson("{$this->api}/admin/job-plans", [
            'prices' => [['tier' => 'gold', 'reach' => 'state', 'quantity' => 1, 'price' => 3299, 'mrp' => 4499]],
        ])->assertOk()->assertJsonPath('message', '1 price(s) updated.');

        $this->assertSame(329900, JobPlanPrice::where(['tier' => 'gold', 'reach' => 'state', 'quantity' => 1])->value('price_paise'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'job_plans.update']);

        // An MRP below the price would show a negative discount.
        $this->putJson("{$this->api}/admin/job-plans", [
            'prices' => [['tier' => 'gold', 'reach' => 'state', 'quantity' => 1, 'price' => 3299, 'mrp' => 100]],
        ])->assertStatus(422);
    }

    public function test_a_read_only_operator_cannot_change_prices(): void
    {
        $this->actingAsAdmin('viewer');

        $this->putJson("{$this->api}/admin/job-plans", [
            'prices' => [['tier' => 'gold', 'reach' => 'state', 'quantity' => 1, 'price' => 1]],
        ])->assertForbidden();
    }
}
