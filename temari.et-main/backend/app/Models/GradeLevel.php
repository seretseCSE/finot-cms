<?php

namespace App\Models;

use App\Enums\Cycle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-seeded national grade levels. No soft deletes — this is fixed
 * reference data, not tenant-owned.
 */
#[Fillable(['code', 'name', 'cycle', 'sort_order', 'has_national_exam'])]
class GradeLevel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle' => Cycle::class,
            'has_national_exam' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
