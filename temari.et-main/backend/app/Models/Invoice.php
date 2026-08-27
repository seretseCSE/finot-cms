<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'student_id', 'academic_year_id', 'term_id',
    'fee_structure_id', 'title', 'amount', 'amount_paid',
    'discount_type', 'discount_value', 'scholarship_reason', 'fee_concession_id',
    'billing_year', 'billing_month', 'penalty_amount', 'penalty_waived',
    'status', 'due_date',
])]
class Invoice extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'billing_year' => 'integer',
            'billing_month' => 'integer',
            'penalty_amount' => 'decimal:2',
            'penalty_waived' => 'boolean',
            'status' => InvoiceStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * SQL twin of netAmount() for aggregate queries (stats endpoints) — keep
     * the CASE arms in lockstep with the PHP match below.
     */
    public static function netAmountSql(): string
    {
        return <<<'SQL'
            CASE discount_type
                WHEN 'percentage' THEN GREATEST(amount * (1 - LEAST(discount_value, 100) / 100), 0)
                WHEN 'fixed' THEN GREATEST(amount - discount_value, 0)
                WHEN 'full_scholarship' THEN 0
                ELSE amount
            END
            SQL;
    }

    /**
     * The payable amount after any discount/scholarship, clamped to ≥ 0.
     * `amount` stays the gross; payments and status judge against this.
     */
    public function netAmount(): float
    {
        $gross = (float) $this->amount;

        $net = match ($this->discount_type ?? DiscountType::None) {
            DiscountType::Percentage => $gross * (1 - min((float) $this->discount_value, 100) / 100),
            DiscountType::Fixed => $gross - (float) $this->discount_value,
            DiscountType::FullScholarship => 0.0,
            DiscountType::None => $gross,
        };

        return round(max(0, $net), 2);
    }

    /**
     * SQL twin of totalDue() — net amount plus accrued late penalty.
     */
    public static function totalDueSql(): string
    {
        return '('.self::netAmountSql().' + penalty_amount)';
    }

    /**
     * Everything payable on this invoice: the post-discount net PLUS any
     * accrued late penalty. Payments and status judge against this.
     */
    public function totalDue(): float
    {
        return round($this->netAmount() + (float) $this->penalty_amount, 2);
    }

    /**
     * Outstanding balance (net + penalty − amount_paid), never negative.
     *
     * @return Attribute<string, never>
     */
    protected function balance(): Attribute
    {
        return Attribute::get(fn (): string => number_format(
            max(0, $this->totalDue() - (float) $this->amount_paid),
            2,
            '.',
            '',
        ));
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * The standing concession stamped onto this invoice at generation time.
     *
     * @return BelongsTo<FeeConcession, $this>
     */
    public function concession(): BelongsTo
    {
        return $this->belongsTo(FeeConcession::class, 'fee_concession_id');
    }

    /**
     * Parent payment-proof submissions checked against bank records.
     *
     * @return HasMany<PaymentVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }
}
