<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Platform seed rows (school_id NULL) shared by every school, plus
 * school-owned custom categories. Deactivate, never delete, once used.
 */
#[Fillable(['school_id', 'name', 'icon', 'is_active'])]
class InventoryCategory extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Platform rows + the school's own — what an item picker should offer.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId));
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
