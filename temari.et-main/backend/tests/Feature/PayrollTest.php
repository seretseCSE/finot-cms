<?php

use App\Models\Employee;
use App\Models\User;
use App\Support\EthiopianIncomeTax;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function staffWithPay($branch, string $name, float $salary, array $allowances = [], array $deductions = []): Employee
{
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $name, 'is_active' => true,
    ]);
    $employee->positions()->create(['job_title' => 'teacher', 'salary' => $salary, 'is_primary' => true]);
    $employee->syncAllowances($allowances);
    $employee->syncDeductions($deductions);

    return $employee;
}

it('computes Ethiopian income tax per Proclamation 1395/2025', function () {
    expect(EthiopianIncomeTax::tax(2000))->toBe(0.0);
    expect(EthiopianIncomeTax::tax(4000))->toBe(300.0);   // (4000-2000) × 15%
    expect(EthiopianIncomeTax::tax(10000))->toBe(1650.0); // 300 + 600 + 750
    expect(EthiopianIncomeTax::tax(20000))->toBe(4950.0); // 2850 + 6000 × 35%
});

it('creates a draft payroll run with computed payslips and totals', function () {
    $branch = makeBranch();
    staffWithPay($branch, 'Alem', 10000, [['name' => 'Housing Allowance', 'amount' => 2000]], [['name' => 'Loan', 'amount' => 500]]);
    staffWithPay($branch, 'Beza', 5000);
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/payroll-runs', [
            'name' => 'Meskerem 2019 E.C.',
            'period_start' => '2026-09-11',
            'period_end' => '2026-10-10',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(2, 'data.items');

    $alem = collect($response->json('data.items'))->firstWhere('employee_name', 'Alem');
    // Gross 12000 → tax 2250; pension 7% of basic 10000 = 700; net = 12000-2250-700-500.
    expect((float) $alem['gross_pay'])->toBe(12000.0);
    expect((float) $alem['income_tax'])->toBe(2250.0);
    expect((float) $alem['pension_employee'])->toBe(700.0);
    expect((float) $alem['net_pay'])->toBe(8550.0);

    // Overlapping period is rejected.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/payroll-runs', [
            'name' => 'Duplicate', 'period_start' => '2026-09-20', 'period_end' => '2026-10-05',
        ])->assertStatus(422);
});

it('freezes an approved run and walks the approve → paid lifecycle', function () {
    $branch = makeBranch();
    staffWithPay($branch, 'Alem', 8000);
    Sanctum::actingAs(directorOf($branch));

    $id = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/payroll-runs', [
            'name' => 'Tikimt 2019 E.C.', 'period_start' => '2026-10-11', 'period_end' => '2026-11-09',
        ])->json('data.id');

    // Draft recomputes freely.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/payroll-runs/{$id}/recompute")->assertOk();

    // Paid before approved is rejected.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/payroll-runs/{$id}/mark-paid")->assertStatus(422);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/payroll-runs/{$id}/approve")->assertOk()
        ->assertJsonPath('data.status', 'approved');

    // Approved runs are frozen: no recompute, no delete.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/payroll-runs/{$id}/recompute")->assertStatus(422);
    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/payroll-runs/{$id}")->assertStatus(422);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/payroll-runs/{$id}/mark-paid")->assertOk()
        ->assertJsonPath('data.status', 'paid');
});

it('forbids a teacher from viewing payroll and a registrar from managing it', function () {
    $branch = makeBranch();
    Sanctum::actingAs(memberOf($branch)); // teacher

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/payroll-runs')->assertForbidden();
});
