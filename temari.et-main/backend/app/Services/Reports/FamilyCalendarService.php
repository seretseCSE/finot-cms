<?php

namespace App\Services\Reports;

use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuizStatus;
use App\Models\Assignment;
use App\Models\Holiday;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubjectAssignment;
use App\Support\Ethiopia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * The family agenda (relationship lane): everything datable in a student's
 * school life for the next window — holidays, term boundaries, planned
 * assessments, exam windows and assignment deadlines — one payload, sorted.
 * Authorization is the CALLER's job (guardian link / own record).
 */
class FamilyCalendarService
{
    private const WINDOW_DAYS = 90;

    /**
     * @return list<array<string, mixed>>
     */
    public function agenda(Student $student): array
    {
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->whereNotNull('section_id')
            ->with('academicYear.terms')
            ->latest('academic_year_id')
            ->first();

        if ($enrollment === null) {
            return [];
        }

        $from = CarbonImmutable::parse(Ethiopia::today());
        $to = $from->addDays(self::WINDOW_DAYS);

        $events = collect()
            ->concat($this->holidays($enrollment, $from, $to))
            ->concat($this->termBoundaries($enrollment, $from, $to))
            ->concat($this->assessments($enrollment, $from, $to))
            ->concat($this->assignmentDeadlines($enrollment, $student, $from, $to))
            ->concat($this->examWindows($enrollment, $from, $to));

        return $events
            ->sortBy([fn ($a, $b) => strcmp($a['date'], $b['date']), fn ($a, $b) => strcmp($a['time'] ?? '', $b['time'] ?? '')])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function holidays(StudentEnrollment $enrollment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Holiday::query()
            ->where('school_id', $enrollment->school_id)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get(['id', 'name', 'date'])
            ->map(fn (Holiday $holiday): array => [
                'type' => 'holiday',
                'date' => Carbon::parse($holiday->date)->toDateString(),
                'time' => null,
                'title' => $holiday->name,
                'subject' => null,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function termBoundaries(StudentEnrollment $enrollment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = [];

        foreach ($enrollment->academicYear?->terms ?? [] as $term) {
            foreach ([['term_start', $term->starts_on], ['term_end', $term->ends_on]] as [$type, $date]) {
                if ($date === null) {
                    continue;
                }
                $day = Carbon::parse($date)->toDateString();
                if ($day >= $from->toDateString() && $day <= $to->toDateString()) {
                    $events[] = [
                        'type' => $type,
                        'date' => $day,
                        'time' => null,
                        'title' => $term->name,
                        'subject' => null,
                    ];
                }
            }
        }

        return $events;
    }

    /**
     * Planned/announced class assessments (tests, quizzes, mid-terms) with a
     * conducted-on date in the window.
     *
     * @return list<array<string, mixed>>
     */
    private function assessments(StudentEnrollment $enrollment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return SubjectAssignment::query()
            ->where('section_id', $enrollment->section_id)
            ->whereHas('assessments', fn ($q) => $q->whereBetween('conducted_on', [$from->toDateString(), $to->toDateString()]))
            ->with([
                'subject:id,name',
                'assessments' => fn ($q) => $q->whereBetween('conducted_on', [$from->toDateString(), $to->toDateString()]),
            ])
            ->get()
            ->flatMap(fn (SubjectAssignment $assignment) => $assignment->assessments->map(fn ($a): array => [
                'type' => 'assessment',
                'date' => $a->conducted_on?->toDateString(),
                'time' => null,
                'title' => $a->name,
                'subject' => $assignment->subject?->name,
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignmentDeadlines(StudentEnrollment $enrollment, Student $student, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $anchorIds = SubjectAssignment::query()
            ->where('section_id', $enrollment->section_id)
            ->pluck('id');

        return Assignment::query()
            ->whereIn('subject_assignment_id', $anchorIds)
            ->where('status', AssignmentStatus::Published->value)
            ->whereBetween('due_at', [$from->startOfDay(), $to->endOfDay()])
            ->visibleToStudent($student->id)
            ->with('subjectAssignment.subject:id,name')
            ->get(['id', 'subject_assignment_id', 'title', 'due_at', 'target_student_ids'])
            ->map(fn (Assignment $assignment): array => [
                'type' => 'assignment_due',
                'date' => $assignment->due_at?->toDateString(),
                'time' => $assignment->due_at?->format('H:i'),
                'title' => $assignment->title,
                'subject' => $assignment->subjectAssignment?->subject?->name,
            ])
            ->all();
    }

    /**
     * Published class exams whose opening window falls inside the agenda —
     * opens_at/closes_at live in the quiz settings JSON, so the (small)
     * per-section set filters in PHP.
     *
     * @return list<array<string, mixed>>
     */
    private function examWindows(StudentEnrollment $enrollment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $anchorIds = SubjectAssignment::query()
            ->where('section_id', $enrollment->section_id)
            ->pluck('id');

        return Quiz::query()
            ->whereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $anchorIds))
            ->where('status', QuizStatus::Published->value)
            ->with('subjectAssignment.subject:id,name')
            ->get()
            ->map(function (Quiz $quiz): ?array {
                $opensAt = $quiz->setting('opens_at');
                if ($opensAt === null) {
                    return null;
                }
                $opens = Carbon::parse($opensAt);

                return [
                    'type' => 'exam',
                    'date' => $opens->toDateString(),
                    'time' => $opens->format('H:i'),
                    'title' => $quiz->title,
                    'subject' => $quiz->subjectAssignment?->subject?->name,
                ];
            })
            ->filter(fn (?array $event) => $event !== null
                && $event['date'] >= $from->toDateString()
                && $event['date'] <= $to->toDateString())
            ->values()
            ->all();
    }

    /**
     * The student's subject teachers for the CURRENT term (falling back to
     * the latest term with assignments) — name + subject only, never
     * personal contact details.
     *
     * @return list<array<string, mixed>>
     */
    public function teachers(Student $student): array
    {
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->whereNotNull('section_id')
            ->latest('academic_year_id')
            ->first();

        if ($enrollment === null) {
            return [];
        }

        $assignments = SubjectAssignment::query()
            ->where('section_id', $enrollment->section_id)
            ->whereHas('term', fn ($q) => $q->where('is_current', true))
            ->with(['subject:id,name,code', 'employee:id,first_name,father_name,photo_path'])
            ->get();

        // No current term (holidays, year boundary)? Show the latest term's roster.
        if ($assignments->isEmpty()) {
            $latestTermId = SubjectAssignment::query()
                ->where('section_id', $enrollment->section_id)
                ->max('term_id');

            if ($latestTermId === null) {
                return [];
            }

            $assignments = SubjectAssignment::query()
                ->where('section_id', $enrollment->section_id)
                ->where('term_id', $latestTermId)
                ->with(['subject:id,name,code', 'employee:id,first_name,father_name,photo_path'])
                ->get();
        }

        return $assignments
            ->sortBy(fn (SubjectAssignment $a) => $a->subject?->name)
            ->map(fn (SubjectAssignment $assignment): array => [
                'subject' => $assignment->subject?->name,
                'subject_code' => $assignment->subject?->code,
                'teacher' => $assignment->employee !== null
                    ? trim($assignment->employee->first_name.' '.$assignment->employee->father_name)
                    : null,
                'photo_url' => $assignment->employee?->photo_url,
            ])
            ->values()
            ->all();
    }
}
