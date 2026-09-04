<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use Illuminate\Support\Collection;

/**
 * The section-assignment board: sections of a grade with live counts, the
 * student pool (each with gender + last known average), and the balanced
 * auto-distribution. Auto never persists — it returns a PROPOSAL the staff
 * reviews and commits (CommitSectionAssignmentsAction).
 *
 * Balancing = serpentine (snake) distribution per gender bucket sorted by
 * average: every section ends with an even ability spread AND an even
 * gender split — the standard class-formation method.
 */
class SectionAssignmentService
{
    /**
     * @return array{sections: Collection<int, array<string, mixed>>, students: Collection<int, array<string, mixed>>}
     */
    public function board(AcademicYear $year, int $gradeLevelId): array
    {
        $sections = Section::query()
            ->where('branch_id', $year->branch_id)
            ->where('grade_level_id', $gradeLevelId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $students = $this->pool($year, $gradeLevelId);

        return [
            'sections' => $sections->map(fn (Section $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'capacity' => $s->capacity,
                'room_number' => $s->room_number,
            ])->values(),
            'students' => $students,
        ];
    }

    /**
     * Balanced proposal. `fill` places only unsectioned students around the
     * existing distribution; `reshuffle` redeals everyone.
     *
     * @return array{assignments: list<array{enrollment_id: int, section_id: ?int}>, unplaced: int}
     */
    public function propose(AcademicYear $year, int $gradeLevelId, string $mode = 'fill'): array
    {
        $board = $this->board($year, $gradeLevelId);
        $sections = $board['sections'];
        $students = $board['students'];

        if ($sections->isEmpty()) {
            return ['assignments' => [], 'unplaced' => $students->count()];
        }

        // Working state per section: current members (kept in `fill` mode).
        $state = $sections->mapWithKeys(fn (array $s) => [$s['id'] => [
            'capacity' => $s['capacity'],
            'count' => 0,
            'male' => 0,
            'female' => 0,
        ]])->all();

        $pool = collect();

        foreach ($students as $student) {
            $keep = $mode === 'fill'
                && $student['section_id'] !== null
                && array_key_exists($student['section_id'], $state);

            if ($keep) {
                $state[$student['section_id']]['count']++;
                $state[$student['section_id']][$this->genderKey($student['gender'])]++;
            } else {
                $pool->push($student);
            }
        }

        // Deal each gender bucket best-first into the emptiest section —
        // tie-broken by that gender's count, then by name for determinism.
        $assignments = [];
        $unplaced = 0;

        foreach (['female', 'male'] as $bucketGender) {
            $bucket = $pool
                ->filter(fn (array $s) => $this->genderKey($s['gender']) === $bucketGender)
                ->sortByDesc(fn (array $s) => $s['last_average'] ?? -1)
                ->values();

            foreach ($bucket as $student) {
                $targetId = $this->pickSection($state, $bucketGender);

                if ($targetId === null) {
                    $assignments[] = ['enrollment_id' => $student['enrollment_id'], 'section_id' => null];
                    $unplaced++;

                    continue;
                }

                $state[$targetId]['count']++;
                $state[$targetId][$bucketGender]++;
                $assignments[] = ['enrollment_id' => $student['enrollment_id'], 'section_id' => $targetId];
            }
        }

        return ['assignments' => $assignments, 'unplaced' => $unplaced];
    }

    /**
     * Live enrollments of (year, grade) with the facts balancing needs.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pool(AcademicYear $year, int $gradeLevelId): Collection
    {
        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('grade_level_id', $gradeLevelId)
            ->live()
            ->with('student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path')
            ->get();

        // Last known average per student: the most recently computed term
        // result (any year — covers promoted, repeating and transferred kids).
        $averages = StudentTermResult::query()
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->whereNotNull('average')
            ->orderByDesc('term_id')
            ->get(['student_id', 'average'])
            ->unique('student_id')
            ->keyBy('student_id');

        return $enrollments->map(fn (StudentEnrollment $e) => [
            'enrollment_id' => $e->id,
            'student_id' => $e->student_id,
            'full_name' => $e->student->full_name,
            'public_id' => $e->student->public_id,
            'gender' => $e->student->gender,
            'photo_url' => $e->student->photo_url,
            'section_id' => $e->section_id,
            'enrollment_status' => $e->status->value,
            'last_average' => isset($averages[$e->student_id]) ? (float) $averages[$e->student_id]->average : null,
        ])->sortBy('full_name')->values();
    }

    /**
     * The section that most needs the next student of this gender: fewest of
     * that gender, then fewest overall — skipping full sections.
     *
     * @param  array<int, array{capacity: ?int, count: int, male: int, female: int}>  $state
     */
    private function pickSection(array $state, string $gender): ?int
    {
        $best = null;
        $bestKey = null;

        foreach ($state as $id => $s) {
            if ($s['capacity'] !== null && $s['count'] >= $s['capacity']) {
                continue;
            }

            $key = [$s[$gender], $s['count'], $id];

            if ($bestKey === null || $key < $bestKey) {
                $bestKey = $key;
                $best = $id;
            }
        }

        return $best;
    }

    private function genderKey(mixed $gender): string
    {
        $value = $gender instanceof \BackedEnum ? $gender->value : (string) $gender;

        return strtolower($value) === 'female' ? 'female' : 'male';
    }
}
