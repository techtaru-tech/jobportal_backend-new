<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Cache;

/**
 * The Play Store QR the site shows instead of a web apply button.
 *
 * Applying happens in the app, not on the site — the flow needs a profile, a
 * resume and Smart Apply's field gating, none of which exist on the web. So the
 * site's job at that moment is to get the person onto their phone, and a QR is
 * how you do that from a desktop screen without asking anyone to type a URL.
 *
 * SVG rather than a PNG: it stays sharp at whatever size the layout gives it,
 * scales for print, weighs less than the equivalent bitmap, and needs no image
 * route — it is inlined straight into the page.
 */
class StoreQr
{
    /**
     * How long a rendered QR is kept.
     *
     * The store URL comes from config and effectively never changes, so this is
     * about not re-encoding the same string on every page view rather than
     * about freshness. Cleared by a config change plus a cache clear, which is
     * already part of deploying.
     */
    private const CACHE_HOURS = 24;

    /** The URL the QR resolves to — Android for now; see config/deeplinks.php. */
    public static function storeUrl(): string
    {
        return (string) config('deeplinks.android.store_url');
    }

    /**
     * The QR as an inline `<svg>` string.
     *
     * `size` is the encoded module size, not the rendered size: the SVG is
     * emitted without width/height so CSS decides how big it appears, which is
     * what lets the same markup serve a 96px card and a 240px dialog.
     */
    public static function svg(?string $url = null): string
    {
        $target = $url ?: self::storeUrl();

        return Cache::remember(
            'store-qr:'.md5($target),
            now()->addHours(self::CACHE_HOURS),
            fn () => self::render($target),
        );
    }

    private static function render(string $target): string
    {
        // endroid/qr-code v6 dropped the fluent `Builder::create()->…` chain for
        // a constructor taking named arguments.
        $result = (new Builder(
            writer: new SvgWriter(),
            data: $target,
            // A phone camera reads a QR off a screen at an angle, in whatever
            // light the room has. High correction costs a denser code and buys
            // back the scans that would otherwise need a second try.
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 240,
            margin: 0,
        ))->build();

        $svg = $result->getString();

        // The writer emits an XML prolog, which is invalid inside an HTML body.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }
}
