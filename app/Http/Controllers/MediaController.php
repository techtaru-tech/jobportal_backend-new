<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /media/{path} — profile photos and company logos, served by the app
 * itself from the public disk.
 *
 * Before this they were linked as /storage/…, which only works when the web
 * server follows the public/storage symlink. On the live server it does not:
 * every /storage/ URL answers 403, so a photo uploaded fine but never showed
 * (the website's "Take photo" looked broken, avatars fell back to initials).
 * Serving them here removes that dependency on the server's configuration.
 *
 * Only the two public upload folders are reachable, and only image files.
 */
class MediaController extends Controller
{
    /** Folders on the public disk that hold public images. */
    private const ALLOWED = ['photos/', 'organisations/'];

    private const TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function show(string $path): Response
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        $allowedFolder = collect(self::ALLOWED)->contains(fn (string $p) => str_starts_with($path, $p));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_if(
            str_contains($path, '..') || ! $allowedFolder || ! isset(self::TYPES[$extension]),
            404,
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        // A path is never reused — a new upload gets a new random name — so
        // the file at a given URL never changes and can be cached hard.
        return $disk->response($path, null, [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
