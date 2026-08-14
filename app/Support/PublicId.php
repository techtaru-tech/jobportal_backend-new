<?php

namespace App\Support;

/**
 * The app's mock data uses prefixed string ids ("j_501", "edu_1", "u_123"), and
 * it echoes them straight back on writes. Ids are emitted in that shape and
 * accepted either prefixed or bare, so route params stay forgiving.
 */
class PublicId
{
    public static function encode(string $prefix, int|string $id): string
    {
        return $prefix.'_'.$id;
    }

    public static function decode(string $prefix, int|string|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if (str_starts_with($value, $prefix.'_')) {
            $value = substr($value, strlen($prefix) + 1);
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
