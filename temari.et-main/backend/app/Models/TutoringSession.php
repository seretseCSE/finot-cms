<?php

namespace App\Models;

use App\Enums\TutoringSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One lesson inside a cycle (see the migration). Only confirmed sessions
 * carry value into a release; a rejected dispute returns the session to
 * confirmed, an upheld one cancels its value.
 */
#[Fillable([
    'cycle_id', 'engagement_id', 'scheduled_at', 'duration_hours', 'topic',
    'status', 'meeting_url', 'logged_at', 'confirmed_at', 'confirmed_by',
    'dispute_reason', 'disputed_at', 'resolution', 'resolved_by', 'resolved_at',
    'notes',
])]
class TutoringSession extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TutoringSessionStatus::class,
            'scheduled_at' => 'datetime',
            'duration_hours' => 'decimal:2',
            'logged_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'disputed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TutoringCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(TutoringCycle::class, 'cycle_id');
    }

    /**
     * @return BelongsTo<TutoringEngagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(TutoringEngagement::class, 'engagement_id');
    }
}
