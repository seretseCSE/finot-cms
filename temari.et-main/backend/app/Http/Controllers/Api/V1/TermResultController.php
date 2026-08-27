<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ComputeTermResultsAction;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentTermResultResource;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\Term;
use App\Models\User;
use App\Support\ReportCardSettings;
use App\Support\TermGate;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

/**
 * Semester report-card rows (student_term_results). The term-close job
 * freezes them automatically; `compute` lets staff refresh a preview while
 * the term is still active; `saveConduct` layers the homeroom's conduct
 * marks + comments on top.
 */
class TermResultController extends Controller
{
    use HandlesListQueries;

    public function index(Request $request, Term $term): AnonymousResourceCollection
    {
        // Supervisory grades.view reads the whole term; a teacher
        // (grades.manage_own) reads ONLY the sections where they hold this
        // year's homeroom — they assemble those report cards (conduct +
        // comments). No homeroom = an empty list, never another class's rows.
        $user = $request->user();
        $supervisor = $user->hasPermissionForScope('grades.view', $term->school_id, $term->branch_id);

        abort_unless(
            $supervisor || $user->hasPermissionForScope('grades.manage_own', $term->school_id, $term->branch_id),
            403,
        );

        $results = StudentTermResult::query()
            ->where('term_id', $term->id)
            ->when(
                ! $supervisor,
                fn ($q) => $q->whereIn('section_id', $user->homeroomSectionIds($term->academic_year_id)),
            )
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->when($request->filled('grade_level_id'), fn ($q) => $q->where('grade_level_id', $request->integer('grade_level_id')))
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->whereHas('student', fn ($s) => $s->where('search_text', 'ilike', $this->needle($n)))))
            ->with([
                'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
            ])
            ->orderByRaw('rank IS NULL, rank')
            ->paginate(min($request->integer('per_page', 50), 200));

        return StudentTermResultResource::collection($results);
    }

    /**
     * Every frozen semester result of ONE enrollment, oldest first — powers
     * the lazy academic-performance modal on the student profile. Read-only
     * over already-frozen rows, so any grades.view holder in scope may look.
     */
    public function forEnrollment(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.view', $enrollment->school_id, $enrollment->branch_id),
            403,
        );

        $results = $enrollment->termResults()
            ->with([
                'term:id,name,sequence,status',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
            ])
            ->get()
            ->sortBy(fn (StudentTermResult $r) => $r->term?->sequence)
            ->values();

        return response()->json([
            'data' => StudentTermResultResource::collection($results),
        ]);
    }

    /**
     * Recompute the term's results synchronously-queued (small branches finish
     * in seconds). Any state — closed terms may also be recomputed by
     * supervisors after a correction, since the source marks are gate-locked.
     */
    public function compute(Request $request, Term $term, ComputeTermResultsAction $action): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.manage', $term->school_id, $term->branch_id),
            403,
        );

        $count = $action->execute($term);

        return response()->json([
            'message' => 'Results computed.',
            'meta' => ['rows' => $count],
        ]);
    }

    /**
     * Bulk-save conduct marks (ሥነ ምግባር), homeroom comments and the report
     * card's behavioral skill ratings onto the frozen rows. Entered by the
     * section's homeroom teacher or any grades.manage supervisor; survives
     * recomputes (the freeze never touches these fields). Skill keys are
     * validated against the branch's configured checklist; a submitted map
     * replaces the row's map wholesale (the modal always sends all rows).
     */
    public function saveConduct(Request $request, Term $term): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.student_enrollment_id' => ['required', 'integer'],
            'rows.*.conduct' => ['nullable', 'string', 'max:5'],
            'rows.*.comment' => ['nullable', 'string', 'max:255'],
            'rows.*.skills' => ['sometimes', 'nullable', 'array'],
            'rows.*.skills.*' => ['nullable', 'string', Rule::in(ReportCardSettings::RATINGS)],
        ]);

        $allowedSkillKeys = array_column($term->branch?->effectiveReportCardSkills() ?? [], 'key');

        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('grades.manage', $term->school_id, $term->branch_id)
            || ($user->hasPermissionForScope('grades.manage_own', $term->school_id, $term->branch_id)
                && $this->isHomeroomFor($user, $term, collect($data['rows'])->pluck('student_enrollment_id'))),
            403,
        );
        TermGate::assertWritable($term);

        $updated = 0;

        foreach ($data['rows'] as $row) {
            $patch = [
                'conduct' => $row['conduct'] ?? null,
                'comment' => $row['comment'] ?? null,
            ];

            // Only rows that carry the key touch skills — the quick inline
            // conduct save must never wipe ratings entered in the modal.
            if (array_key_exists('skills', $row)) {
                $skills = collect($row['skills'] ?? [])
                    ->only($allowedSkillKeys)
                    ->filter(fn ($rating) => $rating !== null)
                    ->all();

                // Query-builder update bypasses Eloquent casts — encode here.
                $patch['skills'] = $skills === [] ? null : json_encode($skills);
            }

            $updated += StudentTermResult::query()
                ->where('term_id', $term->id)
                ->where('student_enrollment_id', $row['student_enrollment_id'])
                ->update($patch);
        }

        return response()->json(['message' => 'Conduct saved.', 'meta' => ['rows' => $updated]]);
    }

    /**
     * A teacher may enter conduct only for sections where they hold this
     * year's homeroom — and every submitted row must belong to one of them.
     *
     * @param  Collection<int, int>  $enrollmentIds
     */
    private function isHomeroomFor(User $user, Term $term, Collection $enrollmentIds): bool
    {
        $homeroomSectionIds = $user->homeroomSectionIds($term->academic_year_id);

        if ($homeroomSectionIds === []) {
            return false;
        }

        return StudentTermResult::query()
            ->where('term_id', $term->id)
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->whereNotIn('section_id', $homeroomSectionIds)
            ->doesntExist();
    }
}
