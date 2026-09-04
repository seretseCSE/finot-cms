<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\TermStatus;
use App\Jobs\SendAttendanceNotifications;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Holiday;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Support\Ethiopia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The device-mode absent sweep: after a branch's cutoff time, every enrolled
 * student with no mark for today (no scan, no manual entry) is marked absent
 * — which is what triggers the guardian SMS. Runs every few minutes from the
 * scheduler; it only ever FILLS MISSING rows, so re-runs are no-ops and it
 * can never clobber a scan or a manual register. Gated per branch:
 * device_auto_absent must be on (override → school default), today must be a
 * school day (Mon–Fri, not a holiday), the cutoff must have passed, and the
 * term must be writable.
 */
class AutoMarkAbsentees extends Command
{
    protected $signature = 'attendance:auto-absent {--branch= : Only this branch id}';

    protected $description = 'Mark unscanned students absent after each branch\'s cutoff (device attendance mode)';

    public function handle(): int
    {
        $now = Ethiopia::now();
        $today = $now->toDateString();

        // Ethiopian school week is Monday–Friday.
        if ($now->isWeekend()) {
            return self::SUCCESS;
        }

        $branches = Branch::query()
            ->where('is_active', true)
            ->when($this->option('branch'), fn ($q, $id) => $q->whereKey($id))
            ->whereHas('school', fn ($q) => $q->where('is_active', true))
            ->with('school')
            ->get();

        $swept = 0;

        foreach ($branches as $branch) {
            if (! $branch->effectiveDeviceAutoAbsent()) {
                continue;
            }

            if ($now->format('H:i') < $branch->effectiveDeviceAbsentCutoff()) {
                continue;
            }

            if ($this->isHoliday($branch, $today)) {
                continue;
            }

            $term = Term::query()
                ->where('branch_id', $branch->id)
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today)
                ->orderByDesc('is_current')
                ->first();

            if ($term === null || $term->status === TermStatus::Closed) {
                continue;
            }

            $swept += $this->sweepBranch($branch, $term->id, $today);
        }

        if ($swept > 0) {
            $this->info("Marked {$swept} students absent.");
        }

        return self::SUCCESS;
    }

    private function isHoliday(Branch $branch, string $today): bool
    {
        return Holiday::query()
            ->where('school_id', $branch->school_id)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->whereDate('date', $today)
            ->exists();
    }

    /** Insert absent rows for every active, sectioned, unmarked enrollment. */
    private function sweepBranch(Branch $branch, int $termId, string $today): int
    {
        $missing = StudentEnrollment::query()
            ->where('branch_id', $branch->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereNotNull('section_id')
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->whereNotExists(function ($q) use ($today): void {
                $q->select(DB::raw(1))
                    ->from('attendance_records')
                    ->whereColumn('attendance_records.student_id', 'student_enrollments.student_id')
                    ->whereColumn('attendance_records.section_id', 'student_enrollments.section_id')
                    ->where('attendance_records.date', $today)
                    ->whereNull('attendance_records.deleted_at');
            })
            ->get(['student_id', 'section_id', 'school_id', 'branch_id', 'academic_year_id']);

        if ($missing->isEmpty()) {
            return 0;
        }

        $created = [];

        foreach ($missing->chunk(500) as $chunk) {
            foreach ($chunk as $enrollment) {
                $created[] = AttendanceRecord::create([
                    'school_id' => $enrollment->school_id,
                    'branch_id' => $enrollment->branch_id,
                    'section_id' => $enrollment->section_id,
                    'student_id' => $enrollment->student_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'term_id' => $termId,
                    'date' => $today,
                    'status' => 'absent',
                    'source' => 'device',
                ])->id;
            }
        }

        SendAttendanceNotifications::dispatch($created);

        return count($created);
    }
}
