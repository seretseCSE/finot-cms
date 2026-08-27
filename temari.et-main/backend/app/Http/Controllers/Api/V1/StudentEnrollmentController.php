<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\EnrollStudentAction;
use App\Actions\UpdateEnrollmentAction;
use App\Actions\WithdrawStudentAction;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEnrollmentRequest;
use App\Http\Resources\StudentEnrollmentResource;
use App\Models\GeneratedDocument;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentWithdrawal;
use App\Services\EnrollmentGate;
use App\Services\TransferNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentController extends Controller
{
    use HandlesBulkActions;

    public function store(
        StoreEnrollmentRequest $request,
        Student $student,
        EnrollStudentAction $action,
    ): JsonResponse {
        $this->authorize('enroll', $student);

        $enrollment = $action->execute($student, $request->validated());

        return (new StudentEnrollmentResource($enrollment->load(['section', 'gradeLevel', 'academicYear'])))
            ->additional(['message' => 'Student enrolled.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Correct a live enrollment in place — the "assigned to the wrong grade
     * by mistake" fix. Same authority as enrolling; the action carries the
     * guard rails (live status, frozen results, offering, section match).
     */
    public function update(
        Request $request,
        StudentEnrollment $enrollment,
        UpdateEnrollmentAction $action,
    ): StudentEnrollmentResource {
        $this->authorize('enroll', $enrollment->student);

        $data = $request->validate([
            'grade_level_id' => ['sometimes', 'required', 'integer', 'exists:grade_levels,id'],
            'section_id' => ['sometimes', 'nullable', 'integer', 'exists:sections,id'],
            'school_program_id' => ['sometimes', 'nullable', 'integer', 'exists:school_programs,id'],
            'enrolled_on' => ['sometimes', 'nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
        ]);

        $enrollment = $action->execute($enrollment, $data, $request->user());

        return (new StudentEnrollmentResource(
            $enrollment->load(['section', 'gradeLevel', 'academicYear']),
        ))->additional(['message' => 'Enrollment updated.']);
    }

    /**
     * Activate a pending enrollment ahead of (or after) fee settlement. The
     * gate enforces the school's soft/hard registration policy.
     */
    public function activate(
        Request $request,
        StudentEnrollment $enrollment,
        EnrollmentGate $gate,
    ): StudentEnrollmentResource {
        $this->authorize('enroll', $enrollment->student);

        $gate->activateManually($enrollment, $request->user());

        return (new StudentEnrollmentResource(
            $enrollment->refresh()->load(['section', 'gradeLevel', 'academicYear']),
        ))->additional(['message' => 'Enrollment activated.']);
    }

    /**
     * Activate a selection of fee-gated enrollments — intake week, once the
     * registration payments are in. Each row goes through the same gate as the
     * single-row path, so a hard-gate branch still refuses unpaid rows; those
     * (and already-active ones) are skipped and reported by student name.
     */
    public function bulkActivate(Request $request, EnrollmentGate $gate): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $activated = 0;
        $skipped = [];

        $rows = $this->bulkRows(
            $data['ids'],
            StudentEnrollment::with('student:id,first_name,father_name,grandfather_name'),
            $skipped,
        );

        foreach ($rows as $enrollment) {
            $name = $enrollment->student?->full_name;

            if ($enrollment->student === null || $actor->cannot('enroll', $enrollment->student)) {
                $skipped[] = self::skipRow($enrollment, $name, 'not_permitted');

                continue;
            }

            if ($enrollment->status !== EnrollmentStatus::Pending) {
                $skipped[] = self::skipRow($enrollment, $name, 'not_pending');

                continue;
            }

            try {
                $gate->activateManually($enrollment, $actor);
            } catch (ValidationException) {
                // Hard-gate branch with the registration fee still unsettled.
                $skipped[] = self::skipRow($enrollment, $name, 'fee_unpaid');

                continue;
            }

            $activated++;
        }

        return response()->json([
            'message' => "{$activated} enrollment(s) activated.",
            'meta' => ['activated' => $activated, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Mid-year withdrawal — leaving school entirely or moving OUTSIDE Temari
     * (in-platform moves go through the transfer flow). Gated on the same
     * student-movement authority as transfers.
     */
    public function withdraw(
        Request $request,
        StudentEnrollment $enrollment,
        WithdrawStudentAction $action,
    ): JsonResponse {
        abort_unless(
            $request->user()->hasPermissionForScope('transfers.manage', $enrollment->school_id, $enrollment->branch_id),
            403,
        );

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'destination' => ['nullable', 'string', 'max:255'],
            'withdrawn_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
        ]);

        $withdrawal = $action->execute($enrollment, $data, $request->user());

        app(TransferNotifier::class)->withdrawn($withdrawal);

        return response()->json([
            'data' => [
                'id' => $withdrawal->id,
                'enrollment_id' => $withdrawal->enrollment_id,
                'outstanding_amount' => (string) $withdrawal->outstanding_amount,
            ],
            'message' => 'Student withdrawn.',
        ], 201);
    }

    /**
     * Printable clearance-letter payload for a withdrawn enrollment. First
     * open mints the public verification token the letter's QR points at.
     */
    public function withdrawalLetter(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('transfers.manage', $enrollment->school_id, $enrollment->branch_id),
            403,
        );

        $withdrawal = StudentWithdrawal::query()
            ->where('enrollment_id', $enrollment->id)
            ->firstOrFail();

        if ($withdrawal->public_token === null) {
            $withdrawal->forceFill(['public_token' => Str::random(48)])->save();
        }

        return response()->json(['data' => self::withdrawalLetterPayload($withdrawal)]);
    }

    /**
     * UNAUTHENTICATED letter view behind the unguessable token — what the QR
     * code on the printed clearance letter resolves to.
     */
    public function publicWithdrawalLetter(string $token): JsonResponse
    {
        $withdrawal = StudentWithdrawal::query()
            ->where('public_token', $token)
            ->firstOrFail();

        // Printing goes through the OFFICIAL PDF (pre-warmed when the student
        // was withdrawn), never the browser's print of this web page.
        return response()->json(['data' => [
            ...self::withdrawalLetterPayload($withdrawal),
            ...GeneratedDocument::publicUrlsFor('withdrawal_letter', $withdrawal),
        ]]);
    }

    /**
     * Also feeds the backend PDF letter (WithdrawalLetterDocument).
     *
     * @return array<string, mixed>
     */
    public static function withdrawalLetterPayload(StudentWithdrawal $withdrawal): array
    {
        $withdrawal->load([
            'student',
            'school:id,name',
            'branch:id,name',
            'withdrawnBy:id,name',
            'enrollment.gradeLevel:id,name',
            'enrollment.section:id,name',
            'enrollment.academicYear:id,name',
        ]);

        $student = $withdrawal->student;

        return [
            'id' => $withdrawal->id,
            'reference' => sprintf('WD-%05d', $withdrawal->id),
            'public_token' => $withdrawal->public_token,
            'student' => [
                'full_name' => $student->full_name,
                'public_id' => $student->public_id,
                'gender' => $student->gender,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'photo_url' => $student->photo_url,
            ],
            'school' => $withdrawal->school->name,
            'branch' => $withdrawal->branch->name,
            'last_grade' => $withdrawal->enrollment->gradeLevel?->name,
            'last_section' => $withdrawal->enrollment->section?->name,
            'last_academic_year' => $withdrawal->enrollment->academicYear?->name,
            'destination' => $withdrawal->destination,
            'reason' => $withdrawal->reason,
            'withdrawn_on' => $withdrawal->withdrawn_on->toDateString(),
            'outstanding_amount' => (string) $withdrawal->outstanding_amount,
            'issued_by' => $withdrawal->withdrawnBy?->name,
        ];
    }
}
