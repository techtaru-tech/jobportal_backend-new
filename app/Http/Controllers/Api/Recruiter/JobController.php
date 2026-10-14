<?php

namespace App\Http\Controllers\Api\Recruiter;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Enums\NotificationAudience;
use App\Enums\ProfileField;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\JobResource;
use App\Enums\PaymentMethod;
use App\Models\JobCredit;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Services\JobPlanCatalog;
use App\Services\JobPostingFlow;
use App\Services\Notifier;
use App\Services\OptionListService;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use App\Support\Display;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** §8 Post a job (recruiter). */
class JobController extends ApiController
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly SubscriptionService $subscriptions,
        private readonly JobPlanCatalog $jobPlans,
        private readonly JobPostingFlow $postingFlow,
        private readonly PaymentService $payments,
    ) {}

    /**
     * POST /recruiter/jobs (§8.1)
     *
     * With `save_as_draft`, the reworked Post a Job flow: the posting is
     * saved once Company details is done ("Your post has been saved! Add a
     * plan to make it live") and only goes for review once a plan pays for
     * it — see [useCredit] and [openOrder]. A saved draft takes no plan slot
     * and is invisible to candidates.
     *
     * Without it, the original single-step submit (still used by the web
     * employer area): straight to review, under the account's subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $asDraft = $request->boolean('save_as_draft');

        if (! $asDraft) {
            $this->assertCanPostAnother($request);
        }

        $validated = $this->validated($request);
        $organisation = $this->ownedOrganisation($request, $validated['organisation_id']);
        unset($validated['organisation_id']);

        $job = $request->user()->jobPostings()->make($validated + [
            'organisation_id' => $organisation->id,
            // Denormalised so the candidate-facing card renders without a join.
            'organisation' => $organisation->name,
        ]);
        if ($asDraft) {
            $job->posting_status = JobPostingStatus::Draft;
        }
        $job->save();

        if ($asDraft) {
            return ApiResponse::data(
                new JobResource($job->load('organisationRecord')),
                'Your post has been saved! Add a plan to make it live.',
                201,
            );
        }

        $this->notifier->jobPosted($job->load('organisationRecord'));

        /*
         * "Submitted", not "posted".
         *
         * Every posting is created `pending_approval` and reaches candidates
         * only once an admin approves it, so "Job posted." was telling a
         * recruiter their job was live while nobody could see it. The app's own
         * screen already worked around this by discarding the server message
         * and hardcoding its own — which is the signal that the message, not
         * the screen, was wrong. Fixed here so every client gets it right.
         */
        return ApiResponse::data(
            new JobResource($job->load('organisationRecord')),
            "Submitted for approval — you'll be notified once it's live.",
            201,
        );
    }

    /**
     * The free plan allows one active posting at a time (`plans.php`).
     *
     * Enforced here and not only in the app: the limit is the whole difference
     * between the free and paid recruiter tiers, and until now the only thing
     * applying it was the client that benefits from ignoring it. The app still
     * hides the button, so reaching this is either a stale screen or someone
     * calling the API directly.
     */
    private function assertCanPostAnother(Request $request): void
    {
        JobPosting::expireOverdue();

        $limit = $this->subscriptions->limitFor(
            $request->user(),
            NotificationAudience::Recruiter,
            'active_jobs',
        );

        if ($limit === null) {
            return;
        }

        // A posting waiting on review occupies a slot exactly as a live one
        // does. Counting only `active` would let a free account submit an
        // unlimited number of postings and have every one of them approved
        // into existence — the limit would be enforced against a state the
        // recruiter no longer controls, which is no limit at all.
        $active = $request->user()->jobPostings()
            ->whereIn('posting_status', [
                JobPostingStatus::PendingApproval->value,
                JobPostingStatus::Active->value,
            ])
            ->count();

        if ($active >= $limit) {
            throw ValidationException::withMessages([
                'plan' => [
                    $limit === 1
                        ? 'The Free plan allows 1 active job post. Upgrade to Business to post more.'
                        : "Your plan allows {$limit} active job posts. Upgrade to post more.",
                ],
            ])->status(422);
        }
    }

    /** GET /recruiter/jobs/mine (§8.2) — every status, unlike the public list. */
    public function mine(Request $request): JsonResponse
    {
        JobPosting::expireOverdue();

        $query = $request->user()->jobPostings()
            ->withOrganisation()
            ->withCount('applications')
            ->latest('posted_at');

        if ($statuses = $this->listParam($request, 'status')) {
            $query->whereIn('posting_status', $statuses);
        }

        return ApiResponse::paginated($query->paginate($this->perPage($request)), JobResource::class);
    }

    /** PATCH /recruiter/jobs/{jobId} — edit a posting. */
    public function update(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);
        $validated = $this->validated($request, partial: true);

        if (array_key_exists('organisation_id', $validated)) {
            $organisation = $this->ownedOrganisation($request, $validated['organisation_id']);
            $validated['organisation_id'] = $organisation->id;
            $validated['organisation'] = $organisation->name;
        }

        // Both read before the write: saving is what makes the posting differ
        // from the copy an admin reviewed, and `wasVisible` has to be captured
        // before `markResubmitted()` moves the status out from under it.
        $needsReview = $job->editNeedsReapproval();
        $wasVisible = in_array($job->posting_status, [
            JobPostingStatus::Active,
            JobPostingStatus::Paused,
        ], true);

        $job->fill($validated)->save();

        /*
         * The edited posting goes (back) into the review queue.
         *
         * The approval gate otherwise only guarded the front door: a recruiter
         * could get an unremarkable posting approved and then rewrite it into
         * something else, and it stayed visible with nobody having read the new
         * version. It also gave a rejected posting nowhere to go — see
         * [JobPosting::editNeedsReapproval] for which statuses qualify and why.
         *
         * The message distinguishes the two, because the consequence differs: a
         * live posting has just left the candidates' browse list, while a
         * rejected one was never on it. Reporting a bare "Job updated." for the
         * first case would let a recruiter read the silence as "saved and still
         * live".
         */
        if ($needsReview) {
            $job->markResubmitted();
            $this->notifier->jobEditedPendingReview($job, wasVisible: $wasVisible);
        }

        return ApiResponse::data(
            new JobResource($job->fresh()->load('organisationRecord')),
            match (true) {
                $needsReview && $wasVisible => 'Saved — your changes need approval, so the posting is back in review and paused for candidates.',
                $needsReview => 'Saved and resubmitted for approval.',
                default => 'Job updated.',
            },
        );
    }

    /** PATCH /recruiter/jobs/{jobId}/status (§8.3) */
    public function updateStatus(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);

        $validated = $request->validate([
            // draft/expired are system-set, so only the transitions the Job
            // Management screen offers are accepted here.
            'status' => ['required', Rule::in([
                JobPostingStatus::Active->value,
                JobPostingStatus::Paused->value,
                JobPostingStatus::Closed->value,
            ])],
        ]);

        $target = JobPostingStatus::from($validated['status']);

        if (! $job->posting_status->canTransitionTo($target)) {
            return ApiResponse::error(
                "A {$job->posting_status->value} job cannot be moved to {$target->value}.",
                422,
                ['status' => ["Invalid transition from {$job->posting_status->value} to {$target->value}."]],
            );
        }

        $job->forceFill(['posting_status' => $target->value])->save();

        return ApiResponse::data(new JobResource($job->load('organisationRecord')), 'Job status updated.');
    }

    /** GET /recruiter/job-plans — tiers, reaches, the price grid and postings left. */
    public function plans(Request $request): JsonResponse
    {
        return ApiResponse::data($this->jobPlans->toApi($request->user()));
    }

    /**
     * POST /recruiter/jobs/{jobId}/use-credit — sends a saved posting for
     * review on a posting left over from an earlier pack, with no payment.
     */
    public function useCredit(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);

        $validated = $request->validate([
            'credit_id' => ['nullable', 'integer'],
        ]);

        $credits = $this->jobPlans->usableCredits($request->user());
        $credit = isset($validated['credit_id'])
            ? $credits->firstWhere('id', (int) $validated['credit_id'])
            : $credits->first();

        if (! $credit instanceof JobCredit) {
            throw ValidationException::withMessages([
                'credit_id' => ['You have no postings left on a plan — choose a plan to continue.'],
            ])->status(422);
        }

        $job = $this->postingFlow->useCredit($job, $credit);

        return ApiResponse::data(
            new JobResource($job->load('organisationRecord')),
            $this->submittedMessage($job),
        );
    }

    /**
     * POST /recruiter/jobs/{jobId}/orders — opens a payment for [quantity]
     * postings at [tier] / [reach], the first of which is this one. The app
     * then confirms it through `/payments/orders/{order}/confirm`, exactly as
     * it does a subscription.
     */
    public function openOrder(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);

        $validated = $request->validate([
            'tier' => ['required', 'string', 'max:20'],
            'reach' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
        ]);

        $order = $this->payments->openJobPlanOrder(
            $request->user(),
            $job,
            $validated['tier'],
            $validated['reach'],
            (int) $validated['quantity'],
            PaymentMethod::from($validated['method']),
        );

        return ApiResponse::data([
            'order' => $order->toApi(),
            'checkout' => $this->payments->checkoutFor($order),
        ]);
    }

    /** What the employer is told once a posting has gone for review. */
    private function submittedMessage(JobPosting $job): string
    {
        return match ($job->hiringStage()) {
            'live' => 'Your post is live.',
            'paid_document_missing', 'posted_document_missing' => 'Your post is under review. Upload your business document to get it live.',
            default => 'Your post is under review.',
        };
    }

    /** GET /recruiter/jobs/{jobId}/stats (§8.4) */
    public function stats(Request $request, string $jobId): JsonResponse
    {
        $job = $this->findOwned($request, $jobId);

        $counts = $job->applications()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = collect(ApplicationStatus::values())
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)]);

        return ApiResponse::data([
            'total_applicants' => (int) $counts->sum(),
            'by_status' => $byStatus->all(),
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $validated = $request->validate([
            'role' => [$required, 'string', 'max:80'],
            'title' => [$required, 'string', 'max:120'],
            'organisation_id' => [$required, 'string'],
            'organisation_note' => ['nullable', 'string', 'max:255'],
            'city' => [$required, 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'salary_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:100000000', 'gte:salary_min'],
            'experience' => ['nullable', 'string', 'max:40'],

            // Resolved lists, not `config()` directly — both are
            // admin-editable, and validating against the config file would
            // reject a value the Post-a-Job picker had just offered.
            'type' => [$required, Rule::in(app(OptionListService::class)->list('job_types'))],
            'shift' => [$required, Rule::in(app(OptionListService::class)->list('shifts'))],

            // Freeform allowed — recruiters type their own (§8.1).
            'qualifications' => ['nullable', 'array'],
            'qualifications.*' => ['string', 'max:120'],
            'duties' => ['nullable', 'array'],
            'duties.*' => ['string', 'max:300'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:120'],

            'about' => ['nullable', 'string', 'max:4000'],

            // Must be a subset of the eight ProfileField values (§8.1).
            'required_fields' => ['nullable', 'array'],
            'required_fields.*' => [Rule::in(ProfileField::values())],

            // ── Post a Job, reworked — all optional on the wire, so the web
            // employer area and older builds keep posting as they did.
            'openings' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'charges_fee' => ['nullable', 'boolean'],
            'state' => ['nullable', Rule::in(config('options.states', []))],
            'locality' => ['nullable', 'string', 'max:120'],
            'min_education' => ['nullable', Rule::in(config('options.education_levels', []))],
            'preferred_gender' => ['nullable', Rule::in(config('options.gender_preferences', []))],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:60'],
            'contact_mode' => ['nullable', Rule::in(array_keys(config('options.contact_modes', [])))],
            // A number candidates can actually call — required once the
            // employer chose to be called.
            'contact_phone' => [
                'nullable',
                'digits:10',
                Rule::requiredIf(fn () => in_array($request->input('contact_mode'), ['calls', 'calls_whatsapp'], true)),
            ],
            'whatsapp_phone' => ['nullable', 'digits:10'],
        ], [
            'salary_max.gte' => 'The maximum salary must be at least the minimum salary.',
            'contact_phone.required' => 'Add the number candidates should call.',
            'contact_phone.digits' => 'The contact number must be 10 digits.',
            'whatsapp_phone.digits' => 'The WhatsApp number must be 10 digits.',
        ]);

        // Picked from the state's own list once a state is given, the same
        // rule the candidate's Current city follows.
        if (filled($validated['state'] ?? null) && filled($validated['city'] ?? null)) {
            $cities = config('options.state_cities.'.$validated['state'], []);
            if ($cities !== [] && ! in_array($validated['city'], $cities, true)) {
                throw ValidationException::withMessages([
                    'city' => ["Pick a city in {$validated['state']}."],
                ])->status(422);
            }
        }

        foreach (['qualifications', 'duties', 'benefits', 'required_fields', 'skills'] as $list) {
            if (array_key_exists($list, $validated)) {
                $validated[$list] = Display::cleanList($validated[$list]);
            }
        }

        return $validated;
    }

    /** §8.1 — organisation_id must be one of this recruiter's own; else 403. */
    private function ownedOrganisation(Request $request, string $organisationId): Organisation
    {
        $organisation = $request->user()->organisations()->find(PublicId::decode('org', $organisationId));

        if (! $organisation) {
            abort(403, 'That organisation does not belong to your account.');
        }

        return $organisation;
    }

    private function findOwned(Request $request, string $jobId): JobPosting
    {
        $job = $request->user()->jobPostings()->find(PublicId::decode('j', $jobId));

        if (! $job) {
            throw new NotFoundHttpException('That job posting was not found.');
        }

        return $job;
    }
}
