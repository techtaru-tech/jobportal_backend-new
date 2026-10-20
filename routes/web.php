<?php

use App\Http\Controllers\DeepLinkController;
use App\Http\Controllers\Site;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
|
| Server-rendered, unlike the admin panel. These pages are the product's front
| door and have to be indexable — a job board whose postings Google cannot read
| is a job board nobody finds — so they render finished HTML from the models and
| use Alpine only for the interactive parts.
|
| Named routes throughout, because the layout and the job cards link to each
| other constantly and hard-coded paths are what makes a URL change a hunt.
|
| Applying happens in the web app (`/app`, below): every Apply button here
| links to `/app#/apply/j_<id>`, which signs the visitor in, gates on the
| profile exactly as the app does, and submits. The app download stays on
| offer beside it — see `StoreQr`.
*/
Route::controller(Site\SiteController::class)->group(function () {
    Route::get('/', 'home')->name('site.home');
    Route::get('/jobs', 'jobs')->name('site.jobs');
    Route::get('/get-app', 'getApp')->name('site.get-app');
    Route::get('/faq', 'faq')->name('site.faq');

    // Below the fixed paths, so `/jobs` and `/faq` are never swallowed by them.
    Route::get('/jobs/{code}', 'job')->name('site.job');
    Route::get('/p/{slug}', 'page')->name('site.page');
});

/*
|--------------------------------------------------------------------------
| Employer area
|--------------------------------------------------------------------------
|
| Behind a sign-in, so unlike the public pages it is client-rendered from the
| API — the same shape as the admin panel, for the same reason: nothing here
| needs indexing, and the recruiter endpoints already exist and already enforce
| their own rules (ownership, the plan's posting ceiling, the approval queue).
|
| Signing in is phone + OTP, exactly as in the app: one account, one identity,
| whichever surface it is used from.
|
| The wildcard is so a refresh inside the area still serves the shell rather
| than a 404; the page switches views itself.
*/
/*
|--------------------------------------------------------------------------
| App download
|--------------------------------------------------------------------------
|
| What every QR on this site resolves to. It reads the User-Agent and forwards
| to Play or the App Store, so one code serves both platforms instead of asking
| a visitor which of two codes is for their own phone — and iPhones start
| working the day IOS_STORE_URL is set, with nothing reprinted.
|
| Its own route rather than part of the site controller group: this URL ends up
| on posters and cards, so it should never move.
*/
Route::get('/get-app/go', Site\AppDownloadController::class)->name('site.app-download');

/*
|--------------------------------------------------------------------------
| Account and employer dashboard
|--------------------------------------------------------------------------
|
| Everything the app does, as website pages: a job seeker's profile,
| applications, saved jobs, alerts and messages under /account, and the
| hiring side under /employer.
|
| The pages are shells — the data is the signed-in visitor's own, so it is
| fetched in the browser from `/api/v1` with the same bearer token the app
| uses (js/site/core.js). Every rule (Smart Apply's gate, ownership,
| approvals, plans and credits) stays in the API, enforced in one place.
| Sign-in is a popup on any page; nothing here redirects to a login screen.
*/
Route::prefix('account')->name('account.')->group(function () {
    Route::view('/', 'site.account.profile')->name('profile');
    Route::view('/setup', 'site.account.setup')->name('setup');
    Route::view('/resume', 'site.account.resume')->name('resume');
    Route::view('/applications', 'site.account.applications')->name('applications');
    Route::get('/applications/{id}', fn (string $id) => view('site.account.application', ['applicationId' => $id]))->name('application');
    Route::view('/saved', 'site.account.saved')->name('saved');
    Route::view('/alerts', 'site.account.alerts')->name('alerts');
    Route::view('/messages', 'site.account.messages')->name('messages');
    Route::view('/notifications', 'site.account.notifications')->name('notifications');
    Route::view('/plan', 'site.account.plan')->name('plan');
});

Route::prefix('employer')->name('employer.')->group(function () {
    Route::view('/', 'site.employer.dashboard')->name('dashboard');
    Route::view('/jobs', 'site.employer.jobs')->name('jobs');
    Route::get('/jobs/new', fn () => view('site.employer.post', ['jobId' => null]))->name('post');
    Route::get('/jobs/{id}/edit', fn (string $id) => view('site.employer.post', ['jobId' => $id]))->name('edit');
    Route::get('/jobs/{id}/checkout', fn (string $id) => view('site.employer.checkout', ['jobId' => $id]))->name('checkout');
    Route::get('/jobs/{id}', fn (string $id) => view('site.employer.job', ['jobId' => $id]))->name('job');
    Route::view('/companies', 'site.employer.companies')->name('companies');
    Route::view('/messages', 'site.employer.messages')->name('messages');
    Route::view('/plan', 'site.employer.plan')->name('plan');
    Route::view('/settings', 'site.employer.settings')->name('settings');
});

// Old names and paths, kept working.
Route::get('/post-job', fn () => redirect()->route('employer.post'))->name('site.post-job');
Route::get('/app', fn () => redirect()->route('account.profile'));

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| A single Blade view that boots an Alpine.js SPA (see resources/js/admin
| bundled at public/js/admin.js). It talks to /api/v1/admin/* on the same
| origin — no separate app, no CORS setup needed. The wildcard exists so a
| refresh on any in-app "route" (e.g. /admin#jobs) still serves the shell
| instead of a 404; the panel does its own client-side view switching.
*/
Route::get('/admin/{any?}', function () {
    return view('admin.app');
})->where('any', '.*');

/*
|--------------------------------------------------------------------------
| Deep links
|--------------------------------------------------------------------------
|
| Web routes, not API ones: an OS verifying an App Link and a WhatsApp crawler
| building a preview both fetch plain URLs and know nothing about the /api/v1
| envelope. See config/deeplinks.php for how the pieces fit together.
|
*/

/*
| The two verification documents. Both must be served over HTTPS, from the
| apex of the domain the links use, with no redirect — a 301 to www is enough
| to make Android and iOS give up silently.
*/
Route::get('/.well-known/assetlinks.json', [DeepLinkController::class, 'assetLinks']);
Route::get('/.well-known/apple-app-site-association', [DeepLinkController::class, 'appleAppSiteAssociation']);

/*
| Where a shared job lands when the app isn't installed. The path prefix is
| configurable, so it is read from config rather than written twice.
*/
Route::get(
    '/'.trim((string) config('deeplinks.job_path', 'j'), '/').'/{code}',
    [DeepLinkController::class, 'job'],
)->name('deeplink.job');
