<?php

namespace App\Http\Controllers\Site;

use App\Enums\JobPostingStatus;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\ContentPage;
use App\Models\Faq;
use App\Models\JobPosting;
use App\Models\Organisation;
use App\Models\User;
use App\Services\OptionListService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public site.
 *
 * **Server-rendered, unlike the admin panel.** The panel is a tool behind a
 * login and renders itself from the API; these pages are the product's front
 * door and have to be indexable — a job board whose postings Google cannot read
 * is a job board nobody finds. So this reads the models directly (no HTTP
 * round trip to our own API) and ships finished HTML, with Alpine used only for
 * the interactive parts: filters, the mobile menu, the apply dialog.
 *
 * Both surfaces share `partials/design-system`, so there is still exactly one
 * definition of the brand.
 *
 * Every list here goes through `JobPosting::publiclyVisible()` — the same scope
 * the API browse endpoints use, which is what guarantees an unapproved posting
 * or an unverified employer's job cannot leak onto a public page.
 */
class SiteController extends Controller
{
    /** One page of the browse list — the same size the page script asks for. */
    private const JOBS_PER_PAGE = 20;

    public function __construct(private readonly OptionListService $options) {}

    public function home(): View
    {
        return view('site.pages.home', [
            'categories' => $this->categoriesWithCounts(),
            'latest' => JobPosting::query()
                ->publiclyVisible()
                ->withOrganisation()
                ->latest('posted_at')
                ->limit(6)
                ->get(),
            'stats' => $this->stats(),
            'cities' => $this->topCities(),
        ]);
    }

    /**
     * The browse page — a split view (filters · results · the selected job)
     * that the browser drives from here on.
     *
     * The first page of results is rendered on the server from the very same
     * `GET /api/v1/jobs` action the app and the page's script call, so the
     * crawlable list, the shared-link list and the live list cannot disagree
     * about what a filter means. Filters live in the query string with the
     * API's own names (`city[]=`, `job_type[]=`, `min_salary=`), which keeps a
     * filtered view linkable and makes the page's URL the API query.
     *
     * Older links still work: `role` reads as `category`, `type` as
     * `job_type`, and a single `city=Jaipur` as `city[]=Jaipur`.
     */
    public function jobs(Request $request): View
    {
        $groups = $this->options->jobFilterGroups();

        $state = [
            'q' => trim((string) $request->query('q', '')),
            'category' => trim((string) ($request->query('category') ?? $request->query('role') ?? '')),
            'sort' => $request->query('sort') === 'newest' ? 'newest' : 'relevant',
            'page' => max(1, (int) $request->query('page', 1)),
            'job' => (string) $request->query('job', ''),
            'filters' => [],
        ];

        $legacy = ['job_type' => 'type'];
        foreach ($groups as $group) {
            $param = $group['param'];
            $raw = $request->query($param) ?? (isset($legacy[$param]) ? $request->query($legacy[$param]) : null);
            if ($group['type'] === 'min') {
                $state['filters'][$param] = is_scalar($raw) && (int) $raw > 0 ? (string) (int) $raw : '';
            } else {
                $values = is_array($raw) ? $raw : (is_scalar($raw) && $raw !== '' ? [$raw] : []);
                $state['filters'][$param] = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values), 'strlen'));
            }
        }

        $apiQuery = array_filter([
            'query' => $state['q'] ?: null,
            'category' => $state['category'] ?: null,
            'page' => $state['page'],
            'per_page' => self::JOBS_PER_PAGE,
        ] + $state['filters'], fn ($v) => $v !== null && $v !== '' && $v !== []);

        $result = app(JobController::class)
            ->index(Request::create('/api/v1/jobs', 'GET', $apiQuery))
            ->getData(true);

        return view('site.pages.jobs', [
            'initial' => ['list' => $result['data'] ?? [], 'meta' => $result['meta'] ?? []],
            'state' => $state,
            'groups' => $groups,
            'categories' => $this->options->list('categories'),
        ]);
    }

    /**
     * One posting.
     *
     * Addressed by `code` (`MC-45530`), not by an encoded id: that is what both
     * sides of the app call a job, what a shared link already carries, and what
     * reads sensibly in a URL somebody might paste. The page also carries the
     * API's own shape of the job (`apiJob`) so its Apply rail starts from the
     * same data the app sees, before any signed-in extras arrive.
     */
    public function job(string $code): View
    {
        $job = JobPosting::query()
            ->publiclyVisible()
            ->withOrganisation()
            ->where('code', $code)
            ->first();

        if (! $job) {
            // 404 rather than a redirect: a posting that was taken down should
            // not silently become the browse page, and search engines need to
            // hear that the URL is gone.
            throw new NotFoundHttpException('That job posting is no longer available.');
        }

        return view('site.pages.job', [
            'job' => $job,
            'apiJob' => (new JobResource($job))->resolve(Request::create('/api/v1/jobs/'.$job->code)),
            'similar' => JobPosting::query()
                ->publiclyVisible()
                ->withOrganisation()
                ->where('id', '!=', $job->id)
                ->where(fn ($q) => $q->where('role', $job->role)->orWhere('city', $job->city))
                ->latest('posted_at')
                ->limit(4)
                ->get(),
        ]);
    }

    /** Terms, Privacy, About, Contact — the four the app also renders. */
    public function page(string $slug): View
    {
        if (! in_array($slug, ['terms', 'privacy', 'about', 'contact'], true)) {
            throw new NotFoundHttpException('That page does not exist.');
        }

        $page = ContentPage::where('slug', $slug)->first();

        return view('site.pages.content', [
            'page' => $page,
            'slug' => $slug,
            'title' => $page->title ?? ucfirst($slug),
        ]);
    }

    public function faq(): View
    {
        return view('site.pages.faq', [
            'faqs' => Faq::ordered()->where('is_active', true)->get(),
        ]);
    }

    /** The "get the app" page, which the apply dialog also links out to. */
    public function getApp(): View
    {
        return view('site.pages.get-app');
    }

    /**
     * Live category counts, so the chip row cannot advertise a category with
     * nothing behind it.
     *
     * @return list<array{role: string, count: int}>
     */
    private function categoriesWithCounts(): array
    {
        $counts = JobPosting::query()
            ->publiclyVisible()
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        return collect($this->options->list('categories'))
            ->map(fn (string $role) => ['role' => $role, 'count' => (int) ($counts[$role] ?? 0)])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /** @return list<array{city: string, count: int}> */
    private function topCities(): array
    {
        return JobPosting::query()
            ->publiclyVisible()
            ->selectRaw('city, count(*) as aggregate')
            ->groupBy('city')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['city' => (string) $row->city, 'count' => (int) $row->aggregate])
            ->all();
    }

    /**
     * Headline numbers for the hero.
     *
     * Real counts, not decoration — an empty install shows small numbers rather
     * than invented ones, which is the honest thing and also the thing that
     * makes them worth printing once they are large.
     *
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'live_jobs' => JobPosting::where('posting_status', JobPostingStatus::Active->value)->count(),
            'employers' => Organisation::where('verified', true)->count(),
            'candidates' => User::count(),
        ];
    }
}
