<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One custody spell of one asset unit. Exactly one holder FK is set,
 * matching holder_type; returned_on NULL = the unit is in these hands now.
 */
#[Fillable([
    'school_id', 'branch_id', 'asset_unit_id', 'holder_type', 'employee_id',
    'student_id', 'room_id', 'section_id', 'assigned_on', 'returned_on',
    'return_condition', 'note', 'assigned_by', 'returned_by',
])]
class AssetAssignment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'returned_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AssetUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Display name of whoever holds the unit, whatever their kind. */
    public function holderLabel(): ?string
    {
        return match ($this->holder_type) {
            'employee' => $this->employee?->full_name,
            'student' => $this->student?->full_name,
            'room' => $this->room?->name,
            'section' => $this->section?->name,
            default => null,
        };
    }
}
