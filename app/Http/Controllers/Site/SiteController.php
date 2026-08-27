<?php

namespace App\Http\Controllers\Site;

use App\Enums\JobPostingStatus;
use App\Http\Controllers\Controller;
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
     * The browse page.
     *
     * Filters live in the query string rather than in component state so a
     * filtered view can be linked, shared and indexed — the same reason the
     * admin panel keeps its own filters in the URL.
     */
    public function jobs(Request $request): View
    {
        $query = JobPosting::query()->publiclyVisible()->withOrganisation();

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $query->search($term);
        }

        foreach (['role' => 'role', 'city' => 'city', 'type' => 'type', 'shift' => 'shift'] as $param => $column) {
            if ($value = trim((string) $request->query($param, ''))) {
                $query->where($column, $value);
            }
        }

        if ($request->filled('min_salary')) {
            // The job's floor must clear the candidate's floor, not merely its
            // ceiling — same rule as §4.1 on the API.
            $query->where('salary_min', '>=', (int) $request->integer('min_salary'));
        }

        $sort = match ($request->query('sort')) {
            'salary' => ['salary_max', 'desc'],
            'oldest' => ['posted_at', 'asc'],
            default => ['posted_at', 'desc'],
        };

        return view('site.pages.jobs', [
            'jobs' => $query->orderBy($sort[0], $sort[1])->paginate(12)->withQueryString(),
            'filters' => [
                'q' => $term,
                'role' => $request->query('role', ''),
                'city' => $request->query('city', ''),
                'type' => $request->query('type', ''),
                'shift' => $request->query('shift', ''),
                'min_salary' => $request->query('min_salary', ''),
                'sort' => $request->query('sort', ''),
            ],
            'options' => [
                'roles' => $this->options->list('categories'),
                'cities' => $this->options->list('cities'),
                'types' => $this->options->list('job_types'),
                'shifts' => $this->options->list('shifts'),
            ],
        ]);
    }

    /**
     * One posting.
     *
     * Addressed by `code` (`MC-45530`), not by an encoded id: that is what both
     * sides of the app call a job, what a shared link already carries, and what
     * reads sensibly in a URL somebody might paste.
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
