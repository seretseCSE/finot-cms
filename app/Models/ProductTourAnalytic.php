<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTourAnalytic extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'role',
        'panel',
        'event_type',
        'tour_key',
        'step_key',
        'page',
        'device_type',
        'browser',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForTour($query, string $tourKey)
    {
        return $query->where('tour_key', $tourKey);
    }

    public function scopeByEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public static function record(string $eventType, string $tourKey, ?array $data = []): self
    {
        $request = request();

        return static::create([
            'user_id' => auth()->id(),
            'role' => auth()->user()?->roles->first()?->name,
            'panel' => $data['panel'] ?? 'admin',
            'event_type' => $eventType,
            'tour_key' => $tourKey,
            'step_key' => $data['step_key'] ?? null,
            'page' => $data['page'] ?? $request?->path(),
            'device_type' => $data['device_type'] ?? ProductTourAnalytic::detectDeviceType(),
            'browser' => $data['browser'] ?? $request?->userAgent(),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    private static function detectDeviceType(): string
    {
        $ua = request()->userAgent() ?? '';

        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $ua)) {
            return 'tablet';
        }

        return 'desktop';
    }
}
