<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An UNAVAILABLE window for a teacher (whole weekday, or a period range).
 * Availability is the default; these rows are the exceptions the timetable
 * treats as hard constraints.
 */
#[Fillable(['employee_id', 'day_of_week', 'from_period', 'to_period', 'note'])]
class TeacherAvailability extends Model
{
    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Does this window block the given (day, period) cell? */
    public function blocks(int $day, int $period): bool
    {
        if ($this->day_of_week !== $day) {
            return false;
        }

        if ($this->from_period === null && $this->to_period === null) {
            return true;
        }

        return $period >= ($this->from_period ?? 1)
            && $period <= ($this->to_period ?? PHP_INT_MAX);
    }
}
