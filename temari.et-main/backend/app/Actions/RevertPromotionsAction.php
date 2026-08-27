<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PromotionDecision;
use App\Models\AcademicYear;
use App\Models\AssessmentResult;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\QuizAttempt;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use App\Models\User;
use App\Services\EnrollmentGate;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The safety net under the year rollover: puts EXECUTED promotion decisions
 * back to "decided, not executed" — per student, each in its own transaction,
 * skip-and-report like the rollover itself (no database-restore heroics).
 *
 * Per decision: the next-year enrollment is removed (soft delete), its
 * auto-billed unpaid invoices are voided, the source enrollment goes live
 * again (through the registration-fee gate, so a fee-gated student returns
 * to `pending`, not blanket `active`), and the decision row keeps its
 * decided-by/decision so the board can re-run the rollover after fixing it.
 *
 * A student whose NEW enrollment has accumulated real life — attendance,
 * marks, exam attempts, frozen term results, or money received — is skipped
 * with a named reason: reverting would orphan records, and that judgement
 * belongs to a person, not this action. Transfer rows never revert here —
 * the transfer workflow owns their lifecycle.
 */
class RevertPromotionsAction
{
    public function __construct(private readonly EnrollmentGate $gate) {}

    /**
     * @param  list<int>|null  $enrollmentIds  source (from) enrollment ids — null reverts the whole executed batch
     * @return array{reverted: int, skipped: int, errors: list<array{enrollment_id: int, student: string, message: string}>}
     */
    public function execute(AcademicYear $fromYear, User $actor, ?int $gradeLevelId = null, ?array $enrollmentIds = null): array
    {
        $decisions = StudentPromotion::query()
            ->where('academic_year_id', $fromYear->id)
            ->whereNotNull('executed_at')
            ->where('decision', '!=', PromotionDecision::Transferred->value)
            ->when($gradeLevelId !== null, fn ($q) => $q->where('from_grade_level_id', $gradeLevelId))
            ->when($enrollmentIds !== null, fn ($q) => $q->whereIn('from_enrollment_id', $enrollmentIds))
            ->with(['fromEnrollment.branch.school', 'fromEnrollment.student', 'toEnrollment.academicYear', 'student:id,first_name,father_name'])
            ->get();

        $reverted = 0;
        $errors = [];

        foreach ($decisions as $decision) {
            try {
                DB::transaction(fn () => $this->revertOne($decision));
                $reverted++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'enrollment_id' => $decision->from_enrollment_id,
                    'student' => trim("{$decision->student->first_name} {$decision->student->father_name}"),
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
            } catch (Throwable $e) {
                $errors[] = [
                    'enrollment_id' => $decision->from_enrollment_id,
                    'student' => trim("{$decision->student->first_name} {$decision->student->father_name}"),
                    'message' => 'Unexpected error — this student was skipped.',
                ];
                report($e);
            }
        }

        ActivityLogger::log(
            actor: $actor,
            action: 'promotion.revert',
            subject: $fromYear,
            properties: ['reverted' => $reverted, 'errors' => count($errors)],
            schoolId: $fromYear->school_id,
            branchId: $fromYear->branch_id,
        );

        return ['reverted' => $reverted, 'skipped' => count($errors), 'errors' => $errors];
    }

    private function revertOne(StudentPromotion $decision): void
    {
        $from = $decision->fromEnrollment;

        // Only untouched-since-rollover sources revert: a source someone has
        // meanwhile re-activated or moved by hand needs a human eye instead.
        if ($from->status !== $decision->decision->enrollmentStatus()) {
            throw ValidationException::withMessages([
                'enrollment' => ['The source enrollment changed since the rollover — review this student by hand.'],
            ]);
        }

        $to = $decision->toEnrollment;

        if ($to !== null) {
            $this->assertRevertible($to);
            $this->voidGeneratedInvoices($to);
            $to->delete();
        }

        $from->update([
            'status' => $this->gate->initialStatus($from),
            'exited_on' => null,
        ]);

        // Back to "decided, not executed" — the board can fix the decision
        // and re-run the rollover for exactly these students.
        $decision->update([
            'to_enrollment_id' => null,
            'to_grade_level_id' => null,
            'to_branch_id' => null,
            'executed_at' => null,
        ]);

        app(Notifier::class)->toFamily($from->student, 'academics.enrollment_reverted', [
            'school' => $from->branch?->school?->name ?? '',
            'year' => $to?->academicYear?->name ?? '',
        ], [
            'link' => '/me/children',
            'schoolId' => $from->school_id,
            'branchId' => $from->branch_id,
            'dedupeKey' => 'promotion-revert-'.$decision->id,
        ]);
    }

    /**
     * The new-year enrollment may only vanish while it is still an empty
     * shell — any real record it accumulated makes the revert a human call.
     */
    private function assertRevertible(StudentEnrollment $to): void
    {
        $fail = function (string $message): never {
            throw ValidationException::withMessages(['enrollment' => [$message]]);
        };

        if (! in_array($to->status, [EnrollmentStatus::Pending, EnrollmentStatus::Active], true)) {
            $fail('The new year\'s enrollment has itself been rolled forward or closed — review this student by hand.');
        }

        if (AttendanceRecord::query()
            ->where('student_id', $to->student_id)
            ->where('branch_id', $to->branch_id)
            ->where('academic_year_id', $to->academic_year_id)
            ->exists()) {
            $fail('Attendance has already been recorded in the new year.');
        }

        if (AssessmentResult::query()
            ->where('student_id', $to->student_id)
            ->whereHas('assessment.subjectAssignment', fn ($q) => $q
                ->where('branch_id', $to->branch_id)
                ->whereHas('term', fn ($t) => $t->where('academic_year_id', $to->academic_year_id)))
            ->exists()) {
            $fail('Marks have already been recorded in the new year.');
        }

        if (QuizAttempt::query()->where('student_enrollment_id', $to->id)->exists()) {
            $fail('The student has already taken exams in the new year.');
        }

        if (StudentTermResult::query()->where('student_enrollment_id', $to->id)->exists()) {
            $fail('The new year already has frozen semester results.');
        }

        if (Invoice::query()
            ->where('student_id', $to->student_id)
            ->where('branch_id', $to->branch_id)
            ->where('academic_year_id', $to->academic_year_id)
            ->where(fn ($q) => $q
                ->whereIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Partial->value])
                ->orWhere('amount_paid', '>', 0))
            ->exists()) {
            $fail('A payment was already received for the new year — refund or void it in Finance first.');
        }
    }

    /**
     * Void the bills the rollover's enrollment generated (registration fee,
     * early recurring bills) so no family is chased for a year the student is
     * no longer entering. With payments already blocked above, everything
     * here is zero-paid. Dual enrollments (a second live program year at the
     * same branch) keep their bills — ownership is ambiguous, finance decides.
     */
    private function voidGeneratedInvoices(StudentEnrollment $to): void
    {
        $hasSibling = StudentEnrollment::query()
            ->where('student_id', $to->student_id)
            ->where('academic_year_id', $to->academic_year_id)
            ->where('branch_id', $to->branch_id)
            ->where('id', '!=', $to->id)
            ->live()
            ->exists();

        if ($hasSibling) {
            return;
        }

        Invoice::query()
            ->where('student_id', $to->student_id)
            ->where('branch_id', $to->branch_id)
            ->where('academic_year_id', $to->academic_year_id)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Scholarship->value])
            ->where('amount_paid', 0)
            ->update(['status' => InvoiceStatus::Void->value]);
    }
}
