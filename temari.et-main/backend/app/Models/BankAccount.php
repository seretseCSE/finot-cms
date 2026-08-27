<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payment collection account. Owned by the SCHOOL so branches can share it;
 * attached to branches via the pivot, each attachment with its own is_active.
 * An account is usable by a branch only when BOTH switches are on.
 */
#[Fillable(['school_id', 'bank_id', 'account_name', 'account_number', 'is_active'])]
class BankAccount extends Model
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
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Payments that landed in this account (snapshot at payment time).
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Fees currently pointing at this account as a collection account.
     *
     * @return BelongsToMany<FeeStructure, $this>
     */
    public function feeStructures(): BelongsToMany
    {
        return $this->belongsToMany(FeeStructure::class, 'fee_structure_bank_account');
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Accounts usable by a branch: school switch on AND branch switch on.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsableByBranch(Builder $query, int $branchId): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('branches', fn ($q) => $q
                ->where('branches.id', $branchId)
                ->where('bank_account_branch.is_active', true));
    }
}
