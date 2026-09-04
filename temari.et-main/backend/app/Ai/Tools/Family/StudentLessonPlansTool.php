<?php

namespace App\Ai\Tools\Family;

use App\Services\LessonPlans\FamilyLessonPlanPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * What each subject teacher planned (approved plans only): syllabus
 * progress, chapter timeline, this week's approved topics and homework —
 * the ground truth for "what is my child learning right now?".
 */
class StudentLessonPlansTool extends StudentScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get the approved syllabus roadmap per subject: chapters, class progress percent, and this week\'s planned topics/homework. Use for questions about what the class is currently studying or covering next.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$student, , $denial] = $this->resolveStudent($request->integer('student_id') ?: null);

        if ($denial !== null) {
            return $this->deny($denial);
        }

        return $this->ok(app(FamilyLessonPlanPayload::class)->forStudent($student));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('Parent lane only: the child to look at (from my_children). Omit in the student lane.'),
        ];
    }
}
