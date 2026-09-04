<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's payslip inside a run. `breakdown` snapshots the position,
 * allowance and deduction lines the numbers were computed from, so later HR
 * edits never rewrite payroll history.
 */
#[Fillable([
    'payroll_run_id', 'employee_id', 'basic_salary', 'allowances_total', 'gross_pay',
    'taxable_income', 'income_tax', 'pension_employee', 'pension_employer',
    'deductions_total', 'net_pay', 'breakdown',
])]
class PayrollItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowances_total' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'taxable_income' => 'decimal:2',
            'income_tax' => 'decimal:2',
            'pension_employee' => 'decimal:2',
            'pension_employer' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
