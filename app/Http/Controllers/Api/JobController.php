<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\JobResource;
use App\Models\JobPosting;
use App\Services\OptionListService;
use App\Support\ApiResponse;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * §4 Public job browse. Readable without a token; when a candidate token *is*
 * present the saved/applied flags come back too so the list renders in one call.
 */
class JobController extends ApiController
{
    public function __construct(private readonly OptionListService $options) {}

    /** GET /jobs (§4.1) */
    public function index(Request $request): JsonResponse
    {
        $query = JobPosting::query()->publiclyVisible()->withOrganisation();

        if ($category = $request->string('category')->trim()->value()) {
            $query->where('role', $category);
        }

        $query->search($request->string('query')->trim()->value() ?: $request->string('q')->trim()->value());

        $this->applyDeclaredFilters($query, $request);

        $this->attachCandidateState($query, $request);

        // `recommended=1` is what makes the app's "Recommended for you" feed
        // mean anything. Until it existed that heading sat above
        // `latest('posted_at')` — the newest jobs, in the same order for every
        // candidate — while the Preferred jobs screen collected roles, cities,
        // job types and shifts that nothing ever read.
        //
        // A rank, not a filter: a candidate whose preferences match nothing on
        // the board still gets a feed, just not a personalised one. Filtering
        // would have shown them an empty screen and no way to tell why.
        if ($request->boolean('recommended')) {
            $this->applyPreferenceRanking($query, $request);
        }

        $paginator = $query->latest('posted_at')->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, JobResource::class);
    }

    /**
     * Applies whichever of the declared filter groups this request actually
     * sent — see `config('options.job_filters')`.
     *
     * The parameters, the columns they match and how they match are all read
     * from that one declaration, which is also what `GET /config/options`
     * serves to the app. So a group added there starts filtering the moment it
     * is served, with no change here and none in the app; and a parameter
     * nobody declared is ignored rather than reaching the query builder.
     *
     * Two shapes:
     *
     *  - `in`  — any of the picked values (`experience`, `job_type`, `shift`,
     *            `city`). Repeatable, so `city[]=Jaipur&city[]=Kota` is "either".
     *  - `min` — the lowest ₹ threshold picked, matched against the job's
     *            *floor*: `salary_min >= x`, so the job pays at least what the
     *            candidate asked for rather than merely topping out there.
     */
    private function applyDeclaredFilters(Builder $query, Request $request): void
    {
        foreach ($this->options->jobFilterParams() as $param => $spec) {
            if ($spec['type'] === 'min') {
                if ($request->filled($param)) {
                    $query->where($spec['column'], '>=', (int) $request->integer($param));
                }

                continue;
            }

            $values = $this->listParam($request, $param);

            if ($values !== []) {
                $query->whereIn($spec['column'], $values);
            }
        }
    }

    /**
     * Orders [$query] by how well each posting matches the candidate's saved
     * preferences, strongest signal first, before the caller's `posted_at`
     * tiebreak takes over.
     *
     * The weights say which preference a candidate actually chose a job on:
     * the role they do (4) outranks where they want to work (3), which
     * outranks full-time-vs-contract (2), which outranks the shift (1). A job
     * matching role and city therefore beats one matching type and shift.
     *
     * `whereIn`-shaped `CASE` expressions rather than a join: the preferences
     * are JSON columns on the profile, so they arrive as PHP arrays and go in
     * as bindings. An empty preference contributes nothing instead of
     * matching everything.
     */
    private function applyPreferenceRanking(Builder $query, Request $request): void
    {
        $profile = $request->user()?->candidateProfile;

        if (! $profile) {
            return;
        }

        $weighted = [
            ['role', $profile->preferred_roles, 4],
            ['city', $profile->location, 3],
            ['type', $profile->preferred_job_types, 2],
            ['shift', $profile->preferred_shifts, 1],
        ];

        $terms = [];
        $bindings = [];

        foreach ($weighted as [$column, $values, $weight]) {
            $values = array_values(array_filter((array) $values, 'filled'));

            if ($values === []) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $terms[] = "(CASE WHEN {$column} IN ({$placeholders}) THEN {$weight} ELSE 0 END)";
            $bindings = [...$bindings, ...$values];
        }

        if ($terms === []) {
            return;
        }

        $query->orderByRaw(implode(' + ', $terms).' DESC', $bindings);
    }

    /** GET /jobs/{jobId} (§4.2) */
    public function show(Request $request, string $jobId): JsonResponse
    {
        $query = JobPosting::query()->publiclyVisible()->withOrganisation();

        $this->attachCandidateState($query, $request);

        // Accepts either the `j_<id>` public id or the job's `code`.
        //
        // A shared link carries the code (`/j/MC-45530`) because that is what
        // both sides of the app call the job and what reads sensibly in a
        // WhatsApp message. Resolving it here means the deep-link handler can
        // use the same endpoint every other screen does, instead of needing a
        // lookup route of its own.
        $id = PublicId::decode('j', $jobId);

        $job = $id !== null
            ? $query->find($id)
            : $query->where('code', $jobId)->first();

        if (! $job) {
            throw new NotFoundHttpException('That job is no longer available.');
        }

        return ApiResponse::data(new JobResource($job));
    }

    /** GET /jobs/categories (§4.3) */
    public function categories(): JsonResponse
    {
        $counts = JobPosting::query()
            ->publiclyVisible()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        // Seeded categories always appear, even at zero, so the chips are
        // stable. Read through the resolved list so a category an admin adds
        // shows up here too, not only in `/config/options`.
        $names = collect($this->options->list('categories'))
            ->merge($counts->keys())
            ->unique()
            ->values();

        return ApiResponse::data(
            $names->map(fn (string $name) => [
                'name' => $name,
                'job_count' => (int) ($counts[$name] ?? 0),
            ])->all()
        );
    }

    /** GET /jobs/search/suggestions (§4.4) */
    public function suggestions(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();

        if (mb_strlen($term) < 1) {
            return ApiResponse::data([]);
        }

        $titles = JobPosting::query()
            ->publiclyVisible()
            ->selectRaw('title as term, count(*) as aggregate')
            ->where('title', 'like', '%'.$term.'%')
            ->groupBy('title')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->pluck('aggregate', 'term');

        // Curated synonyms fill the gap when nothing is posted under that
        // title — but only the ones that lead somewhere. The count was already
        // being worked out here and then ignored, so the list happily offered
        // terms it knew matched nothing: tapping one was a tap straight into
        // "No jobs found", which reads as a broken search rather than as an
        // empty corner of the board.
        $curated = collect(config('options.search_dictionary'))
            ->filter(fn (string $entry) => str_contains(mb_strtolower($entry), mb_strtolower($term)))
            ->reject(fn (string $entry) => $titles->has($entry))
            ->map(fn (string $entry) => [
                'term' => $entry,
                'job_count' => $this->countMatching($entry),
            ])
            ->filter(fn (array $row) => $row['job_count'] > 0);

        $suggestions = $titles->map(fn (int $count, string $term) => [
            'term' => $term,
            'job_count' => $count,
        ])->values()
            ->merge($curated->values())
            ->take(10)
            ->values();

        return ApiResponse::data($suggestions->all());
    }

    /**
     * GET /jobs/search/trending (§4.5)
     *
     * Derived from what is actually posted — the busiest titles right now.
     * Recent searches stay device-local, so there is no endpoint for them.
     */
    public function trending(): JsonResponse
    {
        $trending = JobPosting::query()
            ->publiclyVisible()
            ->selectRaw('title as term, count(*) as aggregate')
            ->groupBy('title')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['term' => $row->term, 'job_count' => (int) $row->aggregate]);

        return ApiResponse::data($trending->all());
    }

    private function countMatching(string $term): int
    {
        return JobPosting::query()->publiclyVisible()->search($term)->count();
    }

    /** Adds is_saved / has_applied for any signed-in user; no-op for guests. */
    private function attachCandidateState(Builder $query, Request $request): void
    {
        $user = $request->user();

        // Was gated on the account being a candidate, which now means only
        // "the side they signed up on" — a recruiter browsing for work would
        // have seen every job unsaved and un-applied even after saving and
        // applying to them.
        if (! $user) {
            return;
        }

        $query->withExists([
            'savedBy as is_saved' => fn ($q) => $q->where('user_id', $user->id),
            'applications as has_applied' => fn ($q) => $q->where('user_id', $user->id),
        ]);
    }
}
