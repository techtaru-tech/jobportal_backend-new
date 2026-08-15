<?php

namespace App\Support;

/**
 * Reads the duration of an MP4/MOV without an external dependency.
 *
 * §3.13 requires the ≤60s cap to be re-checked server-side because some OEM
 * camera apps ignore the requested cap. Both formats are ISO base media files,
 * so the `moov/mvhd` header carries a timescale and duration — no decoding
 * needed. `ffprobe` is used when present; the parser is the fallback so the
 * check never silently degrades on a box without ffmpeg installed.
 */
class VideoProbe
{
    /** Duration in seconds, or null when it genuinely cannot be determined. */
    public static function durationSeconds(string $path): ?float
    {
        return self::viaFfprobe($path) ?? self::viaAtoms($path);
    }

    private static function viaFfprobe(string $path): ?float
    {
        static $available = null;

        if ($available === null) {
            $available = self::binaryExists('ffprobe');
        }

        if (! $available) {
            return null;
        }

        $command = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>%s',
            escapeshellarg($path),
            self::nullDevice(),
        );

        $output = @shell_exec($command);

        return is_numeric(trim((string) $output)) ? (float) trim((string) $output) : null;
    }

    /**
     * Walks the ISO-BMFF box tree to `moov/mvhd` and reads timescale + duration.
     */
    private static function viaAtoms(string $path): ?float
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $moov = self::findBox($handle, 'moov', 0, filesize($path) ?: 0);

            if ($moov === null) {
                return null;
            }

            [$moovOffset, $moovEnd] = $moov;

            $mvhd = self::findBox($handle, 'mvhd', $moovOffset, $moovEnd);

            if ($mvhd === null) {
                return null;
            }

            [$mvhdOffset] = $mvhd;

            fseek($handle, $mvhdOffset);
            $version = ord(fread($handle, 1) ?: "\0");

            // Skip flags (3 bytes) + creation/modification times.
            fseek($handle, $mvhdOffset + 4 + ($version === 1 ? 16 : 8));

            if ($version === 1) {
                $timescale = self::readUint32($handle);
                $duration = self::readUint64($handle);
            } else {
                $timescale = self::readUint32($handle);
                $duration = self::readUint32($handle);
            }

            if (! $timescale || $duration === null) {
                return null;
            }

            return $duration / $timescale;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{0: int, 1: int}|null [payload offset, box end offset]
     */
    private static function findBox($handle, string $type, int $start, int $end): ?array
    {
        $offset = $start;

        while ($offset + 8 <= $end) {
            fseek($handle, $offset);

            $header = fread($handle, 8);

            if ($header === false || strlen($header) < 8) {
                return null;
            }

            $size = unpack('N', substr($header, 0, 4))[1];
            $boxType = substr($header, 4, 4);
            $payload = $offset + 8;

            if ($size === 1) {
                // 64-bit extended size follows the header.
                $large = fread($handle, 8);

                if ($large === false || strlen($large) < 8) {
                    return null;
                }

                $parts = unpack('Nhigh/Nlow', $large);
                $size = ($parts['high'] << 32) | $parts['low'];
                $payload += 8;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }

            if ($size < 8) {
                return null;
            }

            if ($boxType === $type) {
                return [$payload, min($offset + $size, $end)];
            }

            $offset += $size;
        }

        return null;
    }

    private static function readUint32($handle): ?int
    {
        $bytes = fread($handle, 4);

        return ($bytes === false || strlen($bytes) < 4) ? null : unpack('N', $bytes)[1];
    }

    private static function readUint64($handle): ?int
    {
        $bytes = fread($handle, 8);

        if ($bytes === false || strlen($bytes) < 8) {
            return null;
        }

        $parts = unpack('Nhigh/Nlow', $bytes);

        return ($parts['high'] << 32) | $parts['low'];
    }

    private static function binaryExists(string $binary): bool
    {
        // `command -v` is a POSIX shell builtin cmd.exe does not have, and the
        // /dev/null redirect resolves to a literal \dev\null there — cmd prints
        // "The system cannot find the path specified." to stderr before it even
        // runs the lookup. `@` only suppresses PHP's own diagnostics, not a
        // child process's stderr, so that line leaks to the console on Windows.
        $which = self::isWindows()
            ? @shell_exec('where '.escapeshellarg($binary).' 2>NUL')
            : @shell_exec('command -v '.escapeshellarg($binary).' 2>/dev/null');

        return filled(trim((string) $which));
    }

    private static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /** The platform's null device, for discarding a child process's stderr. */
    private static function nullDevice(): string
    {
        return self::isWindows() ? 'NUL' : '/dev/null';
    }
}
