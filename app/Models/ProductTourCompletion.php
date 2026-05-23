<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTourCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'panel',
        'tour_key',
        'tour_version',
        'completed_at',
        'skipped_at',
        'progress_step',
        'completion_percentage',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
            'completion_percentage' => 'integer',
            'progress_step' => 'integer',
            'metadata' => 'json',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeSkipped($query)
    {
        return $query->whereNotNull('skipped_at');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForTour($query, string $tourKey)
    {
        return $query->where('tour_key', $tourKey);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isSkipped(): bool
    {
        return $this->skipped_at !== null;
    }

    public function shouldShowTour(string $currentVersion): bool
    {
        if ($this->isSkipped()) {
            return false;
        }

        if ($this->isCompleted() && $this->tour_version === $currentVersion) {
            return false;
        }

        return true;
    }
}
