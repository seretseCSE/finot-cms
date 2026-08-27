<?php

namespace App\Ai\Tools\Teacher;

use App\Models\AssessmentResult;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Support\SearchTerm;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One student in the teacher's OWN classes (homeroom or teaching section):
 * running marks in the teacher's subjects, frozen term history, and the
 * attendance picture — enough to ground an intervention suggestion or a
 * report-card comment. Students outside the teacher's sections are refused.
 */
class ClassStudentTool extends TeacherScopedTool
{
    public function description(): Stringable|string
    {
        return 'Look at ONE student in the teacher\'s own classes (search by name or pass student_id): their marks in your subjects, past term averages/ranks, and attendance. Use for intervention advice or drafting a report-card comment.';
    }

    public function handle(Request $request): Stringable|string
    {
        $ownedSectionIds = $this->context->branchId() !== null
            ? $this->context->user->ownedSectionIds($this->context->branchId())
            : [];

        if ($ownedSectionIds === []) {
            return $this->deny('No owned sections in this branch context.');
        }

        $query = Student::query()
            ->whereHas('enrollments', fn ($q) => $q
                ->whereIn('section_id', $ownedSectionIds)
                ->whereIn('status', ['pending', 'active']));

        if (($id = $request->integer('student_id')) > 0) {
            $query->where('id', $id);
        } elseif (($name = trim($request->string('name')->toString())) !== '') {
            SearchTerm::apply($query, $name, fn ($w, string $n) => $w
                ->where('search_text', 'ilike', SearchTerm::contains($n)));
        } else {
            return $this->deny('Pass a student name or student_id.');
        }

        $students = $query->limit(6)->get();

        if ($students->isEmpty()) {
            return $this->deny('No matching student in your sections.');
        }

        if ($students->count() > 1) {
            return $this->ok([
                'disambiguate' => $students->map(fn (Student $s): array => [
                    'student_id' => $s->id,
                    'name' => $s->full_name,
                ]),
            ]);
        }

        $student = $students->first();

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('section_id', $ownedSectionIds)
            ->whereIn('status', ['pending', 'active'])
            ->latest('academic_year_id')
            ->first();

        $marks = AssessmentResult::query()
            ->where('student_id', $student->id)
            ->whereHas('assessment', fn ($q) => $q->whereIn(
                'subject_assignment_id',
                $this->ownAssignments()->pluck('id'),
            ))
            ->with(['assessment:id,subject_assignment_id,name,max_score,weight', 'assessment.subjectAssignment.subject:id,name'])
            ->limit(60)
            ->get()
            ->map(fn (AssessmentResult $r): array => [
                'subject' => $r->assessment?->subjectAssignment?->subject?->name,
                'assessment' => $r->assessment?->name,
                'score' => $r->is_absent ? null : ($r->score !== null ? (float) $r->score : null),
                'max' => $r->assessment?->max_score !== null ? (float) $r->assessment->max_score : null,
                'absent' => (bool) $r->is_absent,
            ]);

        $history = StudentTermResult::query()
            ->where('student_id', $student->id)
            ->with('term:id,name')
            ->orderByDesc('term_id')
            ->limit(4)
            ->get()
            ->map(fn (StudentTermResult $r): array => [
                'term' => $r->term?->name,
                'average' => $r->average !== null ? (float) $r->average : null,
                'rank' => $r->rank,
                'rank_of' => $r->rank_of,
            ]);

        $attendance = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->when($enrollment !== null, fn ($q) => $q->where('section_id', $enrollment->section_id))
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return $this->ok([
            'student' => ['id' => $student->id, 'name' => $student->full_name, 'link' => '/students/'.$student->id],
            'marks_in_my_subjects' => $marks,
            'term_history' => $history,
            'attendance_counts' => $attendance,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Student name (or part of it) to search in your sections.'),
            'student_id' => $schema->integer()->description('Exact student id (from a previous search).'),
        ];
    }
}
