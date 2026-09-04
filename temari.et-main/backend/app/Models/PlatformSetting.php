<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide operator settings (Temari.et staff only), key → JSONB value.
 * Read through get() (cached per key); set() writes + flushes. First
 * consumer: the notification SMS whitelist (NotificationCatalog).
 */
#[Fillable(['key', 'value'])]
class PlatformSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key): mixed
    {
        return Cache::remember(
            "platform_setting:{$key}",
            now()->addMinutes(10),
            fn (): mixed => self::query()->where('key', $key)->value('value'),
        );
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("platform_setting:{$key}");
    }
}
