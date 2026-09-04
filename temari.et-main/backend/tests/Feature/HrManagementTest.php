<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeavePolicy;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    // The file's fixture dates (2026-07-13…) sit one week ahead of this
    // anchor: self-service leave must start today or later, so the clock is
    // frozen to keep them "upcoming" forever.
    $this->travelTo('2026-07-06 09:00:00');
});

function hrEmployee($branch, string $name, ?User $user = null, array $attributes = []): Employee
{
    $employee = Employee::create([
        'user_id' => ($user ?? User::factory()->create())->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $name, 'is_active' => true,
        ...$attributes,
    ]);
    $employee->positions()->create([
        'job_title' => 'teacher', 'salary' => 8000, 'is_primary' => true, 'hired_on' => '2020-09-01',
    ]);

    return $employee;
}

it('computes the Ethiopian leave year and service-aware entitlement', function () {
    // 2026-07-09 falls in the E.C. year that began 2025-09-11.
    $year = LeavePolicy::leaveYear(CarbonImmutable::parse('2026-07-09'));
    expect($year['start']->toDateString())->toBe('2025-09-11');
    expect($year['end']->toDateString())->toBe('2026-09-10');

    // Meskerem 1 shifts to Sep 12 before a Gregorian leap year.
    expect(LeavePolicy::meskerem1(2027)->toDateString())->toBe('2027-09-12');
});

it('grows annual leave with cumulative service per Labour Proclamation 1156/2019 Art. 77', function () {
    $branch = makeBranch();
    LeavePolicy::provisionDefaults($branch->school);
    $annual = LeaveType::where('school_id', $branch->school_id)->where('code', 'annual')->firstOrFail();
    $asOf = CarbonImmutable::parse('2026-07-09');

    // The Art. 77(1) ladder: 16 working days for service years 1–2, then one
    // extra day per additional two years of service.
    foreach ([
        '2025-11-01' => 16.0, // 1st service year
        '2024-09-01' => 16.0, // 2nd
        '2023-09-01' => 17.0, // 3rd
        '2020-09-01' => 18.0, // 6th
        '2016-09-01' => 20.0, // 10th
    ] as $hiredOn => $days) {
        $employee = hrEmployee($branch, 'Hired '.$hiredOn);
        $employee->positions()->update(['hired_on' => $hiredOn]);
        expect(LeavePolicy::entitlement($annual, $employee, $asOf))->toBe($days, "hired {$hiredOn}");
    }

    // A branch move never resets the clock: the veteran gets a NEW per-branch
    // HR file, but service is cumulative across the school (the organization).
    $east = $branch->school->branches()->create(['name' => 'East', 'code' => 'AA-0009']);
    $person = User::factory()->create();
    hrEmployee($branch, 'Veteran Main', $person); // hired 2020-09-01 at Main
    $moved = hrEmployee($east, 'Veteran East', $person);
    $moved->positions()->update(['hired_on' => '2026-01-05']); // re-hired at East
    expect(LeavePolicy::entitlement($annual, $moved, $asOf))->toBe(18.0);

    // …but service at a DIFFERENT school never bleeds in.
    $elsewhere = makeBranch('BB-0001');
    $other = hrEmployee($elsewhere, 'Elsewhere', $person);
    $other->positions()->update(['hired_on' => '2010-09-01']);
    expect(LeavePolicy::entitlement($annual, $moved, $asOf))->toBe(18.0);
});

it('provisions the statutory leave catalog on first read', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/leave-types')
        ->assertOk();

    $codes = collect($response->json('data'))->pluck('code');
    expect($codes)->toContain('annual', 'sick', 'maternity', 'paternity');

    $annual = collect($response->json('data'))->firstWhere('code', 'annual');
    expect((float) $annual['days_per_year'])->toBe(16.0);
    expect($annual['service_bonus_every_years'])->toBe(2);
});

it('walks the leave request lifecycle: submit as staff, approve as director', function () {
    $branch = makeBranch();
    $teacherUser = memberOf($branch);
    hrEmployee($branch, 'Alem', $teacherUser);

    // Provision the catalog.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();
    $typeId = LeaveType::where('code', 'annual')->value('id');

    // The teacher submits leave for THEMSELVES (Mon–Fri = 5 working days).
    Sanctum::actingAs($teacherUser);
    $id = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'leave_type_id' => $typeId,
            'start_date' => '2026-07-13', 'end_date' => '2026-07-17',
            'reason' => 'Family visit',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');
    expect((float) $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/hr/leave-requests/{$id}")->json('data.days'))->toBe(5.0);

    // Overlapping submission is rejected.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'leave_type_id' => $typeId,
            'start_date' => '2026-07-16', 'end_date' => '2026-07-20',
        ])->assertStatus(422);

    // The teacher cannot approve their own request.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/leave-requests/{$id}/approve")->assertForbidden();

    // The director approves it.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/leave-requests/{$id}/approve")->assertOk()
        ->assertJsonPath('data.status', 'approved');

    // The balance reflects the 5 approved days. Hired 2020-09-01 → 5 full
    // service years by 2026-07-09 (6th service year), so Art. 77 entitles
    // 16 + intdiv(5, 2) = 18 days.
    Sanctum::actingAs($teacherUser);
    $balances = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/leave-balances?date=2026-07-09')->assertOk()->json('data');
    $annual = collect($balances[0]['balances'])->firstWhere('leave_type_code', 'annual');
    expect((float) $annual['entitled'])->toBe(18.0);
    expect((float) $annual['taken'])->toBe(5.0);
    expect((float) $annual['remaining'])->toBe(13.0);
});

it('lets a manager request their own leave without an employee_id (My HR)', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    $own = hrEmployee($branch, 'Director Alem', $director);
    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();

    // No employee_id in the payload — resolves to the requester's own profile.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'leave_type_id' => LeaveType::where('code', 'annual')->value('id'),
            'start_date' => '2026-07-13', 'end_date' => '2026-07-14',
        ])
        ->assertCreated()
        ->assertJsonPath('data.employee_id', $own->id);
});

it('blocks approval beyond the balance unless explicitly overridden', function () {
    $branch = makeBranch();
    $employee = hrEmployee($branch, 'Beza');
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();

    // Shrink the annual entitlement to 2 days to force the breach.
    $type = LeaveType::where('code', 'annual')->first();
    $type->update(['days_per_year' => 2, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0]);

    $id = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-07-13', 'end_date' => '2026-07-17',
        ])->assertCreated()->json('data.id');

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/leave-requests/{$id}/approve")->assertStatus(422);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/leave-requests/{$id}/approve", ['allow_exceeding_balance' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('excludes weekends and holidays from consumed leave days', function () {
    $branch = makeBranch();
    $employee = hrEmployee($branch, 'Chaltu');
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();

    Holiday::create([
        'school_id' => $branch->school_id, 'name' => 'Eid al-Adha', 'date' => '2026-07-15',
    ]);

    // Mon 13 → Mon 20: weekend (18/19) + holiday (15) excluded = 5 days.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::where('code', 'annual')->value('id'),
            'start_date' => '2026-07-13', 'end_date' => '2026-07-20',
        ])
        ->assertCreated()
        ->assertJsonPath('data.days', 5);
});

it('records staff attendance and overlays approved leave on the roster', function () {
    $branch = makeBranch();
    $alem = hrEmployee($branch, 'Alem');
    $beza = hrEmployee($branch, 'Beza');
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();

    // Approve leave for Beza covering today.
    $id = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $beza->id,
            'leave_type_id' => LeaveType::where('code', 'annual')->value('id'),
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ])->json('data.id');
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/leave-requests/{$id}/approve", ['allow_exceeding_balance' => true])->assertOk();

    // Mark Alem present.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/attendance', [
            'date' => now()->toDateString(),
            'records' => [
                ['employee_id' => $alem->id, 'status' => 'present', 'check_in' => '08:05'],
            ],
        ])->assertOk();

    $roster = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/attendance?date='.now()->toDateString())
        ->assertOk()
        ->json('data');

    $alemRow = collect($roster)->firstWhere('employee_id', $alem->id);
    $bezaRow = collect($roster)->firstWhere('employee_id', $beza->id);
    expect($alemRow['status'])->toBe('present');
    expect($alemRow['check_in'])->toBe('08:05');
    expect($bezaRow['status'])->toBeNull();
    expect($bezaRow['on_leave']['leave_type_name'])->toBe('Annual leave');
});

it('produces the HR attendance report with per-employee aggregates', function () {
    $branch = makeBranch();
    $alem = hrEmployee($branch, 'Alem');
    Sanctum::actingAs(directorOf($branch));

    foreach ([['present', -2], ['late', -1], ['absent', 0]] as [$status, $offset]) {
        $this->withHeaders(branchContext($branch))
            ->postJson('/api/v1/hr/attendance', [
                'date' => now()->addDays($offset)->toDateString(),
                'records' => [['employee_id' => $alem->id, 'status' => $status]],
            ])->assertOk();
    }

    $report = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/reports/attendance?from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())
        ->assertOk()
        ->json('data');

    $row = collect($report)->firstWhere('employee_id', $alem->id);
    expect($row['present'])->toBe(1);
    expect($row['late'])->toBe(1);
    expect($row['absent'])->toBe(1);
    expect((float) $row['attendance_rate'])->toBe(round(2 / 3 * 100, 1));

    $overview = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/reports/overview')->assertOk()->json('data');
    expect($overview['headcount']['active'])->toBe(1);
});

it('produces the HR trends report: daily register, leave months, payroll history, tenure', function () {
    $branch = makeBranch();
    $alem = hrEmployee($branch, 'Alem'); // hired 2020-09-01 → 5-10 years of service
    $beza = hrEmployee($branch, 'Beza');
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/attendance', [
            'date' => now()->toDateString(),
            'records' => [
                ['employee_id' => $alem->id, 'status' => 'present'],
                ['employee_id' => $beza->id, 'status' => 'absent'],
            ],
        ])->assertOk();

    $trends = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/reports/trends?from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())
        ->assertOk()
        ->json('data');

    $day = collect($trends['daily'])->firstWhere('date', now()->toDateString());
    expect($day['present'])->toBe(1);
    expect($day['absent'])->toBe(1);

    expect($trends['leave_monthly'])->toHaveCount(6);
    expect(collect($trends['leave_monthly'])->last()['month'])->toBe(now()->format('Y-m'));

    expect($trends['payroll_runs'])->toBe([]);
    expect($trends['tenure']['5to10'])->toBe(2);
});

it('keeps HR data inside the tenant: another school sees and touches nothing', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    $employeeA = hrEmployee($branchA, 'Alem');

    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))->getJson('/api/v1/hr/leave-types')->assertOk();
    $requestId = $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $employeeA->id,
            'leave_type_id' => LeaveType::where('school_id', $branchA->school_id)->where('code', 'annual')->value('id'),
            'start_date' => '2026-07-13', 'end_date' => '2026-07-14',
        ])->assertCreated()->json('data.id');

    // A director of school B gets an empty list and cannot see or decide A's request.
    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/hr/leave-requests')->assertOk()->assertJsonCount(0, 'data');
    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/hr/leave-requests/{$requestId}")->assertForbidden();
    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/hr/leave-requests/{$requestId}/approve")->assertForbidden();

    // B's leave-type catalog is its own — A's types are not in it.
    $typesB = $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/hr/leave-types')->assertOk()->json('data');
    expect(collect($typesB)->pluck('school_id')->unique()->all())->toBe([$branchB->school_id]);

    // And B cannot record attendance for A's staff.
    $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/hr/attendance', [
            'date' => now()->toDateString(),
            'records' => [['employee_id' => $employeeA->id, 'status' => 'present']],
        ])->assertStatus(422);
});

it('lets staff see only their own requests and balances, and forbids teachers from the register', function () {
    $branch = makeBranch();
    $teacherUser = memberOf($branch);
    hrEmployee($branch, 'Alem', $teacherUser);
    $other = hrEmployee($branch, 'Beza');

    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();
    $typeId = LeaveType::where('code', 'annual')->value('id');
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $other->id, 'leave_type_id' => $typeId,
            'start_date' => '2026-07-13', 'end_date' => '2026-07-14',
        ])->assertCreated();

    Sanctum::actingAs($teacherUser);
    // Their list is empty (Beza's request is not theirs)…
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/leave-requests')->assertOk()->assertJsonCount(0, 'data');
    // …their balances only cover themselves…
    $balances = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/leave-balances')->assertOk()->json('data');
    expect(collect($balances)->pluck('employee_name')->all())->toBe(['Alem']);
    // …and the staff register is closed to them.
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/attendance')->assertForbidden();
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/attendance', [
            'date' => now()->toDateString(),
            'records' => [['employee_id' => $other->id, 'status' => 'absent']],
        ])->assertForbidden();
});

it('deactivates a used leave type instead of deleting it', function () {
    $branch = makeBranch();
    $employee = hrEmployee($branch, 'Alem');
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();
    $type = LeaveType::where('code', 'annual')->first();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-07-13', 'end_date' => '2026-07-14',
        ])->assertCreated();

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/hr/leave-types/{$type->id}")->assertOk();

    expect($type->fresh()->trashed())->toBeFalse();
    expect($type->fresh()->is_active)->toBeFalse();

    // An unused type deletes outright.
    $unused = LeaveType::where('code', 'study')->first();
    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/hr/leave-types/{$unused->id}")->assertOk();
    expect(LeaveType::find($unused->id))->toBeNull();

    expect(LeaveRequestStatus::Pending->label())->toBe('Pending');
});

it('refuses self-service leave starting in the past but lets managers backdate on behalf', function () {
    $branch = makeBranch();
    $teacherUser = memberOf($branch);
    hrEmployee($branch, 'Alem', $teacherUser);
    $beza = hrEmployee($branch, 'Beza');
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();
    $typeId = LeaveType::where('code', 'annual')->value('id');

    // A leave REQUEST asks for upcoming days — yesterday is gone.
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'leave_type_id' => $typeId,
            'start_date' => now()->subDays(4)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('start_date');

    // Today is fine — "starting today" is not the past.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'leave_type_id' => $typeId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ])->assertCreated();

    // HR on behalf may record leave already taken (sick leave filed late).
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $beza->id, 'leave_type_id' => $typeId,
            'start_date' => now()->subDays(4)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
        ])->assertCreated();
});
