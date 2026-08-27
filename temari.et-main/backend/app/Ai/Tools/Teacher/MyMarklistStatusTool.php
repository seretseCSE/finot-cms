<?php

namespace App\Ai\Tools\Teacher;

use App\Models\Marklist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The teacher's own marklists this term: which classes still need marks
 * entered/submitted and which came back declined.
 */
class MyMarklistStatusTool extends TeacherScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get the status of the teacher\'s own marklists (draft/submitted/approved per class and term). Use for "which marks do I still need to submit?".';
    }

    public function handle(Request $request): Stringable|string
    {
        $marklists = Marklist::query()
            ->whereIn('subject_assignment_id', $this->ownAssignments()->pluck('id'))
            ->with(['subjectAssignment.subject:id,name', 'subjectAssignment.section:id,name', 'term:id,name,is_current'])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Marklist $marklist): array => [
                'subject' => $marklist->subjectAssignment?->subject?->name,
                'section' => $marklist->subjectAssignment?->section?->name,
                'term' => $marklist->term?->name,
                'term_is_current' => (bool) $marklist->term?->is_current,
                'status' => $marklist->status->value,
                'submitted_at' => $marklist->submitted_at?->toDateTimeString(),
                'remarks' => $marklist->remarks,
            ]);

        if ($marklists->isEmpty()) {
            return $this->deny('No marklists found for your classes.');
        }

        return $this->ok($marklists);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
