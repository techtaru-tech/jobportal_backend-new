<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Short-lived signed URLs for the files that are not public assets: resumes and
 * intro videos (§9.1) and organisation verification documents (§7.3).
 *
 * These live on the `local` disk (storage/app/private), which has `serve` set
 * in config/filesystems.php — Laravel signs a route to them rather than
 * exposing a guessable path under public/.
 */
class PrivateFiles
{
    public const DISK = 'local';

    /** How long a generated link stays valid. */
    public const TTL_MINUTES = 15;

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return self::disk()->temporaryUrl($path, now()->addMinutes(self::TTL_MINUTES));
    }

    public static function delete(?string $path): void
    {
        if (filled($path) && self::disk()->exists($path)) {
            self::disk()->delete($path);
        }
    }

    /** Public assets — photos and organisation logos — stay on the public disk. */
    public static function publicUrl(?string $path): ?string
    {
        return blank($path) ? null : Storage::disk('public')->url($path);
    }

    public static function deletePublic(?string $path): void
    {
        if (filled($path) && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
