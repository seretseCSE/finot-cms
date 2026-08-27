<?php

namespace App\Ai\Tools\Family;

use App\Services\Reports\StudentReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The student's published class timetable (same source as the /me screens).
 */
class StudentTimetableTool extends StudentScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get the weekly class timetable: subjects, teachers and periods per day. Use for questions about the schedule ("what do I have tomorrow?").';
    }

    public function handle(Request $request): Stringable|string
    {
        [$student, , $denial] = $this->resolveStudent($request->integer('student_id') ?: null);

        if ($denial !== null) {
            return $this->deny($denial);
        }

        $timetable = app(StudentReportService::class)->timetable($student);

        if ($timetable === null) {
            return $this->deny('No published timetable for the student\'s class yet.');
        }

        return $this->ok($timetable);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('Parent lane only: the child to look at (from my_children). Omit in the student lane.'),
        ];
    }
}
