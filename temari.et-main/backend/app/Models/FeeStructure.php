<?php

namespace App\Models;

use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'academic_year_id',
    'name', 'type', 'amount',
    'starts_on', 'due_on', 'billing_day', 'auto_generate',
    'notify_parents', 'notify_students',
    'penalty_type', 'penalty_amount', 'penalty_increment_days',
    'is_active',
])]
class FeeStructure extends Model
{
    use SoftDeletes;

    /**
     * Collection accounts payments on this fee may land in (0..n).
     *
     * @return BelongsToMany<BankAccount, $this>
     */
    public function bankAccounts(): BelongsToMany
    {
        return $this->belongsToMany(BankAccount::class, 'fee_structure_bank_account');
    }

    /**
     * Default account to snapshot when recording a payment without an override:
     * the sole attached account, or null when none / several are configured.
     */
    public function defaultBankAccountId(): ?int
    {
        $ids = $this->relationLoaded('bankAccounts')
            ? $this->bankAccounts->pluck('id')
            : $this->bankAccounts()->pluck('bank_accounts.id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => FeeType::class,
            'starts_on' => 'date',
            'due_on' => 'date',
            'billing_day' => 'integer',
            'auto_generate' => 'boolean',
            'notify_parents' => 'boolean',
            'notify_students' => 'boolean',
            'penalty_amount' => 'decimal:2',
            'penalty_increment_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Ethiopian-month stride between billing periods for the recurring
     * engine; null for every type it never auto-bills — semester fees stay
     * TERM-anchored (generated per term, as today), and registration /
     * one-time / daily / weekly / yearly are issued manually or at
     * enrollment.
     */
    public function monthStride(): ?int
    {
        return match ($this->type) {
            FeeType::Monthly => 1,
            FeeType::Quarterly => 3,
            default => null,
        };
    }

    /** Ethiopian day-of-month a period's invoice falls due (default the 10th). */
    public function effectiveBillingDay(): int
    {
        return max(1, min($this->billing_day ?? 10, 30));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Applicable grades — no rows means the fee applies to every grade.
     *
     * @return BelongsToMany<GradeLevel, $this>
     */
    public function gradeLevels(): BelongsToMany
    {
        return $this->belongsToMany(GradeLevel::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
