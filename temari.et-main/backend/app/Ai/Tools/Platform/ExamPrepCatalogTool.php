<?php

namespace App\Ai\Tools\Platform;

use App\Ai\Tools\AiTool;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mock-exam discovery for platform staff (exam_prep.manage): the platform
 * grade levels and subjects (with the ids CreateMockExamTool takes) and the
 * PLATFORM question banks (school_id null — the national bank) with their
 * question counts. Pass question_bank_id for one bank's topic breakdown.
 */
class ExamPrepCatalogTool extends AiTool
{
    public function description(): Stringable|string
    {
        return 'The exam-prep catalog: platform grade levels and subjects (grade_level_id / subject_id for CreateMockExamTool) and the platform question banks with published/draft counts. Pass grade_level_id to narrow subjects to that grade; pass question_bank_id for one bank\'s breakdown by topic, type and difficulty. Call before building a mock exam.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->context->allows('exam_prep.manage')) {
            return $this->deny('Exam-prep authoring is for Temari.et content staff only.');
        }

        $input = $request->all();

        $grades = GradeLevel::query()
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'sort_order']);

        $gradeLevelId = (int) ($input['grade_level_id'] ?? 0);
        $gradeSort = $gradeLevelId > 0 ? $grades->firstWhere('id', $gradeLevelId)?->sort_order : null;

        $subjects = Subject::query()
            ->whereNull('school_id')
            ->where('is_active', true)
            ->when($gradeSort !== null, fn ($q) => $q->forGradeSorts([$gradeSort]))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $banks = QuestionBank::query()
            ->whereNull('school_id')
            ->where('is_active', true)
            ->with(['subject:id,name', 'gradeLevel:id,name'])
            ->withCount([
                'questions as published_count' => fn ($q) => $q->where('status', 'published'),
                'questions as draft_count' => fn ($q) => $q->where('status', 'draft'),
            ])
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $bankId = (int) ($input['question_bank_id'] ?? 0);
        $detail = null;

        if ($bankId > 0) {
            if ($banks->firstWhere('id', $bankId) === null) {
                return $this->deny('That bank is not a platform question bank.');
            }

            $detail = [
                'question_bank_id' => $bankId,
                'by_topic' => Question::query()
                    ->where('question_bank_id', $bankId)
                    ->where('status', 'published')
                    ->selectRaw("coalesce(topic, '(no topic)') as topic, type, coalesce(difficulty, 'unset') as difficulty, count(*) as count")
                    ->groupBy('topic', 'type', 'difficulty')
                    ->orderBy('topic')
                    ->limit(120)
                    ->get(),
            ];
        }

        return $this->ok([
            'grade_levels' => $grades->map(fn (GradeLevel $g): array => [
                'grade_level_id' => $g->id, 'code' => $g->code, 'name' => $g->name,
            ]),
            'subjects' => $subjects->map(fn (Subject $s): array => [
                'subject_id' => $s->id, 'code' => $s->code, 'name' => $s->name,
            ]),
            'banks' => $banks->map(fn (QuestionBank $bank): array => [
                'question_bank_id' => $bank->id,
                'name' => $bank->name,
                'subject' => $bank->subject?->name,
                'grade' => $bank->gradeLevel?->name,
                'topics' => array_slice($bank->topics ?? [], 0, 25),
                'published_questions' => (int) $bank->published_count,
                'draft_questions' => (int) $bank->draft_count,
                'link' => '/lms/question-banks/'.$bank->id,
            ]),
            'breakdown' => $detail,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'grade_level_id' => $schema->integer()->description('Narrow subjects to those taught in this grade.'),
            'question_bank_id' => $schema->integer()->description('Optional: one platform bank\'s breakdown by topic/type/difficulty.'),
        ];
    }
}
