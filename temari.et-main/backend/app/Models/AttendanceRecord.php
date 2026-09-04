<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'section_id', 'student_id', 'academic_year_id',
    'term_id', 'date', 'status', 'source', 'device_id', 'check_in', 'check_out', 'note', 'recorded_by',
])]
class AttendanceRecord extends Model
{
    use SoftDeletes;

    /**
     * `date` is deliberately NOT cast to a date object: it is a pure calendar day
     * matched by exact equality (and as part of a unique key + updateOrCreate),
     * so it is kept as a 'Y-m-d' string to avoid datetime-format mismatches.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
