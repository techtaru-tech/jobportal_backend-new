<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * One admin-managed setting. Secrets (API secrets, webhook secrets) are
 * encrypted with APP_KEY before they reach the database and are never sent
 * back to the panel — only whether one is set, and its last four characters.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'is_secret', 'updated_by'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    /**
     * Per-request cache, held by the container (not a static) so it never
     * outlives the application instance it was read in.
     */
    private const CACHE = 'app-settings.cache';

    /** The stored value (decrypted), or null when unset. */
    public static function value(string $key): ?string
    {
        if (! app()->bound(self::CACHE)) {
            app()->instance(self::CACHE, Schema::hasTable('app_settings')
                ? self::query()->get()->keyBy('key')->all()
                : []);
        }
        $row = app(self::CACHE)[$key] ?? null;
        if ($row === null || $row->value === null || $row->value === '') {
            return null;
        }
        if (! $row->is_secret) {
            return $row->value;
        }
        try {
            return Crypt::decryptString($row->value);
        } catch (\Throwable) {
            return null; // APP_KEY changed: treat as unset rather than fail payments
        }
    }

    public static function put(string $key, ?string $value, bool $secret = false, ?int $adminId = null): void
    {
        $stored = $value === null || $value === '' ? null : ($secret ? Crypt::encryptString($value) : $value);
        self::query()->updateOrCreate(['key' => $key], ['value' => $stored, 'is_secret' => $secret, 'updated_by' => $adminId]);
        self::flush();
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::CACHE);
    }
}
