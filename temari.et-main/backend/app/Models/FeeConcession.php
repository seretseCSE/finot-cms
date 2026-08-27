<?php

namespace App\Models;

use App\Enums\ConcessionCategory;
use App\Enums\ConcessionStatus;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A standing discount/scholarship policy for a student or a guardian (covering
 * all their children). Resolved at invoice GENERATION time — best single
 * concession wins, no stacking — and stamped onto the invoice, so revoking one
 * never rewrites billed history. See FeeConcessionResolver.
 */
#[Fillable([
    'school_id', 'branch_id', 'student_id', 'parent_id',
    'category', 'discount_type', 'discount_value', 'fee_types',
    'academic_year_id', 'term_id',
    'status', 'source', 'reason',
    'requested_by', 'approved_by', 'approved_at', 'revoked_at',
])]
class FeeConcession extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ConcessionCategory::class,
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'fee_types' => 'array',
            'status' => ConcessionStatus::class,
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * The ETB value this concession takes off a gross amount.
     */
    public function discountOn(float $gross): float
    {
        return round(match ($this->discount_type) {
            DiscountType::Percentage => $gross * min((float) $this->discount_value, 100) / 100,
            DiscountType::Fixed => min((float) $this->discount_value, $gross),
            DiscountType::FullScholarship => $gross,
            DiscountType::None => 0.0,
        }, 2);
    }

    /**
     * Approved concessions only.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ConcessionStatus::Active->value);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<ParentProfile, $this>
     */
    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
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
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Invoices this concession was stamped onto at generation time.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
