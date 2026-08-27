<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\AiContext;
use App\Ai\Tools\Teacher\ClassStudentTool;
use App\Enums\AiLane;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\School;
use App\Services\Ai\AiEntitlementService;
use App\Services\Ai\AiUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request as ToolRequest;

use function Laravel\Ai\agent;

/**
 * Named, embedded AI generators — the ✨ buttons inside existing screens
 * (question-bank studio, weekly planner, marklist comments, registrar
 * letters). One synchronous call, one deterministic deliverable, grounded
 * server-side (never model-recalled), quota-billed like a chat message.
 * Everything returned is a DRAFT the user reviews in the owning screen.
 */
class AiActionController extends Controller
{
    public function run(
        Request $request,
        AiEntitlementService $entitlements,
        AiUsageService $usage,
    ): JsonResponse {
        $user = $request->user();

        $data = $request->validate([
            'action' => ['required', Rule::in(['quiz_questions', 'report_comment', 'lesson_week', 'annual_units', 'daily_plan', 'parent_message', 'letter'])],
            'params' => ['nullable', 'array'],
        ]);

        $lane = $data['action'] === 'letter' ? AiLane::Registrar : AiLane::Teacher;

        $schoolId = $user->activeSchoolId();
        abort_if($schoolId === null, 422, 'Select a school workspace first.');

        abort_unless(
            in_array($lane, AiLane::availableFor($user, $schoolId, $user->activeBranchId()), true),
            403,
            'This AI action is not available for your role.',
        );

        $school = School::query()->find($schoolId);

        $entitlements->assertCanPrompt($user, $lane, $school);
        $usage->recordMessage($user);

        $context = new AiContext(
            user: $user,
            lane: $lane,
            school: $school,
            branch: $user->activeBranchId() !== null ? Branch::query()->find($user->activeBranchId()) : null,
        );

        $params = $data['params'] ?? [];

        $result = match ($data['action']) {
            'quiz_questions' => $this->quizQuestions($context, $params),
            'report_comment' => $this->reportComment($context, $params),
            'lesson_week' => $this->lessonWeek($context, $params),
            'annual_units' => $this->annualUnits($context, $params),
            'daily_plan' => $this->dailyPlan($context, $params),
            'parent_message' => $this->parentMessage($context, $params),
            'letter' => $this->letter($context, $params),
        };

        return response()->json(['data' => $result]);
    }

    /**
     * Draft exam questions as STRUCTURED JSON the question-bank studio can
     * preview and save (through its normal validated store flow).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function quizQuestions(AiContext $context, array $params): array
    {
        $subject = mb_substr(trim((string) ($params['subject'] ?? '')), 0, 80);
        $grade = mb_substr(trim((string) ($params['grade'] ?? '')), 0, 40);
        $topic = mb_substr(trim((string) ($params['topic'] ?? '')), 0, 200);
        $count = min(max((int) ($params['count'] ?? 5), 1), 10);
        $difficulty = in_array($params['difficulty'] ?? null, ['easy', 'medium', 'hard', 'mixed'], true) ? $params['difficulty'] : 'mixed';
        $language = in_array($params['language'] ?? null, ['en', 'am', 'om'], true) ? $params['language'] : 'en';

        $allowedTypes = ['mcq_single', 'true_false', 'short_answer', 'matching', 'passage'];
        $types = array_values(array_intersect($allowedTypes, array_map(strval(...), (array) ($params['types'] ?? []))));
        if ($types === []) {
            $types = ['mcq_single', 'true_false', 'short_answer'];
        }

        $notes = mb_substr(trim((string) ($params['notes'] ?? '')), 0, 500);

        abort_if($subject === '' || $topic === '', 422, 'Fill in the subject and the topic first.');

        // "passage" is a UI choice, not a row type: rows come back as
        // `type: "group"` containers carrying sub_questions.
        $withPassage = in_array('passage', $types, true);
        $rowTypes = array_values(array_diff($types, ['passage']));
        if ($rowTypes === []) {
            $rowTypes = ['mcq_single'];
        }

        $difficultyText = $difficulty === 'mixed'
            ? 'questions with a balanced mix of easy, medium and hard difficulty'
            : "{$difficulty} questions";
        $typeText = count($rowTypes) === 1
            ? 'Every question must be of type '.$rowTypes[0].'.'
            : 'Use only these question types, sensibly mixed: '.implode(', ', $rowTypes).'.';
        $passageText = $withPassage
            ? ' Structure the set around ONE reading passage: a row with type "group" whose stem is the full passage text (a few paragraphs, appropriate for the grade) and whose sub_questions are answered from it; standalone questions may accompany it only if the count allows.'
            : ' Never use type "group".';

        // The row shape (shared by top-level questions and a group's
        // sub-questions). Fully typed items — a loose array schema lets the
        // model improvise shapes (e.g. flat option lists) the studio can't
        // save.
        $rowFields = function ($schema): array {
            return [
                'type' => $schema->string()->description('mcq_single, true_false, short_answer or matching.')->required(),
                'stem' => $schema->string()->description('The question text (for a group: the full passage).')->required(),
                'options' => $schema->array()->items($schema->object([
                    'id' => $schema->string()->description('a, b, c or d.')->required(),
                    'text' => $schema->string()->required(),
                ]))->description('mcq_single only: exactly 4 options.'),
                'correct' => $schema->string()->description('mcq_single: the correct option id. true_false: "true" or "false". short_answer: the expected answer.')->required(),
                'matching_pairs' => $schema->array()->items($schema->object([
                    'left' => $schema->string()->required(),
                    'right' => $schema->string()->required(),
                ]))->description('matching only: 3–8 CORRECTLY matched pairs.'),
                'explanation' => $schema->string()->description('One sentence on why the answer is correct.')->required(),
            ];
        };

        $response = agent(
            'You write assessment questions for Ethiopian K-12 schools, aligned to the national curriculum. Accuracy first: every answer key must be unambiguously correct.',
            schema: fn ($schema) => [
                'questions' => $schema->array()->items($schema->object([
                    ...$rowFields($schema),
                    'sub_questions' => $schema->array()->items($schema->object($rowFields($schema)))
                        ->description('type group only: the questions answered from the passage.'),
                ]))->required(),
            ],
        )->prompt(
            "Write {$count} {$difficultyText} for {$subject}".($grade !== '' ? " ({$grade})" : '')
            ." on: {$topic}. Language: ".match ($language) {
                'am' => 'Amharic', 'om' => 'Afan Oromo', default => 'English'
            }
            .'. '.$typeText.$passageText.' Include a one-sentence explanation per question.'
            .($notes !== '' ? " Teacher's instructions: {$notes}" : ''),
            model: (string) config('temari-ai.model'),
            timeout: (int) config('temari-ai.timeout'),
        );

        $decoded = json_decode($response->text, true);

        // A passage group counts by its sub-questions, so trimming to the
        // requested count must never slice through the middle of one row set.
        $rows = is_array($decoded['questions'] ?? null) ? array_values(array_filter($decoded['questions'], 'is_array')) : [];
        $kept = [];
        $total = 0;
        foreach ($rows as $row) {
            $weight = ($row['type'] ?? null) === 'group'
                ? max(1, count(array_filter((array) ($row['sub_questions'] ?? []), 'is_array')))
                : 1;
            if ($total > 0 && $total + $weight > $count) {
                break;
            }
            $kept[] = $row;
            $total += $weight;
        }

        return [
            'action' => 'quiz_questions',
            'questions' => $kept,
        ];
    }

    /**
     * A report-card comment for one student in the teacher's own classes —
     * grounded through the SAME ownership-gated tool the chat uses.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function reportComment(AiContext $context, array $params): array
    {
        $studentId = (int) ($params['student_id'] ?? 0);
        abort_if($studentId <= 0, 422, 'student_id is required.');

        $grounding = (new ClassStudentTool($context))->handle(new ToolRequest(['student_id' => $studentId]));
        $payload = json_decode((string) $grounding, true);

        abort_unless(($payload['ok'] ?? false) === true, 403, $payload['reason'] ?? 'Student not in your classes.');

        $language = in_array($params['language'] ?? null, ['en', 'am'], true) ? $params['language'] : 'en';

        $response = agent(
            'You draft report-card comments for teachers: 2–3 sentences, warm and specific — one strength, one growth area, one next step. Never shame the student. Ground every claim in the data given; no invented facts.',
        )->prompt(
            'Student data (JSON): '.json_encode($payload['data'], JSON_UNESCAPED_UNICODE)
            ."\n\nWrite the comment in ".($language === 'am' ? 'Amharic' : 'English').'.'
            .(isset($params['tone']) ? ' Tone hint: '.mb_substr((string) $params['tone'], 0, 100) : ''),
            model: (string) config('temari-ai.model'),
            timeout: (int) config('temari-ai.timeout'),
        );

        return ['action' => 'report_comment', 'comment' => trim($response->text)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function lessonWeek(AiContext $context, array $params): array
    {
        $subject = mb_substr(trim((string) ($params['subject'] ?? '')), 0, 80);
        $grade = mb_substr(trim((string) ($params['grade'] ?? '')), 0, 40);
        $unit = mb_substr(trim((string) ($params['unit'] ?? '')), 0, 200);
        $periods = min(max((int) ($params['periods'] ?? 5), 1), 10);

        abort_if($subject === '' || $unit === '', 422, 'subject and unit are required.');

        $response = agent(
            'You draft weekly lesson plans for Ethiopian K-12 teachers following the national curriculum: per lesson — topic, objectives (measurable), teaching activities, and homework. Practical for large classes with few materials.',
        )->prompt(
            "Draft {$periods} lessons for {$subject}".($grade !== '' ? " ({$grade})" : '')." covering the unit: {$unit}."
            .(isset($params['notes']) ? ' Teacher notes: '.mb_substr((string) $params['notes'], 0, 500) : '')
            .' Format as markdown with one section per lesson.',
            model: (string) config('temari-ai.model'),
            timeout: (int) config('temari-ai.timeout'),
        );

        return ['action' => 'lesson_week', 'draft' => trim($response->text)];
    }

    /**
     * The year's unit roadmap as STRUCTURED rows matching the MoE annual-plan
     * grid — the plan workspace previews them and inserts the ones the
     * teacher keeps through the normal unit store flow. Dates are left to the
     * teacher: the pacing math anchors on real calendar windows.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function annualUnits(AiContext $context, array $params): array
    {
        $subject = mb_substr(trim((string) ($params['subject'] ?? '')), 0, 80);
        $grade = mb_substr(trim((string) ($params['grade'] ?? '')), 0, 40);
        $goals = mb_substr(trim(strip_tags((string) ($params['goals'] ?? ''))), 0, 1000);
        $chapters = mb_substr(trim((string) ($params['chapters'] ?? '')), 0, 1500);
        $count = min(max((int) ($params['count'] ?? 6), 1), 15);
        $totalPeriods = min(max((int) ($params['total_periods'] ?? 0), 0), 2000);
        $notes = mb_substr(trim((string) ($params['notes'] ?? '')), 0, 500);

        abort_if($subject === '', 422, 'subject is required.');

        $response = agent(
            'You draft annual lesson-plan unit grids for Ethiopian K-12 teachers in the Ministry of Education format, '
            .'following the national curriculum textbook structure for the subject and grade. Objectives are measurable '
            .'("students will be able to…"); every field is concise (1-2 sentences). Practical for large classes with few materials.',
            schema: fn ($schema) => [
                'units' => $schema->array()->items($schema->object([
                    'title' => $schema->string()->description('The unit/chapter title.')->required(),
                    'objectives' => $schema->string()->required(),
                    'rationale' => $schema->string()->description('Why this unit matters.')->required(),
                    'prerequisite_knowledge' => $schema->string()->description('What students must already know.')->required(),
                    'methods' => $schema->string()->description('Main teaching methods for the unit.')->required(),
                    'teaching_aids' => $schema->string()->required(),
                    'assessment_techniques' => $schema->string()->required(),
                    'planned_periods' => $schema->string()->description('Number of periods this unit needs, digits only.')->required(),
                ]))->required(),
            ],
        )->prompt(
            "Draft the year's {$count} teaching units for {$subject}".($grade !== '' ? " ({$grade})" : '').'.'
            .($chapters !== '' ? " The textbook chapters / topics to cover:\n{$chapters}" : ' Follow the standard national-curriculum chapter sequence.')
            .($goals !== '' ? "\nThe teacher's year goals: {$goals}" : '')
            .($totalPeriods > 0 ? "\nDistribute roughly {$totalPeriods} total periods across the units by weight." : '')
            .($notes !== '' ? "\nTeacher's instructions: {$notes}" : ''),
            model: (string) config('temari-ai.model'),
            timeout: (int) config('temari-ai.timeout'),
        );

        $decoded = json_decode($response->text, true);

        $units = collect(is_array($decoded['units'] ?? null) ? $decoded['units'] : [])
            ->filter(fn ($u) => is_array($u) && trim((string) ($u['title'] ?? '')) !== '')
            ->map(fn (array $u): array => [
                'title' => mb_substr(trim((string) $u['title']), 0, 255),
                'objectives' => trim((string) ($u['objectives'] ?? '')) ?: null,
                'rationale' => trim((string) ($u['rationale'] ?? '')) ?: null,
                'prerequisite_knowledge' => trim((string) ($u['prerequisite_knowledge'] ?? '')) ?: null,
                'methods' => trim((string) ($u['methods'] ?? '')) ?: null,
                'teaching_aids' => trim((string) ($u['teaching_aids'] ?? '')) ?: null,
                'assessment_techniques' => trim((string) ($u['assessment_techniques'] ?? '')) ?: null,
                'planned_periods' => min(max((int) ($u['planned_periods'] ?? 0), 0), 500),
            ])
            ->take($count)
            ->values()
            ->all();

        return ['action' => 'annual_units', 'units' => $units];
    }

    /**
     * One STRUCTURED daily lesson plan in the MoE format — typed fields the
     * daily-plan studio prefills directly (topic, objectives, the three
     * stages with teacher/student activities, learner supports, homework).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function dailyPlan(AiContext $context, array $params): array
    {
        $subject = mb_substr(trim((string) ($params['subject'] ?? '')), 0, 80);
        $grade = mb_substr(trim((string) ($params['grade'] ?? '')), 0, 40);
        $unit = mb_substr(trim((string) ($params['unit'] ?? '')), 0, 200);
        $topic = mb_substr(trim((string) ($params['topic'] ?? '')), 0, 200);

        abort_if($subject === '' || $topic === '', 422, 'subject and topic are required.');

        $response = agent(
            'You draft daily lesson plans for Ethiopian K-12 teachers in the Ministry of Education format. '
            .'Objectives are measurable ("students will be able to…"). Activities must be practical for large '
            .'classes with few materials. Keep every field concise — 1-3 sentences.',
            schema: fn ($schema) => [
                'objectives' => $schema->string()->description('What students will be able to do, measurable.')->required(),
                'rationale' => $schema->string()->description('Why this topic matters.')->required(),
                'prerequisite_knowledge' => $schema->string()->description('What students must already know.')->required(),
                'stages' => $schema->array()->items($schema->object([
                    'stage' => $schema->string()->description('intro, main or conclusion.')->required(),
                    'learning_contents' => $schema->string()->required(),
                    'teacher_activity' => $schema->string()->required(),
                    'student_activity' => $schema->string()->required(),
                    'assessment_techniques' => $schema->string()->required(),
                    'teaching_aids' => $schema->string()->required(),
                ]))->description('Exactly three stages: intro, main, conclusion — in that order.')->required(),
                'support_slow' => $schema->string()->description('Support for slow learners.')->required(),
                'support_medium' => $schema->string()->description('Support for medium learners.')->required(),
                'support_fast' => $schema->string()->description('Extension for fast learners.')->required(),
                'homework' => $schema->string()->description('A short homework task, or empty.')->required(),
            ],
        )->prompt(
            "Draft one daily lesson plan for {$subject}".($grade !== '' ? " ({$grade})" : '')
            .($unit !== '' ? ", unit: {$unit}" : '')
            .", topic: {$topic}."
            .(isset($params['notes']) ? ' Teacher notes: '.mb_substr((string) $params['notes'], 0, 500) : ''),
            model: (string) config('temari-ai.model'),
            timeout: (int) config('temari-ai.timeout'),
        );

        $decoded = json_decode($response->text, true) ?: [];

        $stages = collect(is_array($decoded['stages'] ?? null) ? $decoded['stages'] : [])
            ->filter(fn ($s) => in_array($s['stage'] ?? null, ['intro', 'main', 'conclusion'], true))
            ->unique('stage')
            ->values()
            ->all();

        return [
            'action' => 'daily_plan',
            'plan' => [
                'objectives' => (string) ($decoded['objectives'] ?? ''),
                'rationale' => (string) ($decoded['rationale'] ?? ''),
                'prerequisite_knowledge' => (string) ($decoded['prerequisite_knowledge'] ?? ''),
                'stages' => $stages,
                'support_slow' => (string) ($decoded['support_slow'] ?? ''),
                'support_medium' => (string) ($decoded['support_medium'] ?? ''),
                'support_fast' => (string) ($decoded['support_fast'] ?? ''),
                'homework' => (string) ($decoded['homework'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function parentMessage(AiContext $context, array $params): array
    {
        $studentId = (int) ($params['student_id'] ?? 0);
        abort_if($studentId <= 0, 422, 'student_id is required.');

        $grounding = (new ClassStudentTool($context))->handle(new ToolRequest(['student_id' => $studentId]));
        $payload = json_decode((string) $grounding, true);

        abort_unless(($payload['ok'] ?? false) === true, 403, $payload['reason'] ?? 'Student not in your classes.');

        $topic = mb_substr(trim((string) ($params['topic'] ?? 'academic progress')), 0, 200);

        $response = agent(
            'You draft short, respectful teacher-to-parent messages for Ethiopian schools, in BOTH English and Amharic (English first, then Amharic). Constructive and specific; never blame the child; end with an invitation to talk.',
        )->prompt(
            'Student data (JSON): '.json_encode($payload['data'], JSON_UNESCAPED_UNICODE)
            ."\n\nTopic: {$topic}. Keep each language under 80 words.",
            model: (string) config('temari-ai.model'),
            timeout: (int) config('temari-ai.timeout'),
        );

        return ['action' => 'parent_message', 'draft' => trim($response->text)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function letter(AiContext $context, array $params): array
    {
        $type = in_array($params['type'] ?? null, ['recommendation', 'enrollment_confirmation', 'guardian_invitation', 'general'], true)
            ? $params['type']
            : 'general';
        $language = in_array($params['language'] ?? null, ['en', 'am'], true) ? $params['language'] : 'en';
        $details = mb_substr(trim((string) ($params['details'] ?? '')), 0, 1000);

        abort_if($details === '', 422, 'details are required.');

        $response = agent(
            'You draft formal Ethiopian school letters for a registrar: proper heading placeholders ([School letterhead], [Date]), formal register, clear paragraphs, signature block. Anything not provided stays as a [bracketed placeholder] — never invent names, dates or numbers. This is a working draft; official documents are generated by the school system.',
        )->prompt(
            "Letter type: {$type}. Language: ".($language === 'am' ? 'Amharic' : 'English').".\nDetails from the registrar: {$details}",
            model: (string) config('temari-ai.model'),
            timeout: (int) config('temari-ai.timeout'),
        );

        return ['action' => 'letter', 'draft' => trim($response->text)];
    }
}
