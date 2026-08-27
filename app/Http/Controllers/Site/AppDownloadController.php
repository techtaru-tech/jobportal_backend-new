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

    /**
     * The App Store listing, or null while the app is not published there.
     *
     * Accepts either a full URL or the bare numeric ID, because the number is
     * the awkward half: an App Store URL needs it, and it cannot be derived
     * from the bundle id — only App Store Connect (or the listing's own URL)
     * has it. A full `IOS_STORE_URL` wins, so a country-specific or
     * campaign-tagged link can still be used verbatim.
     *
     * This one value is what turns iOS on everywhere: the buttons, the QR's
     * device routing, and the copy that otherwise says iOS is coming.
     */
    public static function iosUrl(): ?string
    {
        $url = trim((string) config('deeplinks.ios.store_url'));
        if ($url !== '') {
            return $url;
        }

        // Digits only — a pasted "id1234567890" or a stray URL fragment would
        // otherwise build a link that 404s on the store.
        $id = preg_replace('/\D+/', '', (string) config('deeplinks.ios.store_id'));

        return $id === '' ? null : "https://apps.apple.com/app/id{$id}";
    }

    public static function hasIos(): bool
    {
        return self::iosUrl() !== null;
    }
}
