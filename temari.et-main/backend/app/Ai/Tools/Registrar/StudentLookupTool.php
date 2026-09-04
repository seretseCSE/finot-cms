<?php

namespace App\Ai\Tools\Registrar;

use App\Ai\Tools\Leadership\LeadershipScopedTool;
use App\Models\Student;
use App\Support\SearchTerm;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Registrar lookup: find a student in scope by name or public ID and see
 * their enrollment position — the starting point for letters, transfers
 * and record questions. Profile depth (health, guardian phones, documents)
 * deliberately stays OUT of the AI surface.
 */
class StudentLookupTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Find students in this school by name or Temari ID: returns enrollment (grade, section, branch, status) and guardian count. Use before drafting letters or answering record questions. Max 10 matches.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('students.view')) !== null) {
            return $this->deny($denied);
        }

        $term = trim($request->string('query')->toString());

        if (mb_strlen($term) < 2) {
            return $this->deny('Give at least 2 characters of a name or a Temari ID.');
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $students = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->tap(fn ($q) => SearchTerm::apply($q, $term, fn ($w, string $n) => $w
                ->where('search_text', 'ilike', SearchTerm::contains($n))))
            ->with(['currentEnrollment.gradeLevel:id,name', 'currentEnrollment.section:id,name', 'currentEnrollment.branch:id,name'])
            ->withCount(['guardians as guardian_count' => fn ($q) => $q->where('is_active', true)])
            ->limit(10)
            ->get()
            ->map(fn (Student $student): array => [
                'student_id' => $student->id,
                'temari_id' => $student->public_id,
                'name' => $student->full_name,
                'link' => '/students/'.$student->id,
                'gender' => $student->gender,
                'grade' => $student->currentEnrollment?->gradeLevel?->name,
                'section' => $student->currentEnrollment?->section?->name,
                'branch' => $student->currentEnrollment?->branch?->name,
                'enrollment_status' => $student->currentEnrollment?->status,
                'guardians' => (int) $student->guardian_count,
            ]);

        if ($students->isEmpty()) {
            return $this->deny('No matching student in this school.');
        }

        return $this->ok($students);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Name (or part) or Temari student ID.'),
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
