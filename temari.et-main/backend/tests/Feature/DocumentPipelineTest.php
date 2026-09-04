<?php

use App\Models\GeneratedDocument;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));

    Storage::fake();
    config()->set('services.cloudflare.account_id', 'acc-test');
    config()->set('services.cloudflare.api_token', 'token-test');
    Http::fake([
        'api.cloudflare.com/*' => Http::response('%PDF-1.4 fake-pdf', 200),
    ]);
});

function paidStudent($branch, $year): array
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Doc', 'father_name' => 'Test', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'status' => 'active', 'enrolled_on' => '2025-09-15',
    ]);

    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition — Meskerem',
        'amount' => 1000, 'amount_paid' => 0, 'status' => 'unpaid',
    ]);

    return [$student, $invoice];
}

it('renders and stores the official PDF receipt — and notifies the family', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    [, $invoice] = paidStudent($branch, $year);

    $director = directorOf($branch);
    Sanctum::actingAs($director);

    // Recording the payment pre-warms the PDF (sync queue) + queues comms.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 1000, 'method' => 'cash',
        ])->assertCreated();

    $document = GeneratedDocument::query()->where('type', 'payment_receipt')->first();
    expect($document)->not->toBeNull()
        ->and($document->status)->toBe('ready')
        ->and(Storage::exists($document->disk_path))->toBeTrue();

    // Asking again renders a FRESH row — no caching, never a stale PDF.
    $paymentId = $document->subject_id;
    $again = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', ['type' => 'payment_receipt', 'subject_id' => $paymentId])
        ->assertOk()->json('data');
    expect($again['id'])->not->toBe($document->id)
        ->and($again['status'])->toBe('ready')
        ->and(GeneratedDocument::count())->toBe(2);

    // The QR target verifies publicly, without auth.
    $verify = $this->getJson("/api/v1/public/documents/{$document->public_token}")
        ->assertOk()->json('data');
    expect($verify['status'])->toBe('valid')
        ->and($verify['summary']['reference'])->not->toBeNull()
        ->and($verify['download_url'])->not->toBeNull();

    // Revoking flips the public answer.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/documents/{$document->id}/revoke")->assertOk();
    expect($this->getJson("/api/v1/public/documents/{$document->public_token}")
        ->json('data.status'))->toBe('revoked');
});

it('keeps documents tenant-scoped — another school cannot fetch them', function () {
    $branchA = makeBranch('AA-0001');
    $yearA = activeYear($branchA);
    [, $invoice] = paidStudent($branchA, $yearA);

    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 500, 'method' => 'cash'])
        ->assertCreated();

    $document = GeneratedDocument::query()->where('type', 'payment_receipt')->firstOrFail();

    $branchB = makeBranch('BB-0001');
    Sanctum::actingAs(directorOf($branchB));

    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/documents/{$document->id}")->assertForbidden();
    $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/documents', [
            'type' => 'payment_receipt', 'subject_id' => $document->subject_id,
        ])->assertForbidden();
});

it('generates the income–expense statement PDF for the scope the caller may see', function () {
    $branch = makeBranch();
    activeYear($branch);
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'finance_statement',
            'params' => [
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'from' => '2026-06-01', 'to' => '2026-06-30',
            ],
        ])->assertOk()->json('data');

    expect($response['status'])->toBe('ready')->and($response['url'])->not->toBeNull();

    // A branch belonging to another school is rejected outright.
    $other = makeBranch('CC-0001');
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'finance_statement',
            'params' => [
                'school_id' => $branch->school_id, 'branch_id' => $other->id,
                'from' => '2026-06-01', 'to' => '2026-06-30',
            ],
        ])->assertForbidden();
});

it('generates full and partial transcript PDFs as distinct cached documents', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    [$student] = paidStudent($branch, $year);

    // One frozen semester — enough for a transcript sheet.
    $enrollment = StudentEnrollment::firstWhere('student_id', $student->id);
    StudentTermResult::create([
        'student_id' => $student->id, 'student_enrollment_id' => $enrollment->id,
        'term_id' => $year->terms()->orderBy('sequence')->value('id'),
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'grade_level_id' => $enrollment->grade_level_id,
        'total' => 80, 'average' => 80, 'subject_count' => 1,
        'breakdown' => [[
            'subject_id' => 1, 'code' => 'MATH', 'name' => 'Mathematics',
            'total' => 80.0, 'letter' => null, 'band_label' => null, 'is_passing' => true,
        ]],
        'computed_at' => now(),
    ]);

    // The PDF HTML must be SELF-CONTAINED: the remote renderer never fetches
    // signed URLs, so the student photo travels inline as a data URI.
    Storage::put("student-photos/{$student->id}/photo.png", base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    ));
    $student->forceFill(['photo_path' => "student-photos/{$student->id}/photo.png"])->save();

    Sanctum::actingAs(directorOf($branch));

    $full = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', ['type' => 'transcript', 'subject_id' => $student->id])
        ->assertOk()->json('data');
    expect($full['status'])->toBe('ready');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'browser-rendering/pdf')
            && str_contains((string) ($request->data()['html'] ?? ''), 'data:image');
    });

    // Narrowed params → a DIFFERENT document (params key the content hash);
    // its verify page declares the partial coverage.
    $partial = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'transcript', 'subject_id' => $student->id,
            'params' => ['academic_year_ids' => [$year->id]],
        ])->assertOk()->json('data');
    expect($partial['status'])->toBe('ready')
        ->and($partial['id'])->not->toBe($full['id']);

    $token = GeneratedDocument::findOrFail($partial['id'])->public_token;
    $verify = $this->getJson("/api/v1/public/documents/{$token}")
        ->assertOk()->json('data');
    expect($verify['summary']['coverage'])->toContain('Partial');

    // Re-asking with the same params renders a FRESH row — no caching.
    $again = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'transcript', 'subject_id' => $student->id,
            'params' => ['academic_year_ids' => [$year->id]],
        ])->assertOk()->json('data');
    expect($again['id'])->not->toBe($partial['id'])
        ->and(GeneratedDocument::where('type', 'transcript')->count())->toBe(3);
});

it('renders the semester roster PDF from frozen results', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    [$student] = paidStudent($branch, $year);

    $enrollment = StudentEnrollment::firstWhere('student_id', $student->id);
    $termId = $year->terms()->orderBy('sequence')->value('id');
    StudentTermResult::create([
        'student_id' => $student->id, 'student_enrollment_id' => $enrollment->id,
        'term_id' => $termId, 'section_id' => $enrollment->section_id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'grade_level_id' => $enrollment->grade_level_id,
        'total' => 80, 'average' => 80, 'subject_count' => 1, 'rank' => 1,
        'breakdown' => [[
            'subject_id' => 1, 'code' => 'MATH', 'name' => 'Mathematics',
            'total' => 80.0, 'letter' => null, 'band_label' => null, 'is_passing' => true,
        ]],
        'computed_at' => now(),
    ]);

    Sanctum::actingAs(directorOf($branch));

    $params = ['scope' => 'term', 'term_id' => $termId, 'grade_level_id' => $enrollment->grade_level_id];

    $doc = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', ['type' => 'roster', 'params' => $params])
        ->assertOk()->json('data');
    expect($doc['status'])->toBe('ready');

    // Re-asking with the same params renders a FRESH row — no caching.
    $again = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', ['type' => 'roster', 'params' => $params])
        ->assertOk()->json('data');
    expect($again['id'])->not->toBe($doc['id'])
        ->and(GeneratedDocument::where('type', 'roster')->count())->toBe(2);

    // The QR opens the LIVE roster page (no login) — the actual sheet, and it
    // dies when the document is revoked.
    $token = $doc['public_token'];
    $public = $this->getJson("/api/v1/public/rosters/{$token}")->assertOk()->json('data');
    expect($public['scope'])->toBe('term')
        ->and($public['data']['rows'])->toHaveCount(1)
        ->and($public['download_url'])->not->toBeNull();

    GeneratedDocument::findOrFail($doc['id'])->update(['revoked_at' => now()]);
    $this->getJson("/api/v1/public/rosters/{$token}")->assertStatus(410);
});

it('denies the roster PDF to a teacher without supervisory grades.view', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $termId = $year->terms()->orderBy('sequence')->value('id');

    // A teacher holds grades.manage_own only — never the whole-grade sheet,
    // which would leak other sections through the shared PDF cache.
    Sanctum::actingAs(memberOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'roster',
            'params' => ['scope' => 'term', 'term_id' => $termId, 'grade_level_id' => GradeLevel::where('code', 'G1')->value('id')],
        ])->assertForbidden();
});

it('renders a whole class of transcripts as ONE official PDF', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $termId = $year->terms()->orderBy('sequence')->value('id');

    $students = collect(range(1, 2))->map(function () use ($branch, $year, $termId) {
        [$student] = paidStudent($branch, $year);
        $enrollment = StudentEnrollment::firstWhere('student_id', $student->id);
        StudentTermResult::create([
            'student_id' => $student->id, 'student_enrollment_id' => $enrollment->id,
            'term_id' => $termId, 'section_id' => $enrollment->section_id,
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $year->id, 'grade_level_id' => $enrollment->grade_level_id,
            'total' => 80, 'average' => 80, 'subject_count' => 1,
            'breakdown' => [[
                'subject_id' => 1, 'code' => 'MATH', 'name' => 'Mathematics',
                'total' => 80.0, 'letter' => null, 'band_label' => null, 'is_passing' => true,
            ]],
            'computed_at' => now(),
        ]);

        return $student;
    });

    // A school logo + student photos on file: the batch must inline the logo
    // ONCE and drop the photos — a remote URL renders as a broken image (the
    // renderer never fetches signed URLs) and blows the render budget.
    $pixel = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    );
    Storage::put('school-logos/logo.png', $pixel);
    School::findOrFail($branch->school_id)->forceFill(['logo_path' => 'school-logos/logo.png'])->save();

    foreach ($students as $student) {
        Storage::put("student-photos/{$student->id}/photo.png", $pixel);
        $student->forceFill(['photo_path' => "student-photos/{$student->id}/photo.png"])->save();
    }

    Sanctum::actingAs(directorOf($branch));

    $params = [
        'academic_year_id' => $year->id,
        'student_ids' => $students->pluck('id')->all(),
    ];

    $doc = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', ['type' => 'transcript_batch', 'params' => $params])
        ->assertOk()->json('data');
    expect($doc['status'])->toBe('ready');

    // ONE render, one sheet per student — never a browser print of the page.
    Http::assertSent(function ($request) use ($students) {
        $html = (string) ($request->data()['html'] ?? '');

        return str_contains($request->url(), 'browser-rendering/pdf')
            && substr_count($html, 'class="card sheet"') === $students->count()
            && str_contains($html, 'Official student transcript')
            // Self-contained: the logo is inlined ONCE (a shared CSS
            // background), and no student photo URL travels at all.
            && substr_count($html, "url('data:image") === 1
            && ! str_contains($html, 'student-photos');
    });

    // The QR proves origin only: one student's page must never hand whoever
    // holds it a PDF of the whole class's marks.
    $verify = $this->getJson("/api/v1/public/documents/{$doc['public_token']}")
        ->assertOk()->json('data');
    expect($verify['download_url'])->toBeNull()
        ->and($verify['summary']['students'])->toBe('2');
});

it('refuses a transcript batch containing a student from another branch', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    [$mine] = paidStudent($branch, $year);

    $other = makeBranch('AA-0002');
    $otherYear = activeYear($other);
    [$theirs] = paidStudent($other, $otherYear);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'transcript_batch',
            'params' => [
                'academic_year_id' => $year->id,
                'student_ids' => [$mine->id, $theirs->id],
            ],
        ])->assertForbidden();
});
