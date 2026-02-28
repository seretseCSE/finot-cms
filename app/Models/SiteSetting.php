<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'group',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general', ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
                'group' => $group,
            ]
        );
    }

    /**
     * Get settings by group.
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->toArray();
    }

    /**
     * Get all settings as key-value pairs.
     */
    public static function getAll(): array
    {
        return static::all()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->toArray();
    }
}
