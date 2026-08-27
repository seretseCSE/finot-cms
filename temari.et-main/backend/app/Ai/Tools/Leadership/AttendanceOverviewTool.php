<?php

namespace App\Ai\Tools\Leadership;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Student attendance health in scope: status totals + rate for a window,
 * per-section rates, and the students with the most absences (chronic
 * absentee watchlist).
 */
class AttendanceOverviewTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Attendance analytics for a date window (default: last 30 days): overall rate, per-section rates, and the students with the most absences. Use for questions about attendance, absenteeism, or punctuality trends.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('attendance.reports.view', 'attendance.view', 'reports.view')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $from = $this->parseDate($request->string('from')->toString()) ?? now()->subDays(30)->toDateString();
        $to = $this->parseDate($request->string('to')->toString()) ?? now()->toDateString();

        $base = AttendanceRecord::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('date', [$from, $to]);

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $total = (int) $byStatus->sum();

        if ($total === 0) {
            return $this->deny('No attendance records in that window.');
        }

        $present = (int) ($byStatus['present'] ?? 0) + (int) ($byStatus['late'] ?? 0);

        $bySection = (clone $base)
            ->join('sections', 'sections.id', '=', 'attendance_records.section_id')
            ->selectRaw("sections.name as section,
                count(*) as records,
                count(*) filter (where status in ('present','late')) as present_or_late,
                count(*) filter (where status = 'absent') as absent")
            ->groupBy('sections.name')
            ->orderByRaw('count(*) filter (where status = \'absent\')::float / greatest(count(*), 1) desc')
            ->limit(40)
            ->get()
            ->map(fn ($row): array => [
                'section' => $row->section,
                'attendance_rate_percent' => $row->records > 0 ? round($row->present_or_late * 100 / $row->records, 1) : null,
                'absences' => (int) $row->absent,
            ]);

        $chronic = (clone $base)
            ->where('status', 'absent')
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->join('sections', 'sections.id', '=', 'attendance_records.section_id')
            ->selectRaw("concat_ws(' ', students.first_name, students.father_name) as student, sections.name as section, count(*) as absences")
            ->groupBy('students.id', 'students.first_name', 'students.father_name', 'sections.name')
            ->orderByDesc('absences')
            ->limit(15)
            ->get()
            ->map(fn ($row): array => [
                'student' => $row->student,
                'section' => $row->section,
                'absences' => (int) $row->absences,
            ]);

        return $this->ok([
            'window' => ['from' => $from, 'to' => $to],
            'overall_rate_percent' => round($present * 100 / $total, 1),
            'counts' => $byStatus,
            'sections_worst_first' => $bySection,
            'most_absent_students' => $chronic,
        ]);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Window start, YYYY-MM-DD (Gregorian). Default: 30 days ago.'),
            'to' => $schema->string()->description('Window end, YYYY-MM-DD. Default: today.'),
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
