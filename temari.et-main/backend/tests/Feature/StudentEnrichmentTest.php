<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\HealthCondition;
use App\Models\SchoolDirectoryEntry;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Sms\SmsClient;
use App\Support\PublicId;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\HealthConditionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(HealthConditionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function enrichmentYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function enrichmentSection(Branch $branch, string $gradeCode = 'G1', string $name = 'A'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
    ]);
}

it('assigns unique public ids to students and users from the safe alphabet', function () {
    $branch = makeBranch();
    Sanctum::actingAs($director = directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Hana', 'father_name' => 'Bekele', 'gender' => 'female',
            'guardians' => [guardianPayload()],
        ])->assertCreated();

    $publicId = $response->json('data.public_id');
    expect($publicId)->toHaveLength(6);
    expect($publicId)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
    expect($director->fresh()->public_id)->toHaveLength(6);
});

it('finds a student by public id case-insensitively', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sara', 'father_name' => 'Mulu', 'gender' => 'female',
    ]);

    $response = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/students?search='.strtolower($student->public_id))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($student->id);
});

it('registers a student with the full enriched profile', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $condition = HealthCondition::where('name', 'Asthma')->first();

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Abel',
            'father_name' => 'Girma',
            'gender' => 'male',
            'citizenship' => 'Ethiopian',
            'email' => 'abel@example.com',
            'languages' => ['am', 'om'],
            'blood_type' => 'O+',
            'health_notes' => 'Carries an inhaler.',
            'health_conditions' => [
                ['health_condition_id' => $condition->id, 'severity' => 'moderate', 'medication' => 'Inhaler'],
            ],
            'birth_state' => 'Oromia', 'birth_city' => 'Adama',
            'state' => 'Addis Ababa', 'city' => 'Addis Ababa', 'sub_city' => 'Bole', 'woreda' => '03', 'house_no' => '124',
            'guardians' => [guardianPayload()],
        ])->assertCreated()
        ->assertJsonPath('data.languages', ['am', 'om'])
        ->assertJsonPath('data.city', 'Addis Ababa')
        ->assertJsonPath('data.health_conditions.0.severity', 'moderate');

    $student = Student::find($response->json('data.id'));
    expect($student->healthConditions)->toHaveCount(1);
    expect($student->blood_type)->toBe('O+');
});

it('rejects languages outside the platform catalog', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'A', 'father_name' => 'B', 'gender' => 'male',
            'languages' => ['am', 'klingon'],
            'guardians' => [guardianPayload()],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('languages.1');
});

it('enrolls into a grade without a section and assigns the section later', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = enrichmentYear($branch);
    $grade = GradeLevel::where('code', 'G3')->first();
    $previous = SchoolDirectoryEntry::create(['name' => 'Old School', 'is_verified' => true]);

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Marta', 'father_name' => 'Kebede', 'gender' => 'female',
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'previous_school_id' => $previous->id,
            'guardians' => [guardianPayload()],
        ])->assertCreated();

    $enrollment = StudentEnrollment::firstWhere('student_id', $response->json('data.id'));
    expect($enrollment->section_id)->toBeNull();
    expect($enrollment->grade_level_id)->toBe($grade->id);
    expect($enrollment->previous_school_id)->toBe($previous->id);
    expect($enrollment->branch_id)->toBe($branch->id);
});

it('keeps health data off the list payload and on the detail payload', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $condition = HealthCondition::first();

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Liya', 'father_name' => 'Tesfaye', 'gender' => 'female',
        'blood_type' => 'A+',
    ]);
    $student->healthConditions()->sync([$condition->id => ['severity' => 'severe']]);

    $list = $this->withHeaders(branchContext($branch))->getJson('/api/v1/students')->assertOk();
    expect($list->json('data.0'))->not->toHaveKeys(['health_conditions', 'blood_type']);

    $detail = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/students/{$student->id}")->assertOk();
    expect($detail->json('data.blood_type'))->toBe('A+');
    expect($detail->json('data.health_conditions'))->toHaveCount(1);
});

it('syncs health conditions on update', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    [$first, $second] = HealthCondition::limit(2)->get();

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Nahom', 'father_name' => 'Assefa', 'gender' => 'male',
    ]);
    $student->healthConditions()->sync([$first->id => ['severity' => 'mild']]);

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/students/{$student->id}", [
            'first_name' => 'Nahom', 'father_name' => 'Assefa', 'gender' => 'male',
            'health_conditions' => [
                ['health_condition_id' => $second->id, 'severity' => 'severe', 'notes' => 'New diagnosis'],
            ],
        ])->assertOk();

    $student->refresh();
    expect($student->healthConditions)->toHaveCount(1);
    expect($student->healthConditions->first()->id)->toBe($second->id);
});

it('uploads and deletes student documents and photos through signed storage', function () {
    Storage::fake(config('filesystems.default'));
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Ruth', 'father_name' => 'Alemu', 'gender' => 'female',
    ]);

    $upload = $this->withHeaders(branchContext($branch))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Birth certificate',
            'category' => 'birth_certificate',
            'file' => UploadedFile::fake()->create('birth.pdf', 100, 'application/pdf'),
        ])->assertCreated()
        ->assertJsonPath('data.category', 'birth_certificate');

    expect($student->attachments()->count())->toBe(1);

    // Unknown categories are rejected, not silently stored.
    $this->withHeaders(branchContext($branch))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'Mystery',
            'category' => 'not_a_category',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);

    $this->withHeaders(branchContext($branch))
        ->post("/api/v1/students/{$student->id}/photo", [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ])->assertOk();

    expect($student->fresh()->photo_path)->not->toBeNull();

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/students/{$student->id}/attachments/{$upload->json('data.id')}")
        ->assertOk();

    expect($student->attachments()->count())->toBe(0);
});

it('denies student file uploads to staff of another school', function () {
    Storage::fake(config('filesystems.default'));
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    $student = Student::create([
        'school_id' => $branchA->school_id, 'branch_id' => $branchA->id,
        'first_name' => 'Kal', 'father_name' => 'G', 'gender' => 'male',
    ]);

    Sanctum::actingAs(directorOf($branchB));

    $this->withHeaders(branchContext($branchB))
        ->post("/api/v1/students/{$student->id}/attachments", [
            'name' => 'ID',
            'file' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
});

it('normalizes public id input for matching', function () {
    expect(PublicId::normalize(' h8r6wv '))->toBe('H8R6WV');
});
