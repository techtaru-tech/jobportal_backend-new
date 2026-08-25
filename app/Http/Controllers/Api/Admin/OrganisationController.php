<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JobPostingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminAuditLog;
use App\Models\Organisation;
use App\Services\AdminAuditor;
use App\Services\Notifier;
use App\Support\ApiResponse;
use App\Support\PrivateFiles;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Employers, and the verification queue.
 *
 * This is the feature the schema has been waiting for. `organisations.verified`
 * and `verified_at` have existed since the table was created;
 * `Organisation::markVerified()` exists and is documented as "only an admin or
 * an automated GST check may call this"; `Organisation::toApi()` deliberately
 * omits `verified` so no recruiter can set it. And **nothing has ever called
 * it** — the badge could not be granted at all. This controller is the only
 * writer, which is exactly as designed.
 *
 * Two things to get right:
 *
 *  - Re-uploading a GST document resets `verified` to false, so the queue must
 *    handle an employer re-entering it. The audit log is what preserves the
 *    history a boolean cannot.
 *  - `job_postings.organisation` is a denormalised name and the app snapshots
 *    the verified flag onto job payloads at read time, so approving here shows
 *    up on existing postings without a backfill. [postingCount] is surfaced so
 *    an admin can see how much reach a decision has.
 */
class OrganisationController extends ApiController
{
    public function __construct(
        private readonly AdminAuditor $auditor,
        private readonly Notifier $notifier,
    ) {}

    /** GET /admin/organisations */
    public function index(Request $request): JsonResponse
    {
        $query = Organisation::query()
            ->with('recruiter:id,phone')
            ->withCount('jobPostings');

        if ($term = trim((string) $request->query('query', ''))) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('gst_number', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%");
            });
        }

        // The three states that matter operationally, which a single
        // `verified` boolean does not distinguish: waiting on review, verified,
        // and "cannot be reviewed because they uploaded nothing".
        match ($request->query('state')) {
            'pending' => $query->where('verified', false)->whereNotNull('document_path'),
            'verified' => $query->where('verified', true),
            'no_document' => $query->where('verified', false)->whereNull('document_path'),
            default => null,
        };

        $industries = $this->listParam($request, 'industry');
        if ($industries !== []) {
            $query->whereIn('industry', $industries);
        }

        // Same GST across two employers is either a duplicate or a fraud
        // signal; either way a human should look.
        if ($request->boolean('duplicate_gst')) {
            $query->whereNotNull('gst_number')->whereIn('gst_number', function ($sub) {
                $sub->from('organisations')
                    ->selectRaw('gst_number')
                    ->whereNotNull('gst_number')
                    ->groupBy('gst_number')
                    ->havingRaw('count(*) > 1');
            });
        }

        $sort = match ($request->query('sort')) {
            // Oldest-waiting first is the correct default for a review queue.
            'oldest' => ['created_at', 'asc'],
            'jobs' => ['job_postings_count', 'desc'],
            default => ['created_at', 'desc'],
        };

        $paginator = $query->orderBy($sort[0], $sort[1])->paginate($this->perPage($request));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Organisation $org) => $this->row($org)),
        );

        return ApiResponse::paginated($paginator);
    }

    /** GET /admin/organisations/{organisationId} */
    public function show(Request $request, string $organisationId): JsonResponse
    {
        $org = $this->findOrganisation($organisationId);
        $org->load(['recruiter', 'jobPostings']);

        return ApiResponse::data([
            'organisation' => $this->row($org) + [
                'address' => $org->address,
                'website' => $org->website,
                'about' => $org->about,
                'document_name' => $org->document_name,

                // The GST certificate — a signed, ~15-minute URL off the
                // private disk, minted per request. Only on the detail
                // endpoint, and the panel must never persist it: by the time a
                // cached copy were reused it would already have expired.
                'document_url' => PrivateFiles::url($org->document_path),

                // Logos live on the public disk, so this one is a plain URL.
                'logo_url' => PrivateFiles::publicUrl($org->logo_path),
            ],

            'owner' => $org->recruiter === null ? null : [
                'id' => PublicId::encode('u', $org->recruiter->id),
                'phone' => $org->recruiter->phone,
                // Other employers under the same account — relevant context
                // when judging one of them.
                'other_organisations' => Organisation::where('user_id', $org->user_id)
                    ->where('id', '!=', $org->id)
                    ->get(['id', 'name', 'verified'])
                    ->map(fn ($o) => [
                        'id' => PublicId::encode('org', $o->id),
                        'name' => $o->name,
                        'verified' => (bool) $o->verified,
                    ])->all(),
            ],

            'jobs' => $org->jobPostings->map(fn ($j) => [
                'id' => PublicId::encode('j', $j->id),
                'code' => $j->code,
                'title' => $j->title,
                'city' => $j->city,
                'status' => $j->posting_status->value,
                'posted_at' => $j->posted_at->toIso8601String(),
            ])->all(),

            'duplicate_gst_matches' => filled($org->gst_number)
                ? Organisation::where('gst_number', $org->gst_number)
                    ->where('id', '!=', $org->id)
                    ->get(['id', 'name'])
                    ->map(fn ($o) => [
                        'id' => PublicId::encode('org', $o->id),
                        'name' => $o->name,
                    ])->all()
                : [],

            'review' => $this->review($org),
            'impact' => $this->impact($org),

            // The history a boolean cannot hold: re-uploading a GST document
            // resets `verified`, so an employer can pass through this queue
            // more than once and only the log says what happened each time.
            'audit' => AdminAuditLog::query()
                ->where('subject_type', 'Organisation')
                ->where('subject_id', PublicId::encode('org', $org->id))
                ->latest()
                ->limit(20)
                ->get(['admin_email', 'action', 'summary', 'created_at'])
                ->map(fn ($log) => [
                    'admin_email' => $log->admin_email,
                    'action' => $log->action,
                    'summary' => $log->summary,
                    'at' => $log->created_at->toIso8601String(),
                ])->all(),
        ]);
    }

    /**
     * POST /admin/organisations/{organisationId}/verify
     *
     * Grants the trust badge every candidate sees on this employer's postings.
     * Goes through `markVerified()` so `verified_at` is set the one way the
     * model defines.
     */
    public function verify(Request $request, string $organisationId): JsonResponse
    {
        $org = $this->findOrganisation($organisationId);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:280'],
        ]);

        if ($org->verified) {
            return ApiResponse::message('That employer is already verified.');
        }

        $org->markVerified();

        $note = trim((string) ($validated['note'] ?? ''));
        $postings = $org->jobPostings()->count();

        // Counted before the audit line so the log records the real reach of
        // the decision, which is the number an admin is judged on later.
        $activated = $org->jobPostings()
            ->where('posting_status', JobPostingStatus::Active->value)
            ->count();

        $this->auditor->log(
            action: 'organisation.verify',
            summary: "Verified {$org->name}"
                .($postings > 0 ? " ({$activated} of {$postings} posting(s) now visible to candidates)" : '')
                .($note !== '' ? ": {$note}" : ''),
            subjectType: 'Organisation',
            subjectId: PublicId::encode('org', $org->id),
            changes: [
                'verified' => ['from' => false, 'to' => true],
                'note' => ['from' => null, 'to' => $note ?: null],
            ],
        );

        // The employer is the one whose postings just went live; they were
        // never told before this.
        $this->notifier->organisationVerified($org, $activated);

        return ApiResponse::data(
            [
                'verified' => true,
                'verified_at' => $org->verified_at?->toIso8601String(),
                // Echoed so the panel can confirm the actual effect rather
                // than restating the estimate it showed before the click.
                'postings_now_visible' => $activated,
            ],
            $activated > 0
                ? "Employer verified. {$activated} posting(s) are now visible to candidates."
                : 'Employer verified.',
        );
    }

    /**
     * POST /admin/organisations/{organisationId}/unverify
     *
     * Withdraws the badge. A reason is required rather than optional: this
     * removes public trust from a live employer, and six months later the only
     * thing that will explain why is this string.
     */
    public function unverify(Request $request, string $organisationId): JsonResponse
    {
        $org = $this->findOrganisation($organisationId);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:280'],
        ]);

        if (! $org->verified) {
            return ApiResponse::message('That employer is not verified.');
        }

        $hidden = $org->jobPostings()
            ->where('posting_status', JobPostingStatus::Active->value)
            ->count();

        $org->markUnverified();

        $this->auditor->log(
            action: 'organisation.unverify',
            summary: "Withdrew verification from {$org->name}"
                .($hidden > 0 ? " ({$hidden} posting(s) hidden from candidates)" : '')
                .": {$validated['reason']}",
            subjectType: 'Organisation',
            subjectId: PublicId::encode('org', $org->id),
            changes: [
                'verified' => ['from' => true, 'to' => false],
                'reason' => ['from' => null, 'to' => $validated['reason']],
            ],
        );

        $this->notifier->organisationUnverified($org, $validated['reason']);

        return ApiResponse::data(
            ['verified' => false, 'postings_now_hidden' => $hidden],
            $hidden > 0
                ? "Verification withdrawn. {$hidden} posting(s) are now hidden from candidates."
                : 'Verification withdrawn.',
        );
    }

    /**
     * The GSTIN shape the government issues: 2-digit state code, the holder's
     * 10-character PAN, a 1-character entity number, a literal `Z`, and a
     * checksum character.
     *
     * A format check is not a validity check — only the GST portal can say
     * whether a well-formed number is real — but a *mis*-formatted number is
     * conclusive on its own, and it is the single cheapest signal on this
     * screen. Computed server-side so the panel does not carry a second,
     * drifting copy of this rule.
     */
    private const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    /**
     * The checks a human would run before granting the badge, each resolved to
     * pass / warn / fail with the reason attached.
     *
     * Deliberately advisory: nothing here blocks the button. An employer with
     * no website and no logo may still be entirely legitimate, and an admin
     * with a phone call in front of them knows things this list cannot. What
     * this removes is the need to go hunting for each fact across four cards
     * before deciding.
     *
     * @return array<int, array{key: string, label: string, status: string, detail: string}>
     */
    private function review(Organisation $org): array
    {
        $gst = (string) $org->gst_number;
        $duplicates = filled($gst)
            ? Organisation::where('gst_number', $gst)->where('id', '!=', $org->id)->count()
            : 0;

        $otherVerified = Organisation::where('user_id', $org->user_id)
            ->where('id', '!=', $org->id)
            ->where('verified', true)
            ->count();

        // Has this employer been through the queue before? Re-uploading a
        // document resets `verified`, so a second pass is normal — but a
        // previous *withdrawal* is the most important thing on this screen.
        $withdrawnBefore = AdminAuditLog::where('subject_type', 'Organisation')
            ->where('subject_id', PublicId::encode('org', $org->id))
            ->where('action', 'organisation.unverify')
            ->count();

        $checks = [];

        $checks[] = [
            'key' => 'document',
            'label' => 'GST certificate uploaded',
            'status' => filled($org->document_path) ? 'pass' : 'fail',
            'detail' => filled($org->document_path)
                ? ($org->document_name ?: 'Document on file')
                : 'Nothing to review against — ask the employer to upload one.',
        ];

        $checks[] = [
            'key' => 'gst_format',
            'label' => 'GST number well-formed',
            'status' => match (true) {
                blank($gst) => 'fail',
                preg_match(self::GSTIN, $gst) === 1 => 'pass',
                default => 'warn',
            },
            'detail' => match (true) {
                blank($gst) => 'No GST number given.',
                preg_match(self::GSTIN, $gst) === 1 => 'Matches the 15-character GSTIN format.',
                default => 'Does not match the GSTIN format — check it against the certificate.',
            },
        ];

        // Labels stay neutral nouns rather than claims ("GST uniqueness", not
        // "GST not shared"): a label that asserts the good outcome reads as a
        // contradiction the moment the check fails and the detail says so.
        $checks[] = [
            'key' => 'gst_unique',
            'label' => 'GST number uniqueness',
            'status' => $duplicates === 0 ? 'pass' : 'warn',
            'detail' => $duplicates === 0
                ? 'No other employer uses this number.'
                : sprintf(
                    'Also used by %d other employer%s — a duplicate or a fraud signal.',
                    $duplicates,
                    $duplicates === 1 ? '' : 's',
                ),
        ];

        $checks[] = [
            'key' => 'history',
            'label' => 'Withdrawal history',
            'status' => $withdrawnBefore === 0 ? 'pass' : 'warn',
            'detail' => match (true) {
                $withdrawnBefore === 0 => 'This employer has never had verification withdrawn.',
                $withdrawnBefore === 1 => 'Verification was withdrawn once before — read the history below.',
                default => "Verification was withdrawn {$withdrawnBefore} times before — read the history below.",
            },
        ];

        $checks[] = [
            'key' => 'owner',
            'label' => 'Account holder',
            'status' => $org->recruiter?->phone_verified_at !== null ? 'pass' : 'warn',
            'detail' => match (true) {
                $org->recruiter === null => 'No account is attached to this employer.',
                $org->recruiter->phone_verified_at === null => 'Phone number has never been verified by OTP.',
                $otherVerified > 0 => "Phone verified, and already runs {$otherVerified} verified employer(s).",
                default => 'Phone verified by OTP.',
            },
        ];

        $profile = collect([
            'address' => filled($org->address),
            'website' => filled($org->website),
            'about' => filled($org->about),
            'logo' => filled($org->logo_path),
        ]);
        $missing = $profile->filter(fn (bool $has) => ! $has)->keys();

        $checks[] = [
            'key' => 'profile',
            'label' => 'Profile completeness',
            'status' => $missing->isEmpty() ? 'pass' : ($missing->count() >= 3 ? 'warn' : 'pass'),
            'detail' => $missing->isEmpty()
                ? 'Address, website, description and logo all present.'
                : 'Missing '.$missing->implode(', ').'.',
        ];

        return $checks;
    }

    /**
     * What actually changes for candidates the moment the switch is flipped.
     *
     * Verification is not a badge — `JobPosting::isPubliclyVisible()` gates on
     * it, so approving here is what makes this employer's postings reachable at
     * all. Stating the number up front is the difference between an admin
     * approving a record and an admin knowing they are publishing three jobs.
     *
     * @return array<string, int|bool>
     */
    private function impact(Organisation $org): array
    {
        $active = $org->jobPostings()
            ->where('posting_status', JobPostingStatus::Active->value)
            ->count();

        return [
            'active_postings' => $active,
            'total_postings' => $org->jobPostings()->count(),
            // True while those active postings are being withheld from
            // candidates purely because this decision has not been made.
            'currently_hidden' => ! $org->verified && $active > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Organisation $org): array
    {
        return [
            'id' => PublicId::encode('org', $org->id),
            'name' => $org->name,
            'industry' => $org->industry?->value,
            'size' => $org->size?->value,
            'gst_number' => $org->gst_number,
            'verified' => (bool) $org->verified,
            'verified_at' => $org->verified_at?->toIso8601String(),
            'has_document' => filled($org->document_path),
            'has_logo' => filled($org->logo_path),
            // Derived rather than stored: "pending" is unverified WITH
            // something to review, which is a different queue from unverified
            // with nothing uploaded.
            'review_state' => match (true) {
                (bool) $org->verified => 'verified',
                filled($org->document_path) => 'pending',
                default => 'no_document',
            },
            'jobs' => (int) ($org->job_postings_count ?? $org->jobPostings()->count()),
            'active_jobs' => $org->jobPostings()
                ->where('posting_status', JobPostingStatus::Active->value)
                ->count(),
            'owner' => $org->recruiter === null ? null : [
                'id' => PublicId::encode('u', $org->recruiter->id),
                'phone' => $org->recruiter->phone,
            ],
            'created_at' => $org->created_at->toIso8601String(),
        ];
    }

    private function findOrganisation(string $organisationId): Organisation
    {
        $id = PublicId::decode('org', $organisationId);
        $org = $id === null ? null : Organisation::find($id);

        if (! $org) {
            throw new NotFoundHttpException('That employer was not found.');
        }

        return $org;
    }
}
