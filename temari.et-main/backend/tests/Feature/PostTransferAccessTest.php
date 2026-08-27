<?php

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentAttachment;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custody follows the enrollment (StudentPolicy / Student::activeAdminScopes):
 * once a transfer lands, the sending school drops to a read-only archive of
 * its own era — no writes, no documents, no health data, no forward
 * visibility into the receiving school. Mirrors how top SIS platforms bound
 * "legitimate educational interest" to the enrollment window.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function ptaYear(Branch $branch, string $name = '2017 E.C.'): AcademicYear
{
    return AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => $name, 'status' => 'active',
    ]);
}

function ptaStudent(Branch $branch, string $first = 'Hana'): Student
{
    return $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first, 'father_name' => 'Bekele', 'gender' => 'female',
    ]);
}

function ptaEnroll(Student $student, AcademicYear $year, string $gradeCode = 'G1'): StudentEnrollment
{
    return StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $year->school_id, 'branch_id' => $year->branch_id,
        'academic_year_id' => $year->id,
        'school_program_id' => SchoolProgram::defaultFor($year->branch)->id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'status' => 'active', 'enrolled_on' => now(),
    ]);
}

/** Run the full transfer lane: receiving branch requests, sending approves. */
function ptaTransfer(Student $student, Branch $from, Branch $to, AcademicYear $toYear): void
{
    Sanctum::actingAs(directorOf($to));
    $requestId = test()->withHeaders(branchContext($to))
        ->postJson('/api/v1/transfer-requests', [
            'student_id' => $student->id,
            'to_academic_year_id' => $toYear->id,
            'to_grade_level_id' => GradeLevel::where('code', 'G2')->value('id'),
            'reason' => 'Family moved.',
        ])->assertCreated()->json('data.id');

    Sanctum::actingAs(directorOf($from));
    test()->withHeaders(branchContext($from))
        ->postJson("/api/v1/transfer-requests/{$requestId}/approve")
        ->assertOk();
}

it('hands custody forward on transfer: the former school loses every write path and all forward visibility', function () {
    Storage::fake(config('filesystems.default'));

    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = ptaYear($branchA);
    $yearB = ptaYear($branchB);
    $student = ptaStudent($branchA);
    ptaEnroll($student, $yearA);

    $oldDirector = directorOf($branchA);

    // Before the transfer, school A holds custody: it uploads a document.
    Sanctum::actingAs($oldDirector);
    $oldDocId = $this->withHeaders(branchContext($branchA))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Birth certificate',
            'file' => UploadedFile::fake()->create('birth.pdf', 50, 'application/pdf'),
        ])->assertCreated()->json('data.id');

    ptaTransfer($student, $branchA, $branchB, $yearB);

    // The NEW school uploads a document after the transfer.
    $newDirector = directorOf($branchB);
    Sanctum::actingAs($newDirector);
    $newDocId = $this->withHeaders(branchContext($branchB))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Report card from previous school',
            'file' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
        ])->assertCreated()->json('data.id');

    // ── Former school: every mutation path is closed. ──────────────────────
    Sanctum::actingAs($oldDirector);
    $headers = branchContext($branchA);

    $this->withHeaders($headers)
        ->putJson("/api/v1/students/{$student->id}", ['first_name' => 'Renamed', 'father_name' => 'Bekele', 'gender' => 'female'])
        ->assertForbidden();

    $this->withHeaders($headers)
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Late upload',
            'file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

    $this->withHeaders($headers)
        ->deleteJson("/api/v1/students/{$student->id}/attachments/{$newDocId}")
        ->assertForbidden();

    $this->withHeaders($headers)
        ->post("/api/v1/students/{$student->id}/photo", [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ])->assertForbidden();

    // Guardians: the ERA SNAPSHOT is served read-only (the family as it was
    // on file at departure) — mutations stay closed via manageGuardians.
    $frozenGuardians = $this->withHeaders($headers)
        ->getJson("/api/v1/students/{$student->id}/guardians")
        ->assertOk();
    expect($frozenGuardians->json('meta.access'))->toBe('archive');

    // ── Former school: archive read — the file AS THE STUDENT LEFT. ────────
    // Era documents stay visible; anything the new school adds never appears.
    $archive = $this->withHeaders($headers)
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');

    expect($archive['access'])->toBe('archive')
        ->and(collect($archive['attachments'])->pluck('id')->all())->toBe([$oldDocId])
        ->and($archive['archive']['captured_at'] ?? null)->not->toBeNull()
        ->and($archive)->not->toHaveKey('health_conditions')
        ->and($archive['current_enrollment'] ?? null)->toBeNull()
        ->and(collect($archive['enrollments'])->pluck('school_id')->unique()->all())
        ->toBe([$branchA->school_id]);

    // ── New school: full custody — history travelled with the student. ─────
    Sanctum::actingAs($newDirector);
    $full = $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');

    expect($full['access'])->toBe('full')
        ->and(collect($full['attachments'])->pluck('id')->all())
        ->toContain($oldDocId, $newDocId)
        ->and(collect($full['attachments'])->firstWhere('id', $oldDocId)['branch_name'])
        ->toBe($branchA->name)
        ->and($full['current_enrollment']['branch_id'] ?? $full['current_enrollment']['branch']['id'] ?? $branchB->id)
        ->toBe($branchB->id);

    $this->withHeaders(branchContext($branchB))
        ->putJson("/api/v1/students/{$student->id}", ['first_name' => 'Hanna', 'father_name' => 'Bekele', 'gender' => 'female'])
        ->assertOk();
});

it('keeps custody with the last school when a student withdraws without transferring', function () {
    Storage::fake(config('filesystems.default'));

    $branchA = makeBranch('AA-0001');
    $yearA = ptaYear($branchA);
    $student = ptaStudent($branchA);
    $enrollment = ptaEnroll($student, $yearA);

    $enrollment->update(['status' => 'withdrawn', 'exited_on' => now()->toDateString()]);

    // No live enrollment anywhere → the last school still holds the record.
    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->putJson("/api/v1/students/{$student->id}", ['first_name' => 'Sara', 'father_name' => 'Bekele', 'gender' => 'female'])
        ->assertOk();

    $show = $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');

    expect($show['access'])->toBe('full');
});

it("masks the new school's live enrollment on the former school's register", function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = ptaYear($branchA);
    $yearB = ptaYear($branchB);
    $student = ptaStudent($branchA, 'Kalkidan');
    ptaEnroll($student, $yearA);

    ptaTransfer($student, $branchA, $branchB, $yearB);

    Sanctum::actingAs(directorOf($branchA));
    $rows = $this->withHeaders(branchContext($branchA))
        ->getJson('/api/v1/students?per_page=100')
        ->assertOk()
        ->json('data');

    $row = collect($rows)->firstWhere('id', $student->id);

    // Still on the register (archive row), but the enrollment shown is the
    // former school's own closed one — never the receiving school's.
    expect($row)->not->toBeNull()
        ->and($row['access'] ?? null)->toBe('archive')
        ->and($row['current_enrollment']['status'])->toBe('transferred_out')
        ->and($row['current_enrollment']['branch_id'] ?? $branchA->id)->toBe($branchA->id);

    // The receiving school's register shows the live enrollment as usual.
    Sanctum::actingAs(directorOf($branchB));
    $row = collect($this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/students?per_page=100')
        ->assertOk()
        ->json('data'))->firstWhere('id', $student->id);

    expect($row['current_enrollment']['status'])->toBe('active');
});

it('locks the family graph and guardian files to schools with live custody', function () {
    Storage::fake(config('filesystems.default'));

    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = ptaYear($branchA);
    $yearB = ptaYear($branchB);
    $student = ptaStudent($branchA);
    ptaEnroll($student, $yearA);

    $parent = ParentProfile::create([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Almaz', 'father_name' => 'Worku',
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'mother', 'is_active' => true,
    ]);

    ptaTransfer($student, $branchA, $branchB, $yearB);

    // Former school: no guardian file writes, bare parent profile only.
    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->post("/api/v1/parents/{$parent->id}/attachments", [
            'name' => 'Custody letter',
            'file' => UploadedFile::fake()->create('custody.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

    $bare = $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/parents/{$parent->id}")
        ->assertOk()
        ->json('data');
    expect($bare)->not->toHaveKey('attachments');

    // Receiving school: full guardian management.
    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->post("/api/v1/parents/{$parent->id}/attachments", [
            'name' => 'Custody letter',
            'file' => UploadedFile::fake()->create('custody.pdf', 10, 'application/pdf'),
        ])->assertCreated();

    $fullParent = $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/parents/{$parent->id}")
        ->assertOk()
        ->json('data');
    expect($fullParent['attachments'])->toHaveCount(1);
});

it('serves the era snapshot: the former school sees the file as the student left, nothing newer', function () {
    Storage::fake(config('filesystems.default'));

    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = ptaYear($branchA);
    $yearB = ptaYear($branchB);
    $student = ptaStudent($branchA);
    $student->update(['city' => 'Addis Ababa', 'blood_type' => 'O+']);
    ptaEnroll($student, $yearA);

    $eraParent = ParentProfile::create([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Aster', 'father_name' => 'Kebede',
    ]);
    $eraGuardian = StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $eraParent->id,
        'relationship' => 'mother', 'is_active' => true,
    ]);

    ptaTransfer($student, $branchA, $branchB, $yearB);

    // The NEW school reshapes the record: address, health, a new guardian.
    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->putJson("/api/v1/students/{$student->id}", [
            'first_name' => 'Hana', 'father_name' => 'Bekele', 'gender' => 'female',
            'city' => 'Adama', 'blood_type' => 'AB+',
        ])->assertOk();

    $newParent = ParentProfile::create([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Chala', 'father_name' => 'Gemechu',
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $newParent->id,
        'relationship' => 'father', 'is_active' => true,
    ]);

    // The FORMER school reads the frozen era file — not the live record.
    Sanctum::actingAs(directorOf($branchA));
    $archive = $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');

    expect($archive['access'])->toBe('archive')
        ->and($archive['archive']['profile']['city'])->toBe('Addis Ababa')
        ->and($archive['archive']['health']['blood_type'])->toBe('O+');

    $frozen = $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/students/{$student->id}/guardians")
        ->assertOk();

    expect($frozen->json('meta.access'))->toBe('archive')
        ->and(collect($frozen->json('data'))->pluck('id')->all())->toBe([$eraGuardian->id]);
});

it('keeps transfer files on the record for both participant schools, never future ones', function () {
    Storage::fake(config('filesystems.default'));

    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $branchC = makeBranch('CC-0001');
    $yearA = ptaYear($branchA);
    $yearB = ptaYear($branchB);
    $yearC = ptaYear($branchC);
    $student = ptaStudent($branchA, 'Lensa');
    ptaEnroll($student, $yearA);

    // Receiving school files the request WITH a renamed supporting document.
    Sanctum::actingAs(directorOf($branchB));
    $requestId = $this->withHeaders(branchContext($branchB))
        ->post('/api/v1/transfer-requests', [
            'student_id' => $student->id,
            'to_academic_year_id' => $yearB->id,
            'to_grade_level_id' => GradeLevel::where('code', 'G2')->value('id'),
            'reason' => 'Family moved.',
            'documents' => [
                ['file' => UploadedFile::fake()->create('report-2016.pdf', 20, 'application/pdf'), 'name' => 'Report card 2016 E.C.'],
            ],
        ])->assertCreated()->json('data.id');

    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/transfer-requests/{$requestId}/approve")
        ->assertOk();

    // Both participants see the transfer file, under the chosen name.
    foreach ([[$branchA, 'archive'], [$branchB, 'full']] as [$branch, $access]) {
        Sanctum::actingAs(directorOf($branch));
        $data = $this->withHeaders(branchContext($branch))
            ->getJson("/api/v1/students/{$student->id}")
            ->assertOk()
            ->json('data');

        expect($data['access'])->toBe($access)
            ->and($data['transfer_files'][0]['files'][0]['name'])->toBe('Report card 2016 E.C.');
    }

    // The student later moves B → C (no files): school C never sees A↔B files.
    ptaTransfer($student, $branchB, $branchC, $yearC);

    Sanctum::actingAs(directorOf($branchC));
    $atC = $this->withHeaders(branchContext($branchC))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');

    expect($atC['access'])->toBe('full')
        ->and($atC)->not->toHaveKey('transfer_files');
});

it('restores full live access when a student transfers back years later (A → B → A)', function () {
    Storage::fake(config('filesystems.default'));

    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = ptaYear($branchA);
    $yearB = ptaYear($branchB);
    $student = ptaStudent($branchA, 'Robel');
    ptaEnroll($student, $yearA);

    ptaTransfer($student, $branchA, $branchB, $yearB);

    // School B collects a document during its era.
    Sanctum::actingAs(directorOf($branchB));
    $bDocId = $this->withHeaders(branchContext($branchB))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Sports award',
            'file' => UploadedFile::fake()->create('award.pdf', 10, 'application/pdf'),
        ])->assertCreated()->json('data.id');

    // …and the student returns to school A.
    ptaTransfer($student, $branchB, $branchA, $yearA);

    // School A holds live custody again: full file, including B-era documents.
    Sanctum::actingAs(directorOf($branchA));
    $back = $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');

    expect($back['access'])->toBe('full')
        ->and(collect($back['attachments'])->pluck('id')->all())->toContain($bDocId);

    $this->withHeaders(branchContext($branchA))
        ->putJson("/api/v1/students/{$student->id}", ['first_name' => 'Robel', 'father_name' => 'Bekele', 'gender' => 'female'])
        ->assertOk();

    // School B is now the archive side, frozen at ITS handover.
    Sanctum::actingAs(directorOf($branchB));
    $bView = $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');

    expect($bView['access'])->toBe('archive')
        ->and(collect($bView['attachments'])->pluck('id')->all())->toContain($bDocId);
});

it('never destroys era documents: provenance guards deletion, snapshot-referenced files are only hidden', function () {
    Storage::fake(config('filesystems.default'));

    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = ptaYear($branchA);
    $yearB = ptaYear($branchB);
    $student = ptaStudent($branchA, 'Mahi');
    ptaEnroll($student, $yearA);

    // School A collects a document; the student then transfers to B.
    Sanctum::actingAs(directorOf($branchA));
    $eraDocId = $this->withHeaders(branchContext($branchA))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Birth certificate',
            'file' => UploadedFile::fake()->create('birth.pdf', 20, 'application/pdf'),
        ])->assertCreated()->json('data.id');

    ptaTransfer($student, $branchA, $branchB, $yearB);

    // Rule 2 — provenance: school B holds custody but did NOT add this
    // document; it may hide nothing of school A's paperwork.
    $newDirector = directorOf($branchB);
    Sanctum::actingAs($newDirector);
    $this->withHeaders(branchContext($branchB))
        ->deleteJson("/api/v1/students/{$student->id}/attachments/{$eraDocId}")
        ->assertForbidden();

    // B's OWN upload (not referenced by any snapshot) hard-deletes as before.
    $ownDocId = $this->withHeaders(branchContext($branchB))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Sports certificate',
            'file' => UploadedFile::fake()->create('sport.pdf', 10, 'application/pdf'),
        ])->assertCreated()->json('data.id');

    $ownPath = StudentAttachment::findOrFail($ownDocId)->path;

    $this->withHeaders(branchContext($branchB))
        ->deleteJson("/api/v1/students/{$student->id}/attachments/{$ownDocId}")
        ->assertOk();

    expect(StudentAttachment::withTrashed()->find($ownDocId))->toBeNull()
        ->and(Storage::disk(config('filesystems.default'))->exists($ownPath))->toBeFalse();

    // Rule 1 — retention: platform staff (who bypass provenance) delete the
    // era document → it is HIDDEN from the live file, never destroyed.
    Sanctum::actingAs(platformAdmin());
    $this->deleteJson("/api/v1/students/{$student->id}/attachments/{$eraDocId}")
        ->assertOk();

    $eraDoc = StudentAttachment::withTrashed()->findOrFail($eraDocId);
    expect($eraDoc->deleted_at)->not->toBeNull()
        ->and(Storage::disk(config('filesystems.default'))->exists($eraDoc->path))->toBeTrue();

    // Custody school's live file no longer shows it…
    Sanctum::actingAs($newDirector);
    $live = $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');
    expect(collect($live['attachments'])->pluck('id')->all())->not->toContain($eraDocId);

    // …but the former school's frozen archive still opens its copy.
    Sanctum::actingAs(directorOf($branchA));
    $archive = $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/students/{$student->id}")
        ->assertOk()
        ->json('data');
    expect(collect($archive['attachments'])->pluck('id')->all())->toContain($eraDocId);
});
