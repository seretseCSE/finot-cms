<?php

namespace App\Models;

use App\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One job a staff member holds (job title + employment type + compensation +
 * dates). An employee may hold several concurrently (director + teacher);
 * `ended_on` null = current. A combined salary covering several job titles
 * sits on the PRIMARY position; the others carry a null salary.
 */
#[Fillable([
    'employee_id', 'job_title', 'employment_type', 'salary_level', 'salary',
    'hired_on', 'last_promoted_on', 'ended_on', 'is_primary',
])]
class EmployeePosition extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'salary_level' => 'integer',
            'salary' => 'decimal:2',
            'hired_on' => 'date',
            'last_promoted_on' => 'date',
            'ended_on' => 'date',
            'is_primary' => 'boolean',
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_on');
    }
}
