<?php

use App\Http\Controllers\DeepLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
