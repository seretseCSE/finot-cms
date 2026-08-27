<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Jobs\SendAttendanceNotifications;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Term;
use App\Support\Ethiopia;
use App\Support\TermGate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk upserts a section's attendance for one date. Idempotent per
 * (student, section, date) so re-saving simply corrects the marks. Only students
 * actively enrolled in the section are recorded.
 */
class SaveAttendanceAction
{
    /**
     * @param  array{date: string, records: array<int, array{student_id: int, status: string, check_in?: ?string, check_out?: ?string, note?: ?string}>}  $data
     * @return Collection<int, AttendanceRecord>
     */
    public function execute(Section $section, array $data, ?int $recordedBy): Collection
    {
        // Anchor the record to the term the date falls in (term_id is the
        // universal time anchor) and refuse writes into a closed term.
        $term = Term::query()
            ->where('branch_id', $section->branch_id)
            ->whereDate('starts_on', '<=', $data['date'])
            ->whereDate('ends_on', '>=', $data['date'])
            ->orderByDesc('is_current')
            ->first()
            ?? Term::query()->where('branch_id', $section->branch_id)->where('is_current', true)->first();

        TermGate::assertWritable($term);

        $academicYearId = $section->enrollments()
            ->where('status', EnrollmentStatus::Active->value)
            ->value('academic_year_id');

        $enrolledIds = $section->enrollments()
            ->where('status', EnrollmentStatus::Active->value)
            ->pluck('student_id')
            ->all();

        $saved = DB::transaction(function () use ($section, $data, $recordedBy, $academicYearId, $enrolledIds, $term): Collection {
            $existing = AttendanceRecord::where('section_id', $section->id)
                ->where('date', $data['date'])
                ->get()
                ->keyBy('student_id');

            foreach ($data['records'] as $record) {
                if (! in_array($record['student_id'], $enrolledIds, true)) {
                    continue;
                }

                $values = [
                    'status' => $record['status'],
                    'check_in' => $record['check_in'] ?? null,
                    'check_out' => $record['check_out'] ?? null,
                    'note' => $record['note'] ?? null,
                ];

                $current = $existing->get($record['student_id']);

                if ($current !== null) {
                    // Re-saving an untouched row must not flip a device mark to
                    // "manual" — only an actual human edit claims the record.
                    $current->fill($values);

                    if ($current->isDirty()) {
                        $current->fill(['source' => 'manual', 'recorded_by' => $recordedBy])->save();
                    }

                    continue;
                }

                AttendanceRecord::create($values + [
                    'student_id' => $record['student_id'],
                    'section_id' => $section->id,
                    'date' => $data['date'],
                    'school_id' => $section->school_id,
                    'branch_id' => $section->branch_id,
                    'academic_year_id' => $academicYearId,
                    'term_id' => $term?->id,
                    'source' => 'manual',
                    'recorded_by' => $recordedBy,
                ]);
            }

            return AttendanceRecord::where('section_id', $section->id)
                ->where('date', $data['date'])
                ->get();
        });

        // Absence/late alerts to guardians — the job re-checks branch policy,
        // is same-day-only and dedupes, so firing broadly here is safe.
        $notifiable = $saved
            ->filter(fn (AttendanceRecord $r) => in_array($r->status->value, SendAttendanceNotifications::NOTIFIABLE_STATUSES, true))
            ->pluck('id')
            ->all();

        if ($notifiable !== [] && $data['date'] === Ethiopia::today()) {
            SendAttendanceNotifications::dispatch($notifiable);
        }

        return $saved;
    }
}
