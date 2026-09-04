<?php

namespace App\Models;

use App\Enums\HealthConditionCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Platform seed catalog of known K-12 health conditions. A student's actual
 * conditions live on the student_health_conditions pivot with severity/notes.
 */
#[Fillable(['name', 'category', 'is_active'])]
class HealthCondition extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => HealthConditionCategory::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_health_conditions');
    }
}
