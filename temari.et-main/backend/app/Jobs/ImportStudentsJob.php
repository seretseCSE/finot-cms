<?php

namespace App\Jobs;

use App\Actions\EnrollStudentAction;
use App\Actions\LinkStudentLoginAction;
use App\Actions\RegisterStudentAction;
use App\Enums\StudentImportRowStatus;
use App\Enums\StudentImportStatus;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Services\Notify\Notifier;
use App\Support\CommsMute;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Executes a committed student import: every importable row runs through the
 * SAME actions as the registration wizard (RegisterStudentAction /
 * EnrollStudentAction) in its OWN transaction — one bad row fails alone and
 * the run continues (mirrors RolloverPromotionsAction's partial-safe model).
 * Unless the operator explicitly enabled sending, the whole run executes
 * inside CommsMute: no SMS, no email, no setup links — a wrong file must
 * never text a thousand families.
 *
 * Rows are read in row order and their statuses updated as they land, so a
 * retried/re-dispatched job resumes where it stopped instead of re-importing.
 */
class ImportStudentsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $importId)
    {
    }

    public function handle(
        RegisterStudentAction $register,
        EnrollStudentAction $enroll,
        LinkStudentLoginAction $linkLogin,
        Notifier $notifier,
    ): void {
        $import = StudentImport::with('branch')->find($this->importId);

        if ($import === null || $import->status !== StudentImportStatus::Importing) {
            return;
        }

        // Duplicates the registrar left on "skip" are settled up front.
        $skipped = $import->rows()
            ->where('status', StudentImportRowStatus::Duplicate->value)
            ->where(fn ($q) => $q->where('resolution', 'skip')->orWhereNull('resolution'))
            ->update(['status' => StudentImportRowStatus::Skipped->value]);

        $import->increment('skipped_count', $skipped);

        $run = function () use ($import, $register, $enroll, $linkLogin): void {
            $import->importableRows()
                ->orderBy('row_number')
                ->chunkById(100, function ($rows) use ($import, $register, $enroll, $linkLogin): void {
                    foreach ($rows as $row) {
                        $this->importRow($import, $row, $register, $enroll, $linkLogin);
                    }
                });
        };

        $import->sendSms() ? $run() : CommsMute::run($run);

        $import->update([
            'status' => StudentImportStatus::Completed->value,
            'finished_at' => now(),
        ]);

        $notifier->toUser($import->creator, 'system.student_import_completed', [
            'file' => $import->file_name,
            'imported' => $import->imported_count,
            'failed' => $import->failed_count,
        ], [
            'link' => "/students/import/{$import->id}",
            'schoolId' => $import->school_id,
            'branchId' => $import->branch_id,
        ]);
    }

    private function importRow(
        StudentImport $import,
        StudentImportRow $row,
        RegisterStudentAction $register,
        EnrollStudentAction $enroll,
        LinkStudentLoginAction $linkLogin,
    ): void {
        $data = $row->data;

        try {
            if ($row->status === StudentImportRowStatus::Duplicate && $row->resolution === 'enroll_existing') {
                // Returning student: enroll the matched person — profile,
                // documents and guardians stay as they are.
                $student = $row->duplicateStudent()->firstOrFail();

                DB::transaction(function () use ($enroll, $student, $import, $data): void {
                    $enroll->execute($student, [
                        'academic_year_id' => $import->academic_year_id,
                        'section_id' => $data['section_id'] ?? null,
                        'grade_level_id' => $data['grade_level_id'] ?? null,
                        'school_program_id' => $data['school_program_id'] ?? $import->school_program_id,
                    ]);
                });
            } else {
                $student = DB::transaction(fn () => $register->execute(
                    $import->branch,
                    $this->registrationPayload($import, $data),
                    $import->created_by,
                ));
            }

            $row->update([
                'status' => StudentImportRowStatus::Imported->value,
                'student_id' => $student->id,
                'error' => null,
            ]);
            $import->increment('imported_count');

            // Account provisioning is BEST-EFFORT and outside the row's
            // transaction — a student without a reachable guardian phone must
            // not lose their registration over a login detail.
            if ($import->createStudentAccounts()) {
                $this->provisionAccount($row, $student->id, $linkLogin, $data);
            }
        } catch (ValidationException $e) {
            $this->failRow($import, $row, collect($e->errors())->flatten()->implode(' '));
        } catch (\Throwable $e) {
            Log::warning('Student import row failed.', [
                'import_id' => $import->id, 'row' => $row->row_number, 'error' => $e->getMessage(),
            ]);
            $this->failRow($import, $row, 'Unexpected error while importing this row.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function registrationPayload(StudentImport $import, array $data): array
    {
        return [
            ...collect($data)->except(['guardians', 'section_id', 'grade_level_id', 'school_program_id'])->all(),
            'academic_year_id' => $import->academic_year_id,
            'section_id' => $data['section_id'] ?? null,
            'grade_level_id' => $data['grade_level_id'] ?? null,
            'school_program_id' => $data['school_program_id'] ?? $import->school_program_id,
            'guardians' => $data['guardians'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function provisionAccount(StudentImportRow $row, int $studentId, LinkStudentLoginAction $linkLogin, array $data): void
    {
        try {
            $student = Student::findOrFail($studentId);

            DB::transaction(fn () => $linkLogin->execute(
                $student,
                $data['primary_phone'] ?? null,
                $data['email'] ?? null,
            ));
        } catch (\Throwable $e) {
            // Recorded on the row, never fatal — staff can provision from the
            // student profile later.
            $row->update([
                'issues' => [...($row->issues ?? []), [
                    'field' => 'create_user_account', 'level' => 'warning', 'code' => 'account_failed',
                    'message' => $e instanceof ValidationException
                        ? collect($e->errors())->flatten()->implode(' ')
                        : 'Could not provision a login account.',
                ]],
            ]);
        }
    }

    private function failRow(StudentImport $import, StudentImportRow $row, string $error): void
    {
        $row->update([
            'status' => StudentImportRowStatus::Failed->value,
            'error' => mb_substr($error, 0, 500),
        ]);
        $import->increment('failed_count');
    }

    public function failed(?\Throwable $exception): void
    {
        StudentImport::query()->whereKey($this->importId)->update([
            'status' => StudentImportStatus::Failed->value,
            'finished_at' => now(),
        ]);
    }
}
