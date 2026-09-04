<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AbsenceExcuseStatus;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\Notify\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The staff side of parent-filed absence excuses: the branch's review queue
 * and the decision. Approval retro-marks the range's ABSENT attendance
 * records as excused inside one transaction; the excuse row is the audit
 * trail (who asked, who decided, what changed).
 */
class AbsenceExcuseController extends Controller
{
    use HandlesBulkActions;

    public function index(Request $request): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id
            ?? $this->activeSchoolScopeId($request)
            ?? $this->schoolFilterId($request, $branch);

        abort_if($schoolId === null, 422, 'Select a school context to view absence excuses.');

        $user = $request->user();
        abort_unless(
            $user->hasPermissionForScope('attendance.view', $schoolId, $branch?->id)
            || $user->hasPermissionForScope('attendance.record', $schoolId, $branch?->id),
            403,
        );

        $filterBranchId = $this->branchFilterId($request, $branch);

        $excuses = AbsenceExcuse::query()
            ->where('school_id', $schoolId)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($filterBranchId !== null, fn ($q) => $q->where('branch_id', $filterBranchId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with([
                'student:id,first_name,father_name,grandfather_name,public_id,photo_path',
                'student.currentEnrollment.section:id,name',
                'student.currentEnrollment.gradeLevel:id,name',
                'requester:id,name,phone',
                'decider:id,name',
                'branch:id,name',
            ])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($excuses->items())->map(fn (AbsenceExcuse $excuse): array => $this->present($excuse)),
            'meta' => [
                'current_page' => $excuses->currentPage(),
                'last_page' => $excuses->lastPage(),
                'total' => $excuses->total(),
            ],
        ]);
    }

    /** Approve or reject one pending excuse. */
    public function decide(Request $request, AbsenceExcuse $absenceExcuse, Notifier $notifier): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('attendance.record', $absenceExcuse->school_id, $absenceExcuse->branch_id),
            403,
        );
        abort_unless($absenceExcuse->status === AbsenceExcuseStatus::Pending, 422, 'This excuse was already decided.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $excusedDays = $this->applyDecision(
            $absenceExcuse,
            $data['decision'],
            $data['note'] ?? null,
            $request->user(),
            $notifier,
        );

        return response()->json([
            'data' => [...$this->present($absenceExcuse->refresh()->load('decider:id,name')), 'excused_days' => $excusedDays],
            'message' => $data['decision'] === 'approved' ? 'Excuse approved.' : 'Excuse rejected.',
        ]);
    }

    /**
     * Clear the review queue in one pass — a Monday morning of parent notes is
     * a stack of near-identical decisions. Each excuse is permission-checked in
     * its own branch; anything already decided, or outside the reviewer's scope,
     * is skipped and reported rather than failing the batch.
     */
    public function bulkDecide(Request $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validate([
            ...self::bulkIdRules(),
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user();
        $decided = 0;
        $excusedDays = 0;
        $skipped = [];

        $rows = $this->bulkRows(
            $data['ids'],
            AbsenceExcuse::with('student:id,first_name,father_name,grandfather_name,user_id'),
            $skipped,
        );

        foreach ($rows as $excuse) {
            $name = $excuse->student?->full_name;

            if (! $actor->hasPermissionForScope('attendance.record', $excuse->school_id, $excuse->branch_id)) {
                $skipped[] = self::skipRow($excuse, $name, 'not_permitted');

                continue;
            }

            if ($excuse->status !== AbsenceExcuseStatus::Pending) {
                $skipped[] = self::skipRow($excuse, $name, 'already_decided');

                continue;
            }

            $excusedDays += $this->applyDecision($excuse, $data['decision'], $data['note'] ?? null, $actor, $notifier);
            $decided++;
        }

        return response()->json([
            'message' => "{$decided} excuse(s) decided.",
            'meta' => [
                'decided' => $decided,
                'requested' => count($data['ids']),
                'excused_days' => $excusedDays,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * The decision itself — shared by the single and bulk paths so approving one
     * excuse and approving fifty can never diverge. Returns how many attendance
     * days flipped to excused.
     */
    private function applyDecision(
        AbsenceExcuse $excuse,
        string $decision,
        ?string $note,
        User $actor,
        Notifier $notifier,
    ): int {
        $excusedDays = DB::transaction(function () use ($excuse, $decision, $note, $actor): int {
            $updated = 0;

            if ($decision === 'approved') {
                // Only recorded ABSENCES become excused — presence, lateness
                // and already-excused days stay exactly as marked.
                $updated = AttendanceRecord::query()
                    ->where('student_id', $excuse->student_id)
                    ->whereBetween('date', [
                        $excuse->starts_on->toDateString(),
                        $excuse->ends_on->toDateString(),
                    ])
                    ->where('status', AttendanceStatus::Absent->value)
                    ->update(['status' => AttendanceStatus::Excused->value]);
            }

            $excuse->update([
                'status' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $updated;
        });

        $notifier->toFamily($excuse->student, 'attendance.excuse_decided', [
            'student' => (string) $excuse->student?->full_name,
            'decision' => $decision,
            'from' => $excuse->starts_on->toDateString(),
            'to' => $excuse->ends_on->toDateString(),
        ], [
            'link' => '/me/attendance',
            'schoolId' => $excuse->school_id,
            'branchId' => $excuse->branch_id,
            'dedupeKey' => "excuse_decided:{$excuse->id}",
        ]);

        return $excusedDays;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AbsenceExcuse $excuse): array
    {
        $enrollment = $excuse->student?->currentEnrollment;

        return [
            'id' => $excuse->id,
            'student_id' => $excuse->student_id,
            'student_name' => $excuse->student?->full_name,
            'student_public_id' => $excuse->student?->public_id,
            'student_photo_url' => $excuse->student?->photo_url,
            'grade_level' => $enrollment?->gradeLevel?->name,
            'section' => $enrollment?->section?->name,
            'branch_id' => $excuse->branch_id,
            'branch_name' => $excuse->branch?->name,
            'requester_name' => $excuse->requester?->name,
            'requester_phone' => $excuse->requester?->phone,
            'starts_on' => $excuse->starts_on->toDateString(),
            'ends_on' => $excuse->ends_on->toDateString(),
            'reason' => $excuse->reason,
            'attachment_url' => $excuse->attachment_path !== null ? s3Url($excuse->attachment_path) : null,
            'status' => $excuse->status->value,
            'decided_by' => $excuse->decider?->name,
            'decided_at' => $excuse->decided_at?->toISOString(),
            'decision_note' => $excuse->decision_note,
            'created_at' => $excuse->created_at?->toISOString(),
        ];
    }
}
