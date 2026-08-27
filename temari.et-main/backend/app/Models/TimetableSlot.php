<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled lesson cell of a timetable version. Times derive from the
 * term's period schedule (term_periods) via `period_number` — never stored
 * here. `is_locked` pins the slot through solver regenerations.
 */
#[Fillable([
    'timetable_version_id', 'subject_assignment_id', 'room_id',
    'day_of_week', 'period_number', 'is_locked',
])]
class TimetableSlot extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'period_number' => 'integer',
            'is_locked' => 'boolean',
        ];
    }

    /** @return BelongsTo<TimetableVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TimetableVersion::class, 'timetable_version_id');
    }

    /** @return BelongsTo<SubjectAssignment, $this> */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
