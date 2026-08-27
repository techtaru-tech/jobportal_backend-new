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
| Applying is deliberately absent: it needs a profile, a resume and Smart
| Apply's field gating, none of which exist on the web. The job page offers the
| app instead — see `StoreQr`.
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
Route::get('/employer/{any?}', fn () => view('site.employer'))
    ->where('any', '.*')
    ->name('site.post-job');

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
