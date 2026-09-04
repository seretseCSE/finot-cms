<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A school's cashbook category (kind: expense|income). Referenced rows are
 * deactivated, never deleted.
 */
#[Fillable(['school_id', 'kind', 'name', 'is_active'])]
class FinanceCategory extends Model
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
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<OtherIncome, $this>
     */
    public function otherIncomes(): HasMany
    {
        return $this->hasMany(OtherIncome::class);
    }
}
