<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One link — and therefore one QR — that lands on the right store.
 *
 * The alternative was printing two QR codes side by side and asking the visitor
 * to know which one is theirs, which is a question nobody should have to answer
 * about their own phone. This route reads the User-Agent and forwards, so a
 * poster, a business card and a page all carry the same code forever.
 *
 * It also means iOS costs nothing to switch on later: set `IOS_STORE_URL` and
 * every QR already in the wild starts working for iPhones. Until then an iPhone
 * is sent back to the download page, which says plainly that iOS is not out yet
 * rather than dropping somebody on an Android store listing they cannot install.
 */
class AppDownloadController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $agent = (string) $request->userAgent();

        if (self::isIos($agent)) {
            $ios = self::iosUrl();

            return $ios !== null
                ? redirect()->away($ios)
                // `?ios=1` so the page can explain *why* they were bounced,
                // instead of looking like the QR simply did nothing.
                : redirect()->route('site.get-app', ['ios' => 1]);
        }

        // Everything else gets Play. A desktop scanning its own screen is not a
        // real case, but a desktop *clicking* the button is — and the Play
        // listing is readable there, so it is a better landing than a refusal.
        return redirect()->away(self::androidUrl());
    }

    /**
     * iPad reports "Macintosh" in desktop-mode Safari, so `iPad` alone misses
     * it — but a real Mac has no touch, which is what `Mobile/` distinguishes.
     */
    private static function isIos(string $agent): bool
    {
        if (preg_match('/iPhone|iPod/i', $agent) === 1) {
            return true;
        }

        return preg_match('/iPad/i', $agent) === 1
            || (preg_match('/Macintosh/i', $agent) === 1 && str_contains($agent, 'Mobile/'));
    }

    public static function androidUrl(): string
    {
        return (string) config('deeplinks.android.store_url');
    }

    /** Null until `IOS_STORE_URL` is set — the app is not on the App Store yet. */
    public static function iosUrl(): ?string
    {
        $url = trim((string) config('deeplinks.ios.store_url'));

        return $url === '' ? null : $url;
    }

    public static function hasIos(): bool
    {
        return self::iosUrl() !== null;
    }
}
