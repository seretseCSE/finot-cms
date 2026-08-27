<?php

namespace App\Ai\Tools\Family;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Attendance for the student/child: current-term rate, counts by status, the
 * most recent absences/lates (with dates) and simple pattern hints (e.g.
 * repeated weekday) the model can point out.
 */
class StudentAttendanceTool extends StudentScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get attendance: current-term present/absent/late/excused counts, attendance rate, and the most recent absence and late dates. Use for any question about attendance, absences or punctuality.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$student, $link, $denial] = $this->resolveStudent($request->integer('student_id') ?: null);

        if ($denial !== null) {
            return $this->deny($denial);
        }

        if (! $this->linkAllows($link, 'can_view_attendance')) {
            return $this->deny('Your guardian link does not permit viewing this student\'s attendance.');
        }

        $enrollment = $student->currentEnrollment;

        if ($enrollment === null) {
            return $this->deny('The student has no active enrollment.');
        }

        $term = $this->context->currentTerm($enrollment->branch_id);

        $query = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->when($term !== null, fn ($q) => $q->where('term_id', $term->id));

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $total = (int) $byStatus->sum();
        $present = (int) ($byStatus['present'] ?? 0) + (int) ($byStatus['late'] ?? 0);

        $recentIssues = (clone $query)
            ->whereIn('status', ['absent', 'late'])
            ->orderByDesc('date')
            ->limit(15)
            ->get(['date', 'status', 'note'])
            ->map(fn (AttendanceRecord $record): array => [
                'date' => $record->date,
                'weekday' => Carbon::parse($record->date)->format('l'),
                'status' => $record->status,
            ]);

        return $this->ok([
            'student' => $student->full_name,
            'term' => $term?->name,
            'days_recorded' => $total,
            'counts' => $byStatus,
            'attendance_rate_percent' => $total > 0 ? round($present * 100 / $total, 1) : null,
            'recent_absences_and_lates' => $recentIssues,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('Parent lane only: the child to look at (from my_children). Omit in the student lane.'),
        ];
    }
}
