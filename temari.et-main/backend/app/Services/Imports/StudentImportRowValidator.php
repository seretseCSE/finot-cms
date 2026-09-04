<?php

namespace App\Services\Imports;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentImportRowStatus;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use App\Rules\BirthDate;
use App\Rules\EthiopianPhone;
use App\Support\GradeOffering;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Server-side validation of one import row's CANONICAL payload (the studio
 * already did the fuzzy work: header mapping, name splitting, Ethiopian-
 * calendar dates, grade/section name resolution). Returns the normalized
 * data, a status, and field-keyed issues; also detects duplicates — an
 * existing student at THIS school (Fayda / national ID / name trio + DOB)
 * flips the row to `duplicate` for the registrar to resolve, while a Fayda /
 * national-ID match at another Temari school is only a warning (the transfer
 * lane owns that move, and namesakes are legal).
 *
 * Issues carry machine `code`s so the studio renders them in the reader's
 * language; `message` is the English fallback for codes it doesn't know.
 *
 * One instance serves one HTTP chunk — call prime() with the chunk's rows
 * first: lookups (this school's students, the chunk's global identifiers,
 * guardian phones) are batched so a 500-row request costs a handful of
 * queries, not 500.
 */
class StudentImportRowValidator
{
    /** @var array<string, list<array{id: int, in_school: bool}>> */
    private array $byFayda = [];

    /** @var array<string, list<array{id: int, in_school: bool}>> */
    private array $byNationalId = [];

    /** @var array<string, list<array{id: int, in_school: bool}>> */
    private array $byNameDob = [];

    /** @var array<string, bool> normalized phone → an account exists */
    private array $knownPhones = [];

    private bool $primed = false;

    /** @var array<int, Section>|null branch sections keyed by id */
    private ?array $sections = null;

    public function __construct(private readonly StudentImport $import)
    {
    }

    /**
     * Batch-load every lookup the chunk's rows will need.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function prime(array $rows): void
    {
        $this->primed = true;
        $phones = [];
        $faydaHashes = [];
        $nationalIds = [];

        foreach ($rows as $data) {
            // One phone across several rows = one parent with several
            // children — count within the file too, so the reuse note shows
            // even before the first sibling's account exists.
            $seenInRow = [];

            foreach ((array) ($data['guardians'] ?? []) as $guardian) {
                $phone = PhoneNumber::normalize((string) (is_array($guardian) ? ($guardian['phone'] ?? '') : ''));
                if ($phone !== null && ! isset($seenInRow[$phone])) {
                    $seenInRow[$phone] = true;
                    $phones[] = $phone;
                }
            }

            if (! empty($data['fayda_id'])) {
                $faydaHashes[] = hash('sha256', trim((string) $data['fayda_id']));
            }

            if (! empty($data['national_student_id'])) {
                $nationalIds[] = mb_strtolower(trim((string) $data['national_student_id']));
            }
        }

        $repeated = array_keys(array_filter(array_count_values($phones), fn (int $count) => $count > 1));

        $existing = $phones === []
            ? []
            : User::withTrashed()->whereIn('phone', array_unique($phones))
                ->pluck('phone')->all();

        $this->knownPhones = array_fill_keys([...$existing, ...$repeated], true);

        // This school's whole register (its own students + anyone who ever
        // enrolled here) feeds all three duplicate keys.
        Student::query()
            ->select(['id', 'school_id', 'first_name', 'father_name', 'date_of_birth', 'fayda_hash', 'national_student_id'])
            ->where(function ($q): void {
                $q->where('school_id', $this->import->school_id)
                    ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $this->import->school_id));
            })
            ->chunkById(2000, function ($students): void {
                foreach ($students as $student) {
                    $this->index($student, inSchool: true);
                }
            });

        // Fayda / national IDs are NATIONAL identifiers — collisions at other
        // schools matter too, but only for the values this chunk carries.
        if ($faydaHashes !== [] || $nationalIds !== []) {
            Student::query()
                ->select(['id', 'school_id', 'first_name', 'father_name', 'date_of_birth', 'fayda_hash', 'national_student_id'])
                ->where(function ($q) use ($faydaHashes, $nationalIds): void {
                    if ($faydaHashes !== []) {
                        $q->whereIn('fayda_hash', array_unique($faydaHashes));
                    }
                    if ($nationalIds !== []) {
                        $q->orWhereIn('national_student_id', array_unique($nationalIds));
                    }
                })
                ->get()
                ->each(fn (Student $student) => $this->index($student, inSchool: false));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     data: array<string, mixed>,
     *     status: StudentImportRowStatus,
     *     issues: list<array{field: string, level: string, code: string, message: string}>,
     *     duplicate_student_id: ?int,
     *     resolution: ?string,
     * }
     */
    public function validate(array $data): array
    {
        if (! $this->primed) {
            $this->prime([$data]);
        }

        $data = $this->normalize($data);
        $issues = $this->ruleIssues($data);

        // Enrollment target: the row's own columns override the import-wide
        // defaults; a section implies its grade.
        [$sectionId, $gradeLevelId] = $this->resolveTarget($data, $issues);

        $duplicateId = null;

        if (! self::hasErrors($issues)) {
            $duplicateId = $this->detectDuplicate($data, $issues);
            $this->noteKnownGuardians($data, $issues);
        }

        $status = match (true) {
            self::hasErrors($issues) => StudentImportRowStatus::Error,
            $duplicateId !== null => StudentImportRowStatus::Duplicate,
            default => StudentImportRowStatus::Ready,
        };

        return [
            'data' => [...$data, 'section_id' => $sectionId, 'grade_level_id' => $gradeLevelId],
            'status' => $status,
            'issues' => $issues,
            'duplicate_student_id' => $duplicateId,
            'resolution' => $duplicateId !== null ? 'skip' : null,
        ];
    }

    private function index(Student $student, bool $inSchool): void
    {
        $entry = ['id' => $student->id, 'in_school' => $inSchool];

        if ($student->fayda_hash !== null) {
            $this->byFayda[$student->fayda_hash][] = $entry;
        }

        if ($student->national_student_id !== null) {
            $this->byNationalId[mb_strtolower($student->national_student_id)][] = $entry;
        }

        if ($student->date_of_birth !== null) {
            $this->byNameDob[self::nameDobKey(
                $student->first_name,
                $student->father_name,
                $student->date_of_birth->toDateString(),
            )][] = $entry;
        }
    }

    /**
     * Trim strings, canonicalize phones, drop empty guardian rows.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $trim = fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v;
        $data = array_map($trim, $data);

        if (! empty($data['primary_phone'])) {
            $data['primary_phone'] = PhoneNumber::normalize((string) $data['primary_phone']) ?? $data['primary_phone'];
        }

        $guardians = [];

        foreach ((array) ($data['guardians'] ?? []) as $guardian) {
            $guardian = array_map($trim, is_array($guardian) ? $guardian : []);

            // A template's guardian-2 block left blank is not a guardian.
            if (($guardian['first_name'] ?? null) === null && ($guardian['phone'] ?? null) === null) {
                continue;
            }

            foreach (['phone', 'secondary_phone'] as $field) {
                if (! empty($guardian[$field])) {
                    $guardian[$field] = PhoneNumber::normalize((string) $guardian[$field]) ?? $guardian[$field];
                }
            }

            $guardians[] = $guardian;
        }

        // Exactly one primary guardian: respect an explicit flag, else the
        // first listed one.
        if ($guardians !== [] && ! array_any($guardians, fn ($g) => ! empty($g['is_primary']))) {
            $guardians[0]['is_primary'] = true;
        }

        $data['guardians'] = $guardians;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{field: string, level: string, code: string, message: string}>
     */
    private function ruleIssues(array $data): array
    {
        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'grandfather_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', new BirthDate(minAgeYears: 1)],
            'national_student_id' => ['nullable', 'string', 'max:50'],
            'fayda_id' => ['nullable', 'string', 'max:50'],
            'primary_phone' => ['nullable', 'string', 'max:20', new EthiopianPhone()],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'sub_city' => ['nullable', 'string', 'max:100'],
            'woreda' => ['nullable', 'string', 'max:100'],
            'house_no' => ['nullable', 'string', 'max:50'],
            'grade_level_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'school_program_id' => ['nullable', 'integer'],
            'guardians' => ['array', 'max:2'],
            'guardians.*.first_name' => ['required', 'string', 'max:255'],
            'guardians.*.father_name' => ['nullable', 'string', 'max:255'],
            'guardians.*.grandfather_name' => ['nullable', 'string', 'max:255'],
            'guardians.*.phone' => ['required', 'string', 'max:20', new EthiopianPhone()],
            'guardians.*.secondary_phone' => ['nullable', 'string', 'max:20', new EthiopianPhone()],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'guardians.*.occupation' => ['nullable', 'string', 'max:255'],
        ]);

        $issues = [];

        foreach ($validator->errors()->toArray() as $field => $messages) {
            $issues[] = [
                'field' => $field,
                'level' => 'error',
                'code' => str_contains(mb_strtolower($messages[0] ?? ''), 'required') ? 'required' : 'invalid',
                'message' => $messages[0] ?? 'Invalid value.',
            ];
        }

        return $issues;
    }

    /**
     * Resolve the row's enrollment target against the import defaults and the
     * branch's section register + grade offering.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{field: string, level: string, code: string, message: string}>  $issues
     * @return array{0: ?int, 1: ?int} [section_id, grade_level_id]
     */
    private function resolveTarget(array $data, array &$issues): array
    {
        $sectionId = ! empty($data['section_id']) ? (int) $data['section_id'] : $this->import->section_id;
        $gradeLevelId = ! empty($data['grade_level_id']) ? (int) $data['grade_level_id'] : $this->import->grade_level_id;

        if ($sectionId !== null) {
            $section = $this->sections()[$sectionId] ?? null;

            if ($section === null) {
                $issues[] = [
                    'field' => 'section_id', 'level' => 'error', 'code' => 'section_unknown',
                    'message' => 'This section does not belong to the import branch.',
                ];

                return [null, $gradeLevelId];
            }

            if ($gradeLevelId !== null && $section->grade_level_id !== $gradeLevelId) {
                $issues[] = [
                    'field' => 'section_id', 'level' => 'error', 'code' => 'section_grade_mismatch',
                    'message' => 'The section belongs to a different grade than the row specifies.',
                ];
            }

            $gradeLevelId = $section->grade_level_id;
        }

        if ($gradeLevelId === null) {
            $issues[] = [
                'field' => 'grade_level_id', 'level' => 'error', 'code' => 'grade_required',
                'message' => 'A grade is required — set one on the row or pick an import default.',
            ];

            return [$sectionId, null];
        }

        // The branch's grade × program offering gates enrollment; catching it
        // here keeps the failure on the validation grid, not the import run.
        try {
            GradeOffering::assertOffered($this->import->branch, $gradeLevelId);
        } catch (ValidationException) {
            $issues[] = [
                'field' => 'grade_level_id', 'level' => 'error', 'code' => 'grade_not_offered',
                'message' => 'This branch does not offer this grade.',
            ];
        }

        return [$sectionId, $gradeLevelId];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{field: string, level: string, code: string, message: string}>  $issues
     */
    private function detectDuplicate(array $data, array &$issues): ?int
    {
        $matches = [];

        if (! empty($data['fayda_id'])) {
            $matches = $this->byFayda[hash('sha256', (string) $data['fayda_id'])] ?? [];
        }

        if ($matches === [] && ! empty($data['national_student_id'])) {
            $matches = $this->byNationalId[mb_strtolower((string) $data['national_student_id'])] ?? [];
        }

        if ($matches === [] && ! empty($data['date_of_birth'])) {
            $matches = $this->byNameDob[self::nameDobKey(
                (string) $data['first_name'],
                (string) $data['father_name'],
                (string) $data['date_of_birth'],
            )] ?? [];
        }

        if ($matches === []) {
            return null;
        }

        $inSchool = array_values(array_filter($matches, fn ($m) => $m['in_school']));

        if ($inSchool === []) {
            // A Fayda / national-ID match elsewhere on Temari: creating a
            // fresh person is allowed, but moving THAT student here is the
            // transfer lane's job — say so.
            $issues[] = [
                'field' => 'first_name', 'level' => 'warning', 'code' => 'exists_other_school',
                'message' => 'A matching student exists at another Temari school — if this is a transfer, use the transfer lane.',
            ];

            return null;
        }

        $studentId = $inSchool[0]['id'];

        $issues[] = [
            'field' => 'first_name', 'level' => 'warning', 'code' => 'duplicate_in_school',
            'message' => 'A student with the same identity already exists at this school.',
        ];

        if ($this->alreadyEnrolled($studentId)) {
            $issues[] = [
                'field' => 'first_name', 'level' => 'info', 'code' => 'already_enrolled',
                'message' => 'The matched student already has a live enrollment for this year.',
            ];
        }

        return $studentId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{field: string, level: string, code: string, message: string}>  $issues
     */
    private function noteKnownGuardians(array $data, array &$issues): void
    {
        foreach ($data['guardians'] as $index => $guardian) {
            $phone = $guardian['phone'] ?? null;

            if ($phone !== null && ($this->knownPhones[$phone] ?? false)) {
                $issues[] = [
                    'field' => "guardians.{$index}.phone", 'level' => 'info', 'code' => 'guardian_existing',
                    'message' => 'An account with this phone already exists — the existing parent will be linked, never duplicated.',
                ];
            }
        }
    }

    private function alreadyEnrolled(int $studentId): bool
    {
        return Student::query()->whereKey($studentId)
            ->whereHas('enrollments', function ($q): void {
                $q->where('academic_year_id', $this->import->academic_year_id)->live();
            })
            ->exists();
    }

    private static function nameDobKey(string $first, string $father, string $dob): string
    {
        return mb_strtolower(trim($first)).'|'.mb_strtolower(trim($father)).'|'.substr($dob, 0, 10);
    }

    /**
     * @param  list<array{field: string, level: string, code: string, message: string}>  $issues
     */
    private static function hasErrors(array $issues): bool
    {
        return array_any($issues, fn ($issue) => $issue['level'] === 'error');
    }

    /** @return array<int, Section> */
    private function sections(): array
    {
        return $this->sections ??= Section::query()
            ->where('branch_id', $this->import->branch_id)
            ->get()
            ->keyBy('id')
            ->all();
    }
}
