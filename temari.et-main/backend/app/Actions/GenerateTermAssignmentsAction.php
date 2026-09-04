<?php

namespace App\Actions;

use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\TeacherSubject;
use App\Models\Term;
use App\Support\JobTitles;
use Illuminate\Support\Facades\DB;

/**
 * Pre-builds a semester's teaching grid, CURRICULUM-first: one row per
 * (active section × subject applicable to its grade), so no subject slot can
 * be silently forgotten. The teacher is pre-filled only when exactly ONE
 * active teacher in the branch has declared that subject × grade capability —
 * ambiguity stays a human decision (row left unassigned). Idempotent: pairs
 * that already exist on the term are skipped, so re-running never duplicates.
 */
class GenerateTermAssignmentsAction
{
    /** @return int Number of assignment rows created. */
    public function execute(Term $term): int
    {
        return DB::transaction(function () use ($term): int {
            $sections = Section::query()
                ->where('branch_id', $term->branch_id)
                ->where('is_active', true)
                ->with('gradeLevel:id,sort_order')
                ->get();

            $subjects = Subject::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $term->school_id))
                ->with('gradeLevels:grade_levels.id,sort_order')
                ->get(['id']);

            $existing = SubjectAssignment::query()
                ->where('term_id', $term->id)
                ->get(['section_id', 'subject_id'])
                ->map(fn ($a) => "{$a->section_id}:{$a->subject_id}")
                ->flip();

            // subject:grade → list of capable active teachers in this branch.
            $candidates = TeacherSubject::query()
                ->whereHas('employee', fn ($q) => $q
                    ->where('branch_id', $term->branch_id)
                    ->where('is_active', true)
                    ->whereHas('positions', fn ($p) => $p
                        ->where('job_title', JobTitles::TEACHER)
                        ->whereNull('ended_on')))
                ->get(['employee_id', 'subject_id', 'grade_level_id'])
                ->groupBy(fn (TeacherSubject $ts) => "{$ts->subject_id}:{$ts->grade_level_id}");

            $now = now();
            $rows = [];

            foreach ($sections as $section) {
                $gradeSort = (int) $section->gradeLevel->sort_order;

                foreach ($subjects as $subject) {
                    if (! $subject->appliesToGradeSort($gradeSort)) {
                        continue;
                    }

                    if ($existing->has("{$section->id}:{$subject->id}")) {
                        continue;
                    }

                    $capable = $candidates->get("{$subject->id}:{$section->grade_level_id}");

                    $rows[] = [
                        'school_id' => $term->school_id,
                        'branch_id' => $term->branch_id,
                        'academic_year_id' => $term->academic_year_id,
                        'section_id' => $section->id,
                        'subject_id' => $subject->id,
                        'term_id' => $term->id,
                        // Pre-fill only on an unambiguous single candidate.
                        'employee_id' => ($capable !== null && $capable->count() === 1)
                            ? $capable->first()->employee_id
                            : null,
                        'periods_per_week' => 0,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('subject_assignments')->insert($chunk);
            }

            return count($rows);
        });
    }
}
