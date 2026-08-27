<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\TermStatus;
use App\Enums\TimetableVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SectionHomeroom;
use App\Models\StudentEnrollment;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\TimetableVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The teacher's actual workload for one semester, read from the employee
 * profile: subject assignments (what they teach, where, how often), the
 * homeroom they hold this year, and — when the term's timetable is published —
 * their personal week so the profile can render a schedule without opening
 * the timetable module. One response, no request fan-out.
 */
class EmployeeTeachingController extends Controller
{
    public function index(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $terms = Term::query()
            ->where('branch_id', $employee->branch_id)
            ->with('academicYear:id,name')
            ->orderByDesc('starts_on')
            ->get(['id', 'academic_year_id', 'name', 'status', 'is_current', 'starts_on', 'ends_on']);

        $term = $this->resolveTerm($request, $terms);

        if ($term === null) {
            return response()->json([
                'data' => ['term_id' => null, 'assignments' => [], 'homeroom_sections' => [], 'week' => null],
                'meta' => ['terms' => $this->termOptions($terms)],
            ]);
        }

        $assignments = SubjectAssignment::query()
            ->where('term_id', $term->id)
            ->where('employee_id', $employee->id)
            ->with([
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
                'subject:id,code,name,category',
            ])
            ->get()
            ->sortBy([
                fn ($a, $b) => strcmp((string) $a->subject?->name, (string) $b->subject?->name),
                fn ($a, $b) => ($a->section->gradeLevel?->sort_order ?? 0) <=> ($b->section->gradeLevel?->sort_order ?? 0),
                fn ($a, $b) => strcmp($a->section->name, $b->section->name),
            ])
            ->values();

        // Live head counts per section — one grouped query, not per row.
        $studentCounts = StudentEnrollment::query()
            ->whereIn('section_id', $assignments->pluck('section_id')->unique())
            ->where('status', EnrollmentStatus::Active->value)
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        $homerooms = SectionHomeroom::query()
            ->where('employee_id', $employee->id)
            ->where('academic_year_id', $term->academic_year_id)
            ->with(['section:id,name,grade_level_id', 'section.gradeLevel:id,name'])
            ->get();

        return response()->json([
            'data' => [
                'term_id' => $term->id,
                'assignments' => $assignments->map(fn (SubjectAssignment $a) => [
                    'id' => $a->id,
                    'subject_id' => $a->subject_id,
                    'subject_name' => $a->subject?->name,
                    'subject_code' => $a->subject?->code,
                    'subject_category' => $a->subject?->category,
                    'section_id' => $a->section_id,
                    'section_name' => $a->section->name,
                    'grade_level_name' => $a->section->gradeLevel?->name,
                    'grade_level_sort' => $a->section->gradeLevel?->sort_order,
                    'periods_per_week' => $a->periods_per_week,
                    'students' => (int) ($studentCounts[$a->section_id] ?? 0),
                    'is_active' => $a->is_active,
                ])->values(),
                'homeroom_sections' => $homerooms->map(fn (SectionHomeroom $h) => [
                    'section_id' => $h->section_id,
                    'section_name' => $h->section?->name,
                    'grade_level_name' => $h->section?->gradeLevel?->name,
                ])->values(),
                'week' => $this->week($term, $assignments),
            ],
            'meta' => ['terms' => $this->termOptions($terms)],
        ]);
    }

    /**
     * The requested term when it belongs to the same branch, else the branch's
     * current/active semester, else the most recent one.
     *
     * @param  Collection<int, Term>  $terms
     */
    private function resolveTerm(Request $request, Collection $terms): ?Term
    {
        if ($request->filled('term_id')) {
            $picked = $terms->firstWhere('id', $request->integer('term_id'));
            abort_if($picked === null, 404, 'Semester not found for this branch.');

            return $picked;
        }

        return $terms->firstWhere('is_current', true)
            ?? $terms->firstWhere('status', TermStatus::Active)
            ?? $terms->first();
    }

    /**
     * @param  Collection<int, Term>  $terms
     * @return Collection<int, array<string, mixed>>
     */
    private function termOptions(Collection $terms): Collection
    {
        return $terms->map(fn (Term $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'year_name' => $t->academicYear?->name,
            'status' => $t->status->value,
            'is_current' => $t->is_current,
        ])->values();
    }

    /**
     * The teacher's personal week from the term's PUBLISHED timetable version:
     * their slots plus the period schedule (times come from term_periods, never
     * from slots). Null when nothing is published yet.
     *
     * @param  Collection<int, SubjectAssignment>  $assignments
     * @return array<string, mixed>|null
     */
    private function week(Term $term, Collection $assignments): ?array
    {
        if ($assignments->isEmpty()) {
            return null;
        }

        $version = TimetableVersion::query()
            ->where('term_id', $term->id)
            ->where('status', TimetableVersionStatus::Published)
            ->latest('published_at')
            ->first();

        if ($version === null) {
            return null;
        }

        $byId = $assignments->keyBy('id');

        $slots = $version->slots()
            ->whereIn('subject_assignment_id', $assignments->pluck('id'))
            ->with('room:id,name')
            ->orderBy('day_of_week')
            ->orderBy('period_number')
            ->get()
            ->map(function ($slot) use ($byId) {
                $assignment = $byId[$slot->subject_assignment_id];

                return [
                    'day_of_week' => $slot->day_of_week,
                    'period_number' => $slot->period_number,
                    'subject_code' => $assignment->subject?->code,
                    'subject_name' => $assignment->subject?->name,
                    'section_name' => $assignment->section->name,
                    'grade_level_name' => $assignment->section->gradeLevel?->name,
                    'room_name' => $slot->room?->name,
                ];
            });

        return [
            'days' => array_values($version->days ?? []) ?: $slots->pluck('day_of_week')->unique()->sort()->values()->all(),
            'periods' => $term->loadMissing('periods')->periods->map(fn ($p) => [
                'sequence' => $p->sequence,
                'type' => $p->type,
                'period_number' => $p->period_number,
                'label' => $p->label,
                'starts_at' => substr((string) $p->starts_at, 0, 5),
                'ends_at' => substr((string) $p->ends_at, 0, 5),
            ])->values(),
            'slots' => $slots->values(),
        ];
    }
}
