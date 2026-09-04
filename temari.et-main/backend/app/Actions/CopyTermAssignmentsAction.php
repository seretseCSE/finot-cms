<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Support\JobTitles;
use Illuminate\Support\Facades\DB;

/**
 * Copies the section → subject → teacher grid from one semester to another of
 * the SAME branch (clone semester / "copy from another semester"). Pairs
 * already present on the target are left untouched — unless REPLACE is on, in
 * which case their teacher is overwritten from the source. Teachers who are no
 * longer active (or no longer hold a teacher position) copy over as unassigned.
 */
class CopyTermAssignmentsAction
{
    /**
     * @return array{created: int, updated: int}
     */
    public function execute(Term $target, Term $source, bool $replace = false): array
    {
        return DB::transaction(function () use ($target, $source, $replace): array {
            $sourceRows = SubjectAssignment::query()
                ->where('term_id', $source->id)
                ->where('is_active', true)
                ->get(['section_id', 'subject_id', 'employee_id', 'periods_per_week']);

            // First target row per (section, subject) pair — the row a REPLACE
            // overwrites. Extra team-teaching rows are left alone.
            $existing = SubjectAssignment::query()
                ->where('term_id', $target->id)
                ->orderBy('id')
                ->get(['id', 'section_id', 'subject_id', 'employee_id'])
                ->keyBy(fn ($a) => "{$a->section_id}:{$a->subject_id}");

            $eligibleTeacherIds = Employee::query()
                ->where('branch_id', $target->branch_id)
                ->where('is_active', true)
                ->whereHas('positions', fn ($p) => $p
                    ->where('job_title', JobTitles::TEACHER)
                    ->whereNull('ended_on'))
                ->pluck('id')
                ->flip();

            $now = now();
            $rows = [];
            $seen = [];
            $updated = 0;

            foreach ($sourceRows as $row) {
                $pair = "{$row->section_id}:{$row->subject_id}";

                // Team-teaching source rows collapse to one target row per pair
                // (the first teacher wins; the rest is a human decision).
                if (isset($seen[$pair])) {
                    continue;
                }
                $seen[$pair] = true;

                if ($existing->has($pair)) {
                    if (! $replace) {
                        continue;
                    }

                    $employeeId = ($row->employee_id !== null && $eligibleTeacherIds->has($row->employee_id))
                        ? $row->employee_id
                        : null;

                    $current = $existing->get($pair);
                    if ((int) ($current->employee_id ?? 0) !== (int) ($employeeId ?? 0)) {
                        SubjectAssignment::whereKey($current->id)->update([
                            'employee_id' => $employeeId,
                            'periods_per_week' => $row->periods_per_week,
                            'updated_at' => $now,
                        ]);
                        $updated++;
                    }

                    continue;
                }

                $rows[] = [
                    'school_id' => $target->school_id,
                    'branch_id' => $target->branch_id,
                    'academic_year_id' => $target->academic_year_id,
                    'section_id' => $row->section_id,
                    'subject_id' => $row->subject_id,
                    'term_id' => $target->id,
                    'employee_id' => ($row->employee_id !== null && $eligibleTeacherIds->has($row->employee_id))
                        ? $row->employee_id
                        : null,
                    'periods_per_week' => $row->periods_per_week,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('subject_assignments')->insert($chunk);
            }

            return ['created' => count($rows), 'updated' => $updated];
        });
    }
}
