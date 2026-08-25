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
    /** GET /jobs (§4.1) */
    public function index(Request $request): JsonResponse
    {
        $query = JobPosting::query()->publiclyVisible()->withOrganisation();

        if ($category = $request->string('category')->trim()->value()) {
            $query->where('role', $category);
        }

        $query->search($request->string('query')->trim()->value() ?: $request->string('q')->trim()->value());

        if ($city = $request->string('city')->trim()->value()) {
            $query->where('city', $city);
        }

        foreach (['experience' => 'experience', 'job_type' => 'type', 'shift' => 'shift'] as $param => $column) {
            $values = $this->listParam($request, $param);

            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }

        if ($request->filled('min_salary')) {
            // §4.1 specifies this filters on salary_min: the job's floor must
            // clear the candidate's floor, not merely its ceiling.
            $query->where('salary_min', '>=', (int) $request->integer('min_salary'));
        }

        $this->attachCandidateState($query, $request);

        $paginator = $query->latest('posted_at')->paginate($this->perPage($request));

        return ApiResponse::paginated($paginator, JobResource::class);
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
        $names = collect(app(OptionListService::class)->list('categories'))
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

        // Curated synonyms fill the gap when nothing is posted under that title.
        $curated = collect(config('options.search_dictionary'))
            ->filter(fn (string $entry) => str_contains(mb_strtolower($entry), mb_strtolower($term)))
            ->reject(fn (string $entry) => $titles->has($entry));

        $suggestions = $titles->map(fn (int $count, string $term) => [
            'term' => $term,
            'job_count' => $count,
        ])->values()
            ->merge($curated->map(fn (string $entry) => [
                'term' => $entry,
                'job_count' => $this->countMatching($entry),
            ]))
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
