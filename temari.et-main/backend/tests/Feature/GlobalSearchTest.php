<?php

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * The ⌘K palette (GlobalSearchService): index-backed matching on the generated
 * `search_text` haystacks — multi-word names, phones, emails, public/national
 * IDs, typo tolerance — with visibility that mirrors each list endpoint's
 * scoping. A hit in another school is a tenancy leak.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function searchStudent(Branch $branch, array $overrides = []): Student
{
    return Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abebe', 'father_name' => 'Kebede', 'grandfather_name' => 'Lemma',
        'gender' => 'male', 'primary_phone' => '0911-22-33-44',
        'email' => 'abebe@example.et', 'national_student_id' => 'NSID-778899',
        ...$overrides,
    ]);
}

function paletteSearch(Branch $branch, string $query): array
{
    return test()->withHeaders(branchContext($branch))
        ->getJson('/api/v1/search?query='.urlencode($query))
        ->assertOk()
        ->json('data');
}

it('finds a student by any name part, across parts, and by identifiers', function (string $query) {
    $branch = makeBranch();
    $student = searchStudent($branch);
    Sanctum::actingAs(directorOf($branch));

    expect(collect(paletteSearch($branch, $query)['students'] ?? [])->pluck('id'))
        ->toContain($student->id);
})->with([
    'first name' => 'Abebe',
    'father name' => 'kebede',
    'grandfather name' => 'Lemma',
    'multi-word across parts' => 'abebe kebede',
    'phone as stored' => '0911-22',
    'phone without separators' => '0911223344',
    'email' => 'abebe@example',
    'national student id' => 'NSID-778899',
    'typo (one letter off)' => 'Abeba',
]);

it('finds a student by public id, case-insensitively', function () {
    $branch = makeBranch();
    $student = searchStudent($branch);
    Sanctum::actingAs(directorOf($branch));

    expect(collect(paletteSearch($branch, strtolower($student->public_id))['students'] ?? [])->pluck('id'))
        ->toContain($student->id);
});

it('ranks the closest name first', function () {
    $branch = makeBranch();
    searchStudent($branch, ['first_name' => 'Abebech', 'email' => null, 'national_student_id' => null, 'primary_phone' => null]);
    $exact = searchStudent($branch, ['first_name' => 'Abebe', 'email' => null, 'national_student_id' => null, 'primary_phone' => null]);
    Sanctum::actingAs(directorOf($branch));

    expect(paletteSearch($branch, 'Abebe')['students'][0]['id'])->toBe($exact->id);
});

it('finds a parent through the linked user phone', function () {
    $branch = makeBranch();
    $student = searchStudent($branch);
    $user = User::create(['name' => 'Tesfaye Alemu', 'phone' => '0977554433']);
    $parent = ParentProfile::create(['user_id' => $user->id, 'first_name' => 'Tesfaye', 'father_name' => 'Alemu']);
    StudentGuardian::create(['student_id' => $student->id, 'parent_id' => $parent->id, 'relationship' => 'father', 'is_primary' => true]);

    Sanctum::actingAs(directorOf($branch));

    expect(collect(paletteSearch($branch, '0977554433')['parents'] ?? [])->pluck('id'))->toContain($parent->id);
});

it('finds an invoice by student name, title, and bare number', function () {
    $branch = makeBranch();
    $student = searchStudent($branch);
    $year = AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => '2017 E.C.', 'status' => 'active',
    ]);
    $invoice = new Invoice([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition — Semester 1', 'amount' => 4500, 'status' => 'unpaid',
    ]);
    // Two digits so the bare-number query clears the min:2 validation.
    $invoice->id = 42;
    $invoice->save();

    Sanctum::actingAs(directorOf($branch));

    // Bare digits, zero-padded, and every way people type the printed number.
    foreach (['Abebe Kebede', 'Tuition', '42', '000042', 'INV-000042', 'inv-42', 'INV 42', '#42'] as $query) {
        expect(collect(paletteSearch($branch, $query)['invoices'] ?? [])->pluck('id'))
            ->toContain($invoice->id);
    }
    expect(collect(paletteSearch($branch, 'Tuition')['invoices'])->firstWhere('id', $invoice->id)['student_id'])
        ->toBe($student->id);
});

it('finds a payment by transaction reference and an account by number or bank name', function () {
    $branch = makeBranch();
    $student = searchStudent($branch);
    $year = AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => '2017 E.C.', 'status' => 'active',
    ]);
    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition — Semester 1', 'amount' => 4500, 'status' => 'partial',
    ]);
    $payment = Payment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'invoice_id' => $invoice->id, 'student_id' => $student->id,
        'amount' => 2000, 'method' => 'bank_transfer',
        'reference' => 'FT26193K8QZP', 'paid_at' => now()->toDateString(),
        'receipt_number' => 'RCT-TEST-900002', 'receipt_token' => str_repeat('b', 40),
    ]);
    $bank = Bank::create(['code' => 'cbe', 'name' => 'Commercial Bank of Ethiopia', 'type' => 'bank']);
    $account = BankAccount::create([
        'school_id' => $branch->school_id, 'bank_id' => $bank->id,
        'account_name' => 'Main Tuition', 'account_number' => '1000234567890',
    ]);

    Sanctum::actingAs(directorOf($branch));

    // Full and partial references both land, and the hit deep-links to the student.
    foreach (['FT26193K8QZP', '26193K8Q'] as $query) {
        expect(collect(paletteSearch($branch, $query)['payments'] ?? [])->pluck('id'))
            ->toContain($payment->id);
    }
    expect(collect(paletteSearch($branch, 'FT26193K8QZP')['payments'])->firstWhere('id', $payment->id)['student_id'])
        ->toBe($student->id);

    foreach (['1000234', 'Main Tuition', 'Commercial Bank'] as $query) {
        expect(collect(paletteSearch($branch, $query)['accounts'] ?? [])->pluck('id'))
            ->toContain($account->id);
    }
});

it('never surfaces another school in search results', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    searchStudent($branchB, ['first_name' => 'Hidden', 'father_name' => 'Person', 'email' => null, 'national_student_id' => null]);

    Sanctum::actingAs(directorOf($branchA));

    expect(paletteSearch($branchA, 'Hidden Person'))->not->toHaveKey('students');
});

it('omits groups the user has no permission for', function () {
    $branch = makeBranch();
    searchStudent($branch);

    // Registrar: students.view but no account sweep (users group is platform-only).
    Sanctum::actingAs(memberOf($branch, Role::Registrar));
    $groups = paletteSearch($branch, 'Abebe');
    expect($groups)->toHaveKey('students')
        ->and($groups)->not->toHaveKey('users');

    // Teachers hold no register reads at all — the palette stays empty.
    Sanctum::actingAs(memberOf($branch));
    expect(paletteSearch($branch, 'Abebe'))
        ->not->toHaveKey('students')
        ->not->toHaveKey('parents')
        ->not->toHaveKey('sections');
});

it('finds a payment by its printed receipt number, full and partial', function () {
    $branch = makeBranch();
    $student = searchStudent($branch);
    $year = AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => '2017 E.C.', 'status' => 'active',
    ]);
    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition — Semester 1', 'amount' => 4500, 'status' => 'paid',
    ]);
    $payment = Payment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'invoice_id' => $invoice->id, 'student_id' => $student->id,
        'amount' => 4500, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        'receipt_number' => 'RCT-12-000123', 'receipt_token' => str_repeat('c', 40),
    ]);

    Sanctum::actingAs(directorOf($branch));

    foreach (['RCT-12-000123', '12-000123'] as $query) {
        expect(collect(paletteSearch($branch, $query)['payments'] ?? [])->pluck('id'))
            ->toContain($payment->id);
    }
});

it('finds LMS exams and question banks by title, never across schools', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');

    $quiz = Quiz::create([
        'school_id' => $branchA->school_id, 'branch_id' => $branchA->id,
        'kind' => 'exam', 'title' => 'Photosynthesis Midterm',
        'settings' => ['attempts_allowed' => 1, 'results_policy' => 'immediately'],
        'status' => 'published', 'published_at' => now(),
    ]);
    $bank = QuestionBank::create([
        'school_id' => $branchA->school_id, 'branch_id' => $branchA->id,
        'name' => 'Photosynthesis Bank',
    ]);

    // Supervisory lane (lms.view) sweeps the school.
    Sanctum::actingAs(directorOf($branchA));
    $groups = paletteSearch($branchA, 'Photosynthesis');
    expect(collect($groups['exams'] ?? [])->pluck('id'))->toContain($quiz->id)
        ->and(collect($groups['question_banks'] ?? [])->pluck('id'))->toContain($bank->id);

    // Another school's director sees none of it.
    Sanctum::actingAs(directorOf($branchB));
    $foreign = paletteSearch($branchB, 'Photosynthesis');
    expect($foreign)->not->toHaveKey('exams')
        ->and($foreign)->not->toHaveKey('question_banks');
});

it('scopes a teacher\'s exam search to their own classes', function () {
    $branch = makeBranch();

    // A supervisory exam elsewhere in the school the teacher does not teach.
    Quiz::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'kind' => 'exam', 'title' => 'Algebra Final (other class)',
        'settings' => ['attempts_allowed' => 1, 'results_policy' => 'immediately'],
        'status' => 'published', 'published_at' => now(),
    ]);

    Sanctum::actingAs(memberOf($branch)); // teacher: lms.manage_own only

    expect(paletteSearch($branch, 'Algebra Final'))->not->toHaveKey('exams');
});
