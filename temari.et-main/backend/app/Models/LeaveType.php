<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A school's leave policy line (annual, sick, maternity…). Seeded from
 * App\Support\LeavePolicy defaults; schools may adjust or add custom types.
 */
#[Fillable([
    'school_id', 'code', 'name', 'days_per_year',
    'service_bonus_days', 'service_bonus_every_years',
    'is_paid', 'applicable_gender', 'requires_note', 'is_active', 'sort_order',
])]
class LeaveType extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_per_year' => 'float',
            'is_paid' => 'boolean',
            'requires_note' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
