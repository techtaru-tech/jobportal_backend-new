<?php

namespace App\Support;

use App\Models\Application;

/**
 * Guards a replaced profile file (resume, photo, intro video) from deletion
 * while a frozen application snapshot still points at it (§9.1) — editing or
 * replacing a profile must never change what an employer already received.
 */
class FileRetention
{
    /** Deletes the old private file at `$path`, unless a snapshot still needs it. */
    public static function replacePrivate(?string $path): void
    {
        if (filled($path) && ! self::isReferenced($path)) {
            PrivateFiles::delete($path);
        }
    }

    public static function replacePublic(?string $path): void
    {
        if (filled($path) && ! self::isReferenced($path)) {
            PrivateFiles::deletePublic($path);
        }
    }

    private static function isReferenced(string $path): bool
    {
        return Application::query()
            ->whereNotNull('snapshot_files')
            ->get(['snapshot_files'])
            ->contains(fn (Application $application) => in_array($path, $application->snapshot_files ?? [], true));
    }
}
