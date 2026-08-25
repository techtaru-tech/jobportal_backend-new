<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\User;
use App\Services\AdminAuditor;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Accounts. **One list, not two.**
 *
 * There is no "candidates page" and no "recruiters page" here, and that is
 * deliberate rather than an omission. `users.role` records the side an account
 * signed up on and nothing else — the phone is globally unique, both profiles
 * are created lazily, and the same person routinely posts jobs and applies for
 * them. Splitting the list by `role` would file a hiring manager who also
 * job-hunts under exactly one of the two things they are, and would make the
 * other half of their activity unreachable.
 *
 * So: one row per human, with **facets** for each side (`candidate` and
 * `recruiter`), and "is a recruiter" derived from owning an organisation or a
 * posting rather than from a column.
 */
class UserController extends ApiController
{
    public function __construct(private readonly AdminAuditor $auditor) {}

    /** GET /admin/users */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['candidateProfile', 'recruiterProfile'])
            ->withCount(['applications', 'jobPostings', 'organisations', 'savedJobs']);

        if ($term = trim((string) $request->query('query', ''))) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('phone', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('candidateProfile', fn (Builder $p) => $p
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            });
        }

        // "Which side is this account actually using", by activity — not by
        // `users.role`. An account can legitimately match both.
        match ($request->query('side')) {
            'recruiter' => $query->where(fn (Builder $q) => $q
                ->whereHas('organisations')->orWhereHas('jobPostings')),
            'candidate' => $query->whereHas('applications'),
            default => null,
        };

        if ($request->filled('verified')) {
            $request->boolean('verified')
                ? $query->whereNotNull('phone_verified_at')
                : $query->whereNull('phone_verified_at');
        }

        if ($request->filled('min_strength')) {
            $min = (int) $request->integer('min_strength');
            $query->whereHas('candidateProfile', fn (Builder $p) => $p->where('profile_strength', '>=', $min));
        }

        if ($city = trim((string) $request->query('city', ''))) {
            $query->whereHas('candidateProfile', fn (Builder $p) => $p->where('home_city', $city));
        }

        $sort = match ($request->query('sort')) {
            'oldest' => ['created_at', 'asc'],
            'last_login' => ['last_login_at', 'desc'],
            'applications' => ['applications_count', 'desc'],
            default => ['created_at', 'desc'],
        };

        $paginator = $query->orderBy($sort[0], $sort[1])->paginate($this->perPage($request));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (User $user) => $this->row($user)),
        );

        return ApiResponse::paginated($paginator);
    }

    /**
     * GET /admin/users/{userId}
     *
     * One page for one person, carrying both sides at once. The panel renders
     * it as tabs; the API returns it whole because every tab is small and the
     * point of the screen is seeing the halves together.
     */
    public function show(Request $request, string $userId): JsonResponse
    {
        $user = $this->findUser($userId);

        $user->load([
            'candidateProfile.educations',
            'candidateProfile.workExperiences',
            'recruiterProfile',
            'organisations',
            'jobPostings',
            'applications.jobPosting',
            'savedJobs.jobPosting',
            'deviceTokens',
        ]);

        $profile = $user->candidateProfile;

        return ApiResponse::data([
            'account' => [
                'id' => PublicId::encode('u', $user->id),
                'phone' => $user->phone,
                'email' => $user->email,
                'name' => $user->name,
                // Labelled as what it is, so nothing downstream mistakes it
                // for a permission.
                'signed_up_as' => $user->role->value,
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'devices' => $user->deviceTokens->map(fn ($t) => [
                    'platform' => $t->platform,
                    'registered_at' => $t->created_at->toIso8601String(),
                ])->all(),
            ],

            'candidate' => $profile === null ? null : [
                'name' => $profile->name,
                'email' => $profile->email,
                'gender' => $profile->gender,
                'dob' => $profile->dob?->toDateString(),
                'address' => $profile->address,
                'home_city' => $profile->home_city,
                'home_pincode' => $profile->home_pincode,
                'has_coordinates' => $profile->home_latitude !== null && $profile->home_longitude !== null,
                'qualification' => $profile->qualification,
                'experience' => $profile->experience,
                'skills' => $profile->skills ?? [],
                'skill_levels' => $profile->skill_levels ?? [],
                'preferred_cities' => $profile->location ?? [],
                'specialization' => $profile->specialization ?? [],
                'preferred_roles' => $profile->preferred_roles ?? [],
                'preferred_job_types' => $profile->preferred_job_types ?? [],
                'preferred_shifts' => $profile->preferred_shifts ?? [],
                'expected_salary' => $profile->expected_salary,
                'certifications' => $profile->certifications ?? [],
                'certification_years' => $profile->certification_years ?? [],
                'languages' => $profile->languages ?? [],
                'language_levels' => $profile->language_levels ?? [],
                'about' => $profile->about,
                'has_resume' => filled($profile->resume_path),
                'resume_name' => $profile->resume_name,
                'has_photo' => filled($profile->photo_path),
                'has_intro_video' => filled($profile->intro_video_path),

                // The server's own stored figure. Never recomputed here — the
                // app reads the same column, and two implementations of the
                // same score is how they end up disagreeing.
                'profile_strength' => (int) $profile->profile_strength,
                'strength_breakdown' => $this->strengthBreakdown($profile),

                'educations' => $profile->educations->map(fn ($e) => [
                    'id' => PublicId::encode('edu', $e->id),
                    'qualification' => $e->qualification,
                    'specialization' => $e->specialization,
                    'institute' => $e->institute,
                    'year' => $e->year,
                ])->all(),
                'experiences' => $profile->workExperiences->map(fn ($w) => [
                    'id' => PublicId::encode('exp', $w->id),
                    'designation' => $w->designation,
                    'organization' => $w->organization,
                    'department' => $w->department,
                    'city' => $w->city,
                    'start_date' => $w->start_date,
                    'end_date' => $w->end_date,
                    'currently_working' => (bool) $w->currently_working,
                ])->all(),
            ],

            'recruiter' => [
                'contact' => $user->recruiterProfile === null ? null : [
                    'contact_person_name' => $user->recruiterProfile->contact_person_name,
                    'contact_email' => $user->recruiterProfile->contact_email,
                    'contact_phone' => $user->recruiterProfile->contact_phone,
                ],
                'organisations' => $user->organisations->map(fn ($o) => [
                    'id' => PublicId::encode('org', $o->id),
                    'name' => $o->name,
                    'industry' => $o->industry?->value,
                    'size' => $o->size?->value,
                    'verified' => (bool) $o->verified,
                    'verified_at' => $o->verified_at?->toIso8601String(),
                    'gst_number' => $o->gst_number,
                    'has_document' => filled($o->document_path),
                ])->all(),
                'jobs' => $user->jobPostings->map(fn ($j) => [
                    'id' => PublicId::encode('j', $j->id),
                    'code' => $j->code,
                    'title' => $j->title,
                    'city' => $j->city,
                    'status' => $j->posting_status->value,
                    'posted_at' => $j->posted_at->toIso8601String(),
                ])->all(),
            ],

            'applications' => $user->applications
                ->sortByDesc('applied_at')
                ->values()
                ->map(fn (Application $a) => [
                    'reference' => $a->reference,
                    'status' => $a->status->value,
                    'applied_at' => $a->applied_at->toIso8601String(),
                    'stage_updated_at' => $a->stage_updated_at?->toIso8601String(),
                    // The frozen figure at submit time. Routinely differs from
                    // the live profile_strength above, and both are worth
                    // seeing: the recruiter sorted on this one.
                    'snapshot_profile_strength' => (int) $a->snapshot_profile_strength,
                    'job' => $a->jobPosting === null ? null : [
                        'id' => PublicId::encode('j', $a->jobPosting->id),
                        'code' => $a->jobPosting->code,
                        'title' => $a->jobPosting->title,
                        'organisation' => $a->jobPosting->organisation,
                    ],
                ])->all(),

            'saved_jobs' => $user->savedJobs
                ->filter(fn ($s) => $s->jobPosting !== null)
                ->map(fn ($s) => [
                    'id' => PublicId::encode('j', $s->jobPosting->id),
                    'code' => $s->jobPosting->code,
                    'title' => $s->jobPosting->title,
                    'saved_at' => $s->created_at->toIso8601String(),
                ])->values()->all(),
        ]);
    }

    /**
     * POST /admin/users/{userId}/revoke-tokens
     *
     * Signs the account out of every device. The one account-level action
     * worth having: it is reversible (they sign in again with an OTP) and it
     * is the only remedy for a token on a lost phone.
     */
    public function revokeTokens(Request $request, string $userId): JsonResponse
    {
        $user = $this->findUser($userId);
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        $this->auditor->log(
            action: 'user.revoke_tokens',
            summary: "Signed {$user->phone} out of {$count} device(s)",
            subjectType: 'User',
            subjectId: PublicId::encode('u', $user->id),
            changes: ['tokens_revoked' => ['from' => $count, 'to' => 0]],
        );

        return ApiResponse::message("Signed out of {$count} device(s).");
    }

    /**
     * The completion score broken down by the weights that produce it, so an
     * admin can see *which* component is missing rather than only that the
     * total is low.
     *
     * Reads `CandidateProfile::sectionParts()` — the same map
     * `calculateStrength()` scores from — so the breakdown can never drift
     * from the number it is explaining. Every bucket reports `filled`/`total`,
     * because a half-finished section needs to look different from an
     * untouched one.
     *
     * @return list<array{field: string, weight: int, earned: bool, filled: int, total: int}>
     */
    private function strengthBreakdown(CandidateProfile $profile): array
    {
        $parts = $profile->sectionParts();

        $breakdown = [];
        foreach (CandidateProfile::WEIGHTS as $field => $weight) {
            $bucketParts = $parts[$field] ?? [];
            $total = count($bucketParts);
            $filled = count(array_filter($bucketParts));

            $breakdown[] = [
                'field' => $field,
                'weight' => (int) $weight,
                // Fully earned only — a partially filled bucket has not
                // earned its weight.
                'earned' => $total > 0 && $filled === $total,
                'filled' => $filled,
                'total' => $total,
            ];
        }

        return $breakdown;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        $profile = $user->candidateProfile;

        return [
            'id' => PublicId::encode('u', $user->id),
            'phone' => $user->phone,
            'name' => $profile?->name ?: $user->name,
            'signed_up_as' => $user->role->value,
            'phone_verified' => $user->phone_verified_at !== null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),

            // The two facets. Both can be populated for the same row — that is
            // the normal case for an engaged account, not an edge case.
            'candidate' => [
                'profile_strength' => (int) ($profile?->profile_strength ?? 0),
                'home_city' => $profile?->home_city,
                'applications' => (int) $user->applications_count,
                'saved_jobs' => (int) $user->saved_jobs_count,
                'has_resume' => filled($profile?->resume_path),
            ],
            'recruiter' => [
                'organisations' => (int) $user->organisations_count,
                'jobs' => (int) $user->job_postings_count,
                'is_active' => $user->organisations_count > 0 || $user->job_postings_count > 0,
            ],
        ];
    }

    private function findUser(string $userId): User
    {
        $id = PublicId::decode('u', $userId);
        $user = $id === null ? null : User::find($id);

        if (! $user) {
            throw new NotFoundHttpException('That account was not found.');
        }

        return $user;
    }
}
