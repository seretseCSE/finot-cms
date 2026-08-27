<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A branch facility the timetable can book (lab, gym, library…). Treated as
 * an exclusive resource per (day, period) by the solver and validator.
 */
#[Fillable(['school_id', 'branch_id', 'name', 'type', 'capacity', 'is_active'])]
class Room extends Model
{
    use SoftDeletes;

    public const TYPES = ['classroom', 'lab', 'library', 'ict', 'gym', 'music', 'art', 'hall', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
