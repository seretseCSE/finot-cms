<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'student_id', 'parent_id', 'relationship',
    'can_view_grades', 'can_view_attendance', 'can_pay_fees', 'can_receive_sms',
    'is_primary', 'emergency_contact', 'priority_order', 'is_active', 'notes',
])]
class StudentGuardian extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'can_view_grades' => 'boolean',
            'can_view_attendance' => 'boolean',
            'can_pay_fees' => 'boolean',
            'can_receive_sms' => 'boolean',
            'is_primary' => 'boolean',
            'emergency_contact' => 'boolean',
            'priority_order' => 'integer',
            'is_active' => 'boolean',
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
     * @return BelongsTo<ParentProfile, $this>
     */
    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
    }
}
