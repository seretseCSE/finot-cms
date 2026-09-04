<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CopyTermAssignmentsAction;
use App\Actions\GenerateTermAssignmentsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectAssignmentResource;
use App\Models\Employee;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\TeacherSubject;
use App\Models\Term;
use App\Support\JobTitles;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The semester teaching grid (section → subject → teacher). `matrix` returns
 * every assignment of the term plus the pickers' data (sections, applicable
 * subjects, teachers with their declared capabilities) in ONE response so the
 * page renders without request fan-out. Generation is opt-in and idempotent;
 * copying pulls the grid from a sibling semester.
 */
class TermAssignmentController extends Controller
{
    public function matrix(Term $term): JsonResponse
    {
        $this->authorize('view', $term);

        $assignments = SubjectAssignment::query()
            ->where('term_id', $term->id)
            ->with([
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
                'subject:id,code,name,category',
                'employee:id,first_name,father_name',
            ])
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->section->gradeLevel->sort_order <=> $b->section->gradeLevel->sort_order,
                fn ($a, $b) => strcmp($a->section->name, $b->section->name),
                fn ($a, $b) => strcmp((string) $a->subject?->name, (string) $b->subject?->name),
            ])
            ->values();

        // Active teachers of the branch + their capability rows, for the
        // per-row teacher dropdowns (capable teachers rank first client-side).
        $teachers = Employee::query()
            ->where('branch_id', $term->branch_id)
            ->where('is_active', true)
            ->whereHas('positions', fn ($p) => $p
                ->where('job_title', JobTitles::TEACHER)
                ->whereNull('ended_on'))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'father_name']);

        $capabilities = TeacherSubject::query()
            ->whereIn('employee_id', $teachers->pluck('id'))
            ->get(['employee_id', 'subject_id', 'grade_level_id']);

        $sections = Section::query()
            ->where('branch_id', $term->branch_id)
            ->where('is_active', true)
            ->with('gradeLevel:id,name,sort_order')
            ->get(['id', 'name', 'grade_level_id']);

        $subjects = Subject::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $term->school_id))
            ->orderBy('name')
            ->with('gradeLevels:grade_levels.id,sort_order')
            ->get(['id', 'code', 'name', 'category']);

        return response()->json([
            'data' => SubjectAssignmentResource::collection($assignments),
            'meta' => [
                'teachers' => $teachers->map(fn (Employee $t) => [
                    'id' => $t->id,
                    'name' => trim("{$t->first_name} {$t->father_name}"),
                ])->values(),
                'capabilities' => $capabilities->map(fn (TeacherSubject $c) => [
                    'employee_id' => $c->employee_id,
                    'subject_id' => $c->subject_id,
                    'grade_level_id' => $c->grade_level_id,
                ])->values(),
                'sections' => $sections->map(fn (Section $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'grade_level_id' => $s->grade_level_id,
                    'grade_level_name' => $s->gradeLevel?->name,
                    'grade_level_sort' => $s->gradeLevel?->sort_order,
                ])->values(),
                'subjects' => $subjects->map(fn (Subject $s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'name' => $s->name,
                    'category' => $s->category,
                    // Empty = open (taught in every grade).
                    'grade_sorts' => $s->gradeLevels->pluck('sort_order')->sort()->values(),
                ])->values(),
            ],
        ]);
    }

    public function generate(Request $request, Term $term, GenerateTermAssignmentsAction $action): JsonResponse
    {
        $this->authorizeManage($request, $term);
        TermGate::assertWritable($term);

        $created = $action->execute($term);

        return response()->json([
            'data' => ['created' => $created],
            'message' => $created > 0
                ? "{$created} assignments generated. Review the teachers and adjust where needed."
                : 'Nothing to generate — every section/subject slot already exists.',
        ]);
    }

    /**
     * One-click burn-down: fills every UNASSIGNED row whose (subject × grade)
     * has exactly one capable active teacher. Ambiguous rows stay untouched —
     * they need a human. Safe to run repeatedly.
     */
    public function autofill(Request $request, Term $term): JsonResponse
    {
        $this->authorizeManage($request, $term);
        TermGate::assertWritable($term);

        $rows = SubjectAssignment::query()
            ->where('term_id', $term->id)
            ->whereNull('employee_id')
            ->with('section:id,grade_level_id')
            ->get();

        $candidates = TeacherSubject::query()
            ->whereHas('employee', fn ($q) => $q
                ->where('branch_id', $term->branch_id)
                ->where('is_active', true)
                ->whereHas('positions', fn ($p) => $p
                    ->where('job_title', JobTitles::TEACHER)
                    ->whereNull('ended_on')))
            ->get(['employee_id', 'subject_id', 'grade_level_id'])
            ->groupBy(fn (TeacherSubject $ts) => "{$ts->subject_id}:{$ts->grade_level_id}");

        $filled = 0;
        foreach ($rows as $row) {
            $capable = $candidates->get("{$row->subject_id}:{$row->section->grade_level_id}");
            if ($capable !== null && $capable->count() === 1) {
                $row->update(['employee_id' => $capable->first()->employee_id]);
                $filled++;
            }
        }

        return response()->json([
            'data' => ['filled' => $filled],
            'message' => $filled > 0
                ? "{$filled} slots auto-filled from teacher capabilities."
                : 'No unambiguous slots to fill — the remaining ones need a decision.',
        ]);
    }

    public function copy(Request $request, Term $term, CopyTermAssignmentsAction $action): JsonResponse
    {
        $this->authorizeManage($request, $term);
        TermGate::assertWritable($term);

        $data = $request->validate([
            'source_term_id' => [
                'required', 'integer', 'different:term',
                Rule::exists('terms', 'id')->where('branch_id', $term->branch_id)->whereNull('deleted_at'),
            ],
            // Force mode: overwrite existing pairs' teachers from the source.
            'replace' => ['sometimes', 'boolean'],
        ]);

        abort_if((int) $data['source_term_id'] === $term->id, 422, 'Pick a different semester to copy from.');

        $source = Term::findOrFail($data['source_term_id']);
        $result = $action->execute($term, $source, (bool) ($data['replace'] ?? false));

        $message = match (true) {
            $result['created'] > 0 && $result['updated'] > 0 => "{$result['created']} assignments copied and {$result['updated']} replaced from \"{$source->name}\".",
            $result['created'] > 0 => "{$result['created']} assignments copied from \"{$source->name}\".",
            $result['updated'] > 0 => "{$result['updated']} assignments replaced from \"{$source->name}\".",
            default => 'Nothing to copy — every pair already exists on this semester.',
        };

        return response()->json([
            'data' => $result,
            'message' => $message,
        ]);
    }

    private function authorizeManage(Request $request, Term $term): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.manage', $term->school_id, $term->branch_id),
            403,
        );
    }
}
