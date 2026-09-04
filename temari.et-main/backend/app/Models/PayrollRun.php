<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One branch × one pay period. Items are recomputed freely while DRAFT and
 * frozen once approved; `*_total` columns cache sums over items.
 */
#[Fillable([
    'school_id', 'branch_id', 'name', 'period_start', 'period_end', 'status', 'notes',
    'gross_total', 'tax_total', 'pension_employee_total', 'pension_employer_total',
    'deduction_total', 'net_total',
    'created_by', 'approved_by', 'approved_at', 'paid_by', 'paid_at',
])]
class PayrollRun extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'pension_employee_total' => 'decimal:2',
            'pension_employer_total' => 'decimal:2',
            'deduction_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    /** Refresh the cached totals from the items table. */
    public function refreshTotals(): void
    {
        $sums = $this->items()
            ->selectRaw('COALESCE(SUM(gross_pay),0) g, COALESCE(SUM(income_tax),0) t,'
                .' COALESCE(SUM(pension_employee),0) pe, COALESCE(SUM(pension_employer),0) pr,'
                .' COALESCE(SUM(deductions_total),0) d, COALESCE(SUM(net_pay),0) n')
            ->first();

        $this->forceFill([
            'gross_total' => $sums->g,
            'tax_total' => $sums->t,
            'pension_employee_total' => $sums->pe,
            'pension_employer_total' => $sums->pr,
            'deduction_total' => $sums->d,
            'net_total' => $sums->n,
        ])->save();
    }
}
