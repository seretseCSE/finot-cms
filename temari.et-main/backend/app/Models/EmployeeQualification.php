<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One academic credential of a staff member (BEd, MSc, PGDT…). A person holds
 * many; degree scans attach via EmployeeAttachment.employee_qualification_id.
 */
#[Fillable(['employee_id', 'education_level', 'field_of_study', 'institution', 'graduation_year'])]
class EmployeeQualification extends Model
{
    public const EDUCATION_LEVELS = ['certificate', 'diploma', 'bachelor', 'master', 'phd', 'pgdt', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<EmployeeAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmployeeAttachment::class);
    }
}
