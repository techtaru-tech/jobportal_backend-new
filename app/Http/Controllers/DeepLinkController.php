<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Support\JobShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The web half of deep linking: the two verification files, and the page a
 * shared job link lands on.
 *
 * These are `web` routes, not `/api/v1` ones — the crawlers that build a
 * WhatsApp preview and the OS that verifies an App Link both fetch plain URLs
 * and have no idea what our API envelope is.
 */
class DeepLinkController extends Controller
{
    /**
     * GET /.well-known/assetlinks.json — Android App Links.
     *
     * Android fetches this over HTTPS when the app is installed and grants the
     * app the right to handle our https links only if a listed fingerprint
     * matches the installed APK's signer. Served from a route rather than as a
     * static file so the package and fingerprints stay configuration.
     *
     * With no fingerprints configured this is an empty list, which is a valid
     * document meaning "no app may claim these links" — https links then open
     * the browser and get the landing page below, which still works.
     */
    public function assetLinks(): JsonResponse
    {
        $fingerprints = config('deeplinks.android.sha256_fingerprints');

        if ($fingerprints === []) {
            return response()->json([]);
        }

        return response()->json([
            [
                'relation' => [
                    'delegate_permission/common.handle_all_urls',
                ],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('deeplinks.android.package'),
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ]);
    }

    /**
     * GET /.well-known/apple-app-site-association — iOS Universal Links.
     *
     * Must be served as JSON with no extension and no redirect. `paths` is
     * scoped to the job prefix on purpose: claiming `*` would route every URL
     * on the domain into the app, including any future web pages.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        $appId = (string) config('deeplinks.ios.app_id');
        $path = '/'.trim((string) config('deeplinks.job_path'), '/').'/*';

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => $appId === '' ? [] : [
                    [
                        'appID' => $appId,
                        'paths' => [$path],
                    ],
                ],
            ],
        ]);
    }

    /**
     * GET /{job_path}/{code} — where a shared job link lands.
     *
     * Only reached when the app is *not* installed, or when App Link
     * verification has not happened on that device, or when the fetcher is a
     * link-preview crawler. With a verified install the OS intercepts the URL
     * before a request is ever made.
     *
     * A withdrawn job still renders a page rather than a bare 404: the link
     * may have been forwarded for days, and "this job has closed" is a more
     * useful answer than a server error. The status is still 404 so crawlers
     * and search engines do not index it.
     */
    public function job(string $code): Response
    {
        $job = JobPosting::query()
            ->withOrganisation()
            ->where('code', $code)
            ->first();

        if (! $job || ! $job->isPubliclyVisible()) {
            return response()->view('deeplink.job-gone', [
                'storeUrl' => $this->storeUrl(),
            ], 404);
        }

        return response()->view('deeplink.job', [
            'job' => $job,
            // Tried by the page itself, so a device where verification failed
            // still gets into the app instead of stopping at the browser.
            'appUrl' => JobShareLink::scheme($job),
            'shareUrl' => JobShareLink::web($job),
            'storeUrl' => $this->storeUrl(),
        ]);
    }

    /** Whichever store fits the visitor; Play is the fallback. */
    private function storeUrl(): string
    {
        $agent = (string) request()->userAgent();
        $ios = (string) config('deeplinks.ios.store_url');

        if ($ios !== '' && preg_match('/iPhone|iPad|iPod/i', $agent)) {
            return $ios;
        }

        return (string) config('deeplinks.android.store_url');
    }
}
