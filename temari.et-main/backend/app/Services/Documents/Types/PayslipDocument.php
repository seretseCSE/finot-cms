<?php

namespace App\Services\Documents\Types;

use App\Models\GeneratedDocument;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\Documents\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * One employee's payslip from a frozen (approved/paid) payroll run. HR sees
 * every slip; an employee may always fetch their own.
 */
class PayslipDocument extends DocumentType
{
    public function view(): string
    {
        return 'payslip';
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return PayrollItem::with('run', 'employee')->find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        if (! $subject instanceof PayrollItem || $subject->run === null) {
            return false;
        }

        return $subject->employee?->user_id === $user->id
            || $user->hasPermissionForScope('payroll.view', $subject->run->school_id, $subject->run->branch_id);
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var PayrollItem $subject */
        return [
            'school_id' => $subject->run?->school_id,
            'branch_id' => $subject->run?->branch_id,
        ];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var PayrollItem $subject */
        $subject->loadMissing(['run.branch.school:id,name', 'run.branch:id,name,school_id', 'employee:id,first_name,father_name,grandfather_name,public_id,user_id']);

        if (! in_array($subject->run?->status, ['approved', 'paid'], true)) {
            throw ValidationException::withMessages([
                'document' => ['Payslips exist only for approved or paid payroll runs.'],
            ]);
        }

        return [
            'slip' => [
                'reference' => sprintf('PS-%06d', $subject->id),
                'employee' => $subject->employee?->full_name,
                'employee_id' => $subject->employee?->public_id,
                'school' => $subject->run?->branch?->school?->name,
                'branch' => $subject->run?->branch?->name,
                'run_name' => $subject->run?->name,
                'period_start' => $subject->run?->period_start?->toDateString(),
                'period_end' => $subject->run?->period_end?->toDateString(),
                'basic_salary' => (string) $subject->basic_salary,
                'allowances_total' => (string) $subject->allowances_total,
                'gross_pay' => (string) $subject->gross_pay,
                'taxable_income' => (string) $subject->taxable_income,
                'income_tax' => (string) $subject->income_tax,
                'pension_employee' => (string) $subject->pension_employee,
                'pension_employer' => (string) $subject->pension_employer,
                'deductions_total' => (string) $subject->deductions_total,
                'net_pay' => (string) $subject->net_pay,
                'breakdown' => $subject->breakdown,
            ],
        ];
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $item = $document->subject;

        if (! $item instanceof PayrollItem) {
            return [];
        }

        $item->loadMissing(['employee:id,first_name,father_name,grandfather_name,public_id', 'run:id,name,period_start,period_end']);

        // Authenticity only — never pay amounts on the public page.
        return [
            'reference' => sprintf('PS-%06d', $item->id),
            'employee' => $item->employee?->full_name,
            'school' => $document->school?->name,
            'period' => $item->run?->name,
            'issued_on' => $document->created_at?->toDateString(),
        ];
    }
}
