<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff document (credentials, ID, contract…) stored privately on R2. Only
 * the storage path is persisted; access always goes through signed URLs. May
 * additionally anchor to one position (contract) or qualification (degree scan).
 */
#[Fillable(['employee_id', 'employee_position_id', 'employee_qualification_id', 'name', 'path', 'mime_type', 'size'])]
class EmployeeAttachment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
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
     * @return BelongsTo<EmployeePosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(EmployeePosition::class, 'employee_position_id');
    }

    /**
     * @return BelongsTo<EmployeeQualification, $this>
     */
    public function qualification(): BelongsTo
    {
        return $this->belongsTo(EmployeeQualification::class, 'employee_qualification_id');
    }

    /** Short-lived signed URL — staff documents are never public. */
    public function url(): ?string
    {
        return s3Url($this->path);
    }
}
