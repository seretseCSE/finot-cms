<?php

namespace App\Services\Reports;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\TimetableVersionStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\TimetableVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Term-scoped student reports, shared by the staff lane (ReportController) and
 * the relationship lane (/me endpoints) so both always agree. Authorization is
 * the CALLER's job — this service only computes.
 */
class StudentReportService
{
    /**
     * All subject scores for a student in a term, with weighted totals.
     *
     * @return array<string, mixed>
     */
    public function resultCard(Student $student, int $termId): array
    {
        $enrollment = $this->enrollmentForTerm($student, $termId);

        $assignments = SubjectAssignment::query()
            ->where('section_id', $enrollment->section_id)
            ->where('term_id', $termId)
            ->with([
                'subject',
                'employee:id,first_name,father_name',
                'marklist:id,subject_assignment_id,status',
                'assessments.results' => fn ($q) => $q->where('student_id', $student->id),
            ])
            ->get();

        $subjects = $assignments->map(function (SubjectAssignment $assignment): array {
            $assessments = $assignment->assessments->map(function ($a) {
                $result = $a->results->first();

                return [
                    'id' => $a->id,
                    'type' => $a->type,
                    'name' => $a->name,
                    'max_score' => (float) $a->max_score,
                    'weight' => (float) $a->weight,
                    'conducted_on' => $a->conducted_on?->toDateString(),
                    'score' => $result ? (float) $result->score : null,
                    'is_absent' => $result?->is_absent ?? false,
                ];
            });

            // Weighted total out of 100
            $weightedTotal = $assessments
                ->filter(fn ($a) => ! $a['is_absent'] && $a['score'] !== null && $a['max_score'] > 0)
                ->sum(fn ($a) => ($a['score'] / $a['max_score']) * $a['weight']);

            // How much of the term's weight has actually been marked — the
            // /me marks view shows "42 of 60% assessed" style progress.
            $assessedWeight = $assessments
                ->filter(fn ($a) => $a['is_absent'] || $a['score'] !== null)
                ->sum(fn ($a) => $a['weight']);

            return [
                'subject_assignment_id' => $assignment->id,
                'subject' => [
                    'id' => $assignment->subject->id,
                    'code' => $assignment->subject->code,
                    'name' => $assignment->subject->name,
                ],
                'teacher' => $assignment->employee !== null
                    ? trim($assignment->employee->first_name.' '.$assignment->employee->father_name)
                    : null,
                // Sign-off state: submitted/approved marks are final; draft
                // rows render as provisional in the family lane.
                'marklist_status' => $assignment->marklist?->status->value ?? 'draft',
                'assessments' => $assessments->values(),
                'assessed_weight' => round($assessedWeight, 2),
                'weighted_total' => round($weightedTotal, 2),
            ];
        });

        // The frozen summary (average + section rank), when computed.
        $summary = StudentTermResult::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('term_id', $termId)
            ->first();

        return [
            'student_id' => $student->id,
            'term_id' => $termId,
            'section_id' => $enrollment->section_id,
            'subjects' => $subjects->values(),
            'summary' => $summary === null ? null : [
                'total' => $summary->total !== null ? (float) $summary->total : null,
                'average' => $summary->average !== null ? (float) $summary->average : null,
                'rank' => $summary->rank,
                'rank_of' => $summary->rank_of,
                'computed_at' => $summary->computed_at?->toISOString(),
            ],
        ];
    }

    /**
     * The OFFICIAL report card: the frozen student_term_results row rendered
     * with its snapshotted grading (letters resolved at freeze time), conduct,
     * absences and homeroom comment. Null when the term was never computed —
     * callers show "results not published yet".
     *
     * @return array<string, mixed>|null
     */
    public function reportCard(Student $student, int $termId): ?array
    {
        $enrollment = $this->enrollmentForTerm($student, $termId);

        $result = StudentTermResult::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('term_id', $termId)
            ->with([
                'term:id,name,academic_year_id',
                'section:id,name',
                'academicYear:id,name',
                'gradeLevel:id,name',
                'branch:id,name,school_id',
                'branch.school:id,name,logo_path',
            ])
            ->first();

        if ($result === null) {
            return null;
        }

        return [
            'student' => [
                'id' => $student->id,
                'public_id' => $student->public_id,
                'full_name' => $student->full_name,
                'gender' => $student->gender,
                'photo_url' => $student->photo_url,
            ],
            'school_name' => $result->branch?->school?->name,
            'school_logo_url' => $result->branch?->school?->logoUrl(),
            'branch_name' => $result->branch?->name,
            'academic_year' => $result->academicYear?->name,
            'term_id' => $result->term_id,
            'term_name' => $result->term?->name,
            'grade_level' => $result->gradeLevel?->name,
            'section_name' => $result->section?->name,
            'subjects' => $result->breakdown,
            'total' => $result->total !== null ? (float) $result->total : null,
            'average' => $result->average !== null ? (float) $result->average : null,
            'rank' => $result->rank,
            'rank_of' => $result->rank_of,
            'subject_count' => $result->subject_count,
            'grading' => $result->grading,
            'conduct' => $result->conduct,
            'absence_days' => $result->absence_days,
            'comment' => $result->comment,
            'computed_at' => $result->computed_at?->toISOString(),
        ];
    }

    /**
     * Report cards for MANY students of one term in one query — powers the
     * batch PDF without an N+1 fan-out. Same shape as reportCard(); order
     * follows the given ids; students with no frozen row are skipped (the
     * document layer reports them).
     *
     * @param  list<int>  $studentIds
     * @return list<array<string, mixed>>
     */
    public function reportCards(array $studentIds, int $termId): array
    {
        $results = StudentTermResult::query()
            ->whereIn('student_id', $studentIds)
            ->where('term_id', $termId)
            ->with([
                'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
                'term:id,name,academic_year_id',
                'section:id,name',
                'academicYear:id,name',
                'gradeLevel:id,name',
                'branch:id,name,school_id',
                'branch.school:id,name,logo_path',
            ])
            ->get()
            ->keyBy('student_id');

        return collect($studentIds)
            ->map(fn (int $id) => $results->get($id))
            ->filter()
            ->map(fn (StudentTermResult $result): array => [
                'student' => [
                    'id' => $result->student?->id,
                    'public_id' => $result->student?->public_id,
                    'full_name' => $result->student?->full_name,
                    'gender' => $result->student?->gender,
                    'photo_url' => null,
                ],
                'school_name' => $result->branch?->school?->name,
                'school_logo_url' => null,
                'branch_name' => $result->branch?->name,
                'academic_year' => $result->academicYear?->name,
                'term_id' => $result->term_id,
                'term_name' => $result->term?->name,
                'grade_level' => $result->gradeLevel?->name,
                'section_name' => $result->section?->name,
                'subjects' => $result->breakdown,
                'total' => $result->total !== null ? (float) $result->total : null,
                'average' => $result->average !== null ? (float) $result->average : null,
                'rank' => $result->rank,
                'rank_of' => $result->rank_of,
                'subject_count' => $result->subject_count,
                'grading' => $result->grading,
                'conduct' => $result->conduct,
                'absence_days' => $result->absence_days,
                'comment' => $result->comment,
                'computed_at' => $result->computed_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * The student's frozen report-card INDEX — one summary line per computed
     * term, newest first. Lets the /me surfaces list "Semester 1 · avg 84 ·
     * rank 3" without the caller knowing any term ids.
     *
     * @return list<array<string, mixed>>
     */
    public function reportCardIndex(Student $student): array
    {
        return StudentTermResult::query()
            ->where('student_id', $student->id)
            ->with([
                'term:id,name,sequence,academic_year_id',
                'academicYear:id,name,starts_on',
                'gradeLevel:id,name',
                'section:id,name',
            ])
            ->get()
            ->sortByDesc([
                fn ($r) => $r->academicYear?->starts_on?->timestamp ?? 0,
                fn ($r) => $r->term?->sequence ?? 0,
            ])
            ->map(fn (StudentTermResult $r): array => [
                'term_id' => $r->term_id,
                'term_name' => $r->term?->name,
                'academic_year' => $r->academicYear?->name,
                'grade_level' => $r->gradeLevel?->name,
                'section_name' => $r->section?->name,
                'average' => $r->average !== null ? (float) $r->average : null,
                'rank' => $r->rank,
                'rank_of' => $r->rank_of,
                'letter' => $r->grading['overall']['letter'] ?? null,
                'is_passing' => $r->grading['overall']['is_passing'] ?? null,
                'computed_at' => $r->computed_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * The multi-year transcript: every frozen term row the student has, in
     * chronological order, grouped per academic year with the annual average
     * (mean of the year's term averages). Reads ONLY student_term_results —
     * never raw marks — so it always matches the report cards that were
     * actually issued.
     *
     * @param  list<int>|null  $academicYearIds  Limit to these years (a PARTIAL
     *                                           transcript — stamped as such); null = the full record.
     * @return array<string, mixed>
     */
    public function transcript(Student $student, ?array $academicYearIds = null): array
    {
        return $this->buildTranscript(
            $student,
            $this->transcriptResults()->where('student_id', $student->id)->get(),
            $academicYearIds,
        );
    }

    /**
     * Transcripts for MANY students in one results query — powers the bulk
     * print page and the Excel export without an N+1 fan-out. Order follows
     * the given collection; students with nothing frozen get empty years.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Student>  $students
     * @param  list<int>|null  $academicYearIds
     * @return list<array<string, mixed>>
     */
    public function transcripts($students, ?array $academicYearIds = null): array
    {
        $resultsByStudent = $this->transcriptResults()
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id');

        return $students
            ->map(fn (Student $student): array => $this->buildTranscript(
                $student,
                $resultsByStudent->get($student->id, new Collection()),
                $academicYearIds,
            ))
            ->values()
            ->all();
    }

    /**
     * The frozen-rows query behind every transcript (no student filter).
     *
     * @return Builder<StudentTermResult>
     */
    private function transcriptResults()
    {
        return StudentTermResult::query()
            ->with([
                'term:id,name,sequence,academic_year_id',
                'section:id,name',
                'academicYear:id,name,starts_on',
                'gradeLevel:id,name,sort_order',
                'branch:id,name,school_id,city,sub_city,state,phone',
                'branch.school:id,name,logo_path,phone,address',
                'enrollment:id,status',
            ]);
    }

    /**
     * @param  Collection<int, StudentTermResult>  $results
     * @param  list<int>|null  $academicYearIds
     * @return array<string, mixed>
     */
    private function buildTranscript(Student $student, $results, ?array $academicYearIds = null): array
    {
        $results = $results
            ->sortBy([
                fn ($r) => $r->academicYear?->starts_on?->timestamp ?? 0,
                fn ($r) => $r->term?->sequence ?? 0,
            ])
            ->values();

        // The FULL frozen history drives the year picker and the partial
        // stamp, whatever subset this print covers.
        $availableYears = $results
            ->groupBy('academic_year_id')
            ->map(fn ($rows) => [
                'academic_year_id' => $rows->first()->academic_year_id,
                'academic_year' => $rows->first()->academicYear?->name,
                'grade_level' => $rows->first()->gradeLevel?->name,
                'terms_count' => $rows->count(),
            ])
            ->values();

        // The issuing school = the CURRENT custodian of the record — the
        // school behind the newest frozen row of the FULL history (documents
        // travel forward with the student, ADR-017), whatever subset prints.
        $latest = $results->last();
        $issuingBranch = $latest?->branch;
        $issuingSchool = $issuingBranch?->school;

        // Masthead contact chain: the branch's own line wins, then the
        // school's, then (phone only) the principal's — queried lazily since
        // most schools will have filled their own numbers in.
        $address = collect([$issuingBranch?->sub_city, $issuingBranch?->city, $issuingBranch?->state])
            ->filter()
            ->implode(', ') ?: $issuingSchool?->address;
        $phone = $issuingBranch?->phone
            ?? $issuingSchool?->phone
            ?? $issuingSchool?->contactMemberships()
                ->where('role', Role::Principal->value)
                ->with('user:id,phone')
                ->first()?->user?->phone;

        if ($academicYearIds !== null) {
            $results = $results
                ->filter(fn ($r) => in_array((int) $r->academic_year_id, $academicYearIds, true))
                ->values();
        }

        // Year-end outcomes ("Promoted to Grade 10") keyed by the source
        // enrollment — one query for every year on the sheet.
        $promotions = StudentPromotion::query()
            ->whereIn('from_enrollment_id', $results->pluck('student_enrollment_id')->unique()->filter())
            ->whereNotNull('decided_at')
            ->with('toGradeLevel:id,name')
            ->get()
            ->keyBy('from_enrollment_id');

        $years = $results
            ->groupBy('academic_year_id')
            ->map(function ($rows) use ($promotions) {
                $averages = $rows->pluck('average')->filter()->map(fn ($v) => (float) $v);
                $first = $rows->first();
                $promotion = $rows
                    ->pluck('student_enrollment_id')
                    ->map(fn ($id) => $promotions->get($id))
                    ->filter()
                    ->first();

                // No board decision recorded? Fall back to the enrollment's
                // own terminal status (stamped when the rollover executed) —
                // live years honestly have no outcome yet.
                $outcome = null;
                if ($promotion !== null) {
                    $outcome = [
                        'decision' => $promotion->decision->value,
                        'label' => $promotion->decision->label(),
                        'to_grade_level' => $promotion->toGradeLevel?->name,
                    ];
                } elseif ($fallback = $this->enrollmentOutcome($rows->last()?->enrollment?->status)) {
                    $outcome = $fallback;
                }

                return [
                    'academic_year_id' => $first->academic_year_id,
                    'academic_year' => $first->academicYear?->name,
                    'grade_level' => $first->gradeLevel?->name,
                    'school_name' => $first->branch?->school?->name,
                    'branch_name' => $first->branch?->name,
                    'terms' => $rows->map(fn (StudentTermResult $r): array => [
                        'term_id' => $r->term_id,
                        'term_name' => $r->term?->name,
                        'section_name' => $r->section?->name,
                        'total' => $r->total !== null ? (float) $r->total : null,
                        'average' => $r->average !== null ? (float) $r->average : null,
                        'rank' => $r->rank,
                        'rank_of' => $r->rank_of,
                        'conduct' => $r->conduct,
                        'absence_days' => $r->absence_days,
                        'subjects' => $r->breakdown,
                        'grading' => $r->grading,
                    ])->values(),
                    'annual_average' => $averages->isEmpty() ? null : round((float) $averages->avg(), 2),
                    // Year-end outcome line ("Promoted to Grade 10") — what a
                    // receiving school actually looks for.
                    'outcome' => $outcome,
                ];
            })
            ->values();

        return [
            'student' => [
                'id' => $student->id,
                'public_id' => $student->public_id,
                'full_name' => $student->full_name,
                'gender' => $student->gender?->value,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'photo_url' => $student->photo_url,
            ],
            // Masthead: whoever currently holds the record issues the paper.
            'issued_by' => $issuingSchool === null ? null : [
                'school_name' => $issuingSchool->name,
                'logo_url' => $issuingSchool->logoUrl(),
                'branch_name' => $issuingBranch?->name,
                'address' => $address ?: null,
                'phone' => $phone ?: null,
            ],
            'years' => $years,
            'available_years' => $availableYears,
            // A subset print must SAY it is one — a partial transcript can
            // never masquerade as the complete record.
            'is_partial' => $years->count() < $availableYears->count(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Transcript Status fallback when no promotion-board row exists: the
     * enrollment's own terminal status. Live enrollments yield null.
     *
     * @return array{decision: string, label: string, to_grade_level: null}|null
     */
    private function enrollmentOutcome(?EnrollmentStatus $status): ?array
    {
        $label = match ($status) {
            EnrollmentStatus::Promoted => 'Promoted',
            EnrollmentStatus::Repeated => 'Repeated',
            EnrollmentStatus::Graduated => 'Graduated',
            EnrollmentStatus::Withdrawn => 'Withdrawn',
            EnrollmentStatus::TransferredOut => 'Transferred',
            default => null,
        };

        return $label === null ? null : [
            'decision' => $status->value,
            'label' => $label,
            'to_grade_level' => null,
        ];
    }

    /**
     * The transcript register: every ACTIVE enrollment of one year under the
     * optional grade/section narrowing, with a readiness aggregate over the
     * student's ENTIRE frozen history (all years — a transcript is multi-year)
     * so staff can see "3 years · 6 semesters" before printing.
     *
     * @param  list<int>|null  $allowedSectionIds  null = unrestricted (supervisory)
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function transcriptRegister(AcademicYear $year, ?int $sectionId, ?int $gradeLevelId, ?array $allowedSectionIds): array
    {
        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($allowedSectionIds !== null, fn ($q) => $q->whereIn('section_id', $allowedSectionIds))
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->when($sectionId === null && $gradeLevelId !== null, fn ($q) => $q->where('grade_level_id', $gradeLevelId))
            ->with([
                'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
            ])
            ->limit(2000)
            ->get();

        $readiness = StudentTermResult::query()
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->selectRaw('student_id, COUNT(DISTINCT academic_year_id) AS years_count, COUNT(*) AS terms_count, MAX(computed_at) AS last_computed_at')
            ->groupBy('student_id')
            ->toBase()
            ->get()
            ->keyBy('student_id');

        $rows = $enrollments
            ->sortBy([
                fn ($a, $b) => ($a->section?->gradeLevel?->sort_order ?? 0) <=> ($b->section?->gradeLevel?->sort_order ?? 0),
                fn ($a, $b) => strcmp((string) $a->section?->name, (string) $b->section?->name),
                fn ($a, $b) => strcmp((string) $a->student?->full_name, (string) $b->student?->full_name),
            ])
            ->map(function (StudentEnrollment $enrollment) use ($readiness): array {
                $ready = $readiness->get($enrollment->student_id);

                return [
                    'student_id' => $enrollment->student_id,
                    'public_id' => $enrollment->student?->public_id,
                    'full_name' => $enrollment->student?->full_name,
                    'gender' => $enrollment->student?->gender?->value,
                    'photo_url' => $enrollment->student?->photo_url,
                    'section_id' => $enrollment->section_id,
                    'section_name' => $enrollment->section?->name,
                    'grade_level_name' => $enrollment->section?->gradeLevel?->name,
                    'years_count' => (int) ($ready->years_count ?? 0),
                    'terms_count' => (int) ($ready->terms_count ?? 0),
                    'last_computed_at' => $ready?->last_computed_at,
                ];
            })
            ->values();

        return [
            'data' => $rows->all(),
            'meta' => [
                'year' => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status->value],
                'students' => $rows->count(),
            ],
        ];
    }

    /**
     * Attendance counts + rate for a student in a term.
     *
     * @return array<string, mixed>
     */
    public function attendanceSummary(Student $student, int $termId): array
    {
        $enrollment = $this->enrollmentForTerm($student, $termId);

        $records = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->where('section_id', $enrollment->section_id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = $records->sum();

        return [
            'student_id' => $student->id,
            'term_id' => $termId,
            'total_days' => $total,
            'present' => (int) ($records['present'] ?? 0),
            'absent' => (int) ($records['absent'] ?? 0),
            'late' => (int) ($records['late'] ?? 0),
            'excused' => (int) ($records['excused'] ?? 0),
            'attendance_rate' => $total > 0
                ? round((($records['present'] ?? 0) / $total) * 100, 1)
                : null,
        ];
    }

    /**
     * The student's published weekly timetable: live enrollment → section →
     * the current (or latest active) term of that year/program → its
     * published version's slots. Null when any link is missing — the /me
     * surfaces render a friendly empty state.
     *
     * @return array<string, mixed>|null
     */
    public function timetable(Student $student): ?array
    {
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['pending', 'active'])
            ->whereNotNull('section_id')
            ->with('section:id,name')
            ->latest('academic_year_id')
            ->first();

        if ($enrollment === null) {
            return null;
        }

        $term = Term::query()
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->when($enrollment->school_program_id !== null, fn ($q) => $q->where(
                fn ($inner) => $inner->where('school_program_id', $enrollment->school_program_id)->orWhereNull('school_program_id'),
            ))
            ->orderByDesc('is_current')
            ->orderByDesc('sequence')
            ->first();

        if ($term === null) {
            return null;
        }

        $version = TimetableVersion::query()
            ->where('term_id', $term->id)
            ->where('status', TimetableVersionStatus::Published->value)
            ->first();

        if ($version === null) {
            return null;
        }

        $slots = $version->slots()
            ->whereHas('subjectAssignment', fn ($q) => $q->where('section_id', $enrollment->section_id))
            ->with([
                'subjectAssignment.subject:id,code,name',
                'subjectAssignment.employee:id,first_name,father_name',
                'room:id,name',
            ])
            ->get();

        return [
            'term_id' => $term->id,
            'term_name' => $term->name,
            'section' => $enrollment->section?->name,
            'days' => $version->days,
            'periods' => $term->periods()->get()->map(fn ($p) => [
                'sequence' => $p->sequence,
                'type' => $p->type,
                'period_number' => $p->period_number,
                'label' => $p->label,
                'starts_at' => substr((string) $p->starts_at, 0, 5),
                'ends_at' => substr((string) $p->ends_at, 0, 5),
            ])->values(),
            'slots' => $slots->map(fn ($s) => [
                'day_of_week' => $s->day_of_week,
                'period_number' => $s->period_number,
                'subject' => $s->subjectAssignment->subject?->name,
                'teacher' => $s->subjectAssignment->employee !== null
                    ? trim($s->subjectAssignment->employee->first_name.' '.$s->subjectAssignment->employee->father_name)
                    : null,
                'room' => $s->room?->name,
            ])->values(),
        ];
    }

    private function enrollmentForTerm(Student $student, int $termId): StudentEnrollment
    {
        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->whereHas('academicYear.terms', fn ($q) => $q->where('id', $termId))
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages(['term_id' => ['Student has no enrollment in this term.']]);
        }

        return $enrollment;
    }
}
