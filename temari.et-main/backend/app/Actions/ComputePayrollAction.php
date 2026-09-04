<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Support\EthiopianIncomeTax;
use Illuminate\Support\Facades\DB;

/**
 * (Re)computes every payslip of a DRAFT run from the branch's current HR data:
 * basic = sum of active position salaries, gross = basic + allowances, tax per
 * EthiopianIncomeTax (allowances taxed with basic), pension 7%/11% on basic,
 * then recurring deductions. Each item snapshots its source lines so the run
 * stays auditable after HR edits. One bulk delete + insert — no per-row chatter.
 */
class ComputePayrollAction
{
    public function execute(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run): PayrollRun {
            $employees = Employee::query()
                ->where('branch_id', $run->branch_id)
                ->where('is_active', true)
                ->whereHas('positions', fn ($q) => $q->whereNull('ended_on'))
                ->with(['activePositions', 'allowances', 'deductions'])
                ->orderBy('first_name')
                ->get();

            $run->items()->delete();

            $now = now();
            $rows = $employees->map(function (Employee $employee) use ($run, $now): array {
                $basic = (float) $employee->activePositions->sum(fn ($p) => (float) ($p->salary ?? 0));
                $allowances = (float) $employee->allowances->sum(fn ($a) => (float) $a->amount);
                $deductions = (float) $employee->deductions->sum(fn ($d) => (float) $d->amount);

                $gross = round($basic + $allowances, 2);
                $tax = EthiopianIncomeTax::tax($gross);
                $pensionEmployee = EthiopianIncomeTax::employeePension($basic);
                $pensionEmployer = EthiopianIncomeTax::employerPension($basic);
                $net = round($gross - $tax - $pensionEmployee - $deductions, 2);

                return [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $basic,
                    'allowances_total' => $allowances,
                    'gross_pay' => $gross,
                    'taxable_income' => $gross,
                    'income_tax' => $tax,
                    'pension_employee' => $pensionEmployee,
                    'pension_employer' => $pensionEmployer,
                    'deductions_total' => $deductions,
                    'net_pay' => $net,
                    'breakdown' => json_encode([
                        'positions' => $employee->activePositions->map(fn ($p) => [
                            'job_title' => $p->job_title,
                            'employment_type' => $p->employment_type?->value,
                            'salary' => (float) ($p->salary ?? 0),
                            'is_primary' => $p->is_primary,
                        ])->values(),
                        'allowances' => $employee->allowances->map(fn ($a) => [
                            'name' => $a->name, 'amount' => (float) $a->amount,
                        ])->values(),
                        'deductions' => $employee->deductions->map(fn ($d) => [
                            'name' => $d->name, 'amount' => (float) $d->amount,
                        ])->values(),
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('payroll_items')->insert($chunk->all());
            }

            $run->refreshTotals();

            return $run;
        });
    }
}
