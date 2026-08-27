<?php

namespace App\Ai\Tools\Teacher;

use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The school's question banks visible in this context (school-wide banks
 * plus this branch's), with question counts — what the exam builder uses to
 * offer "use existing questions" and to pick a bank for new drafts. Passing
 * question_bank_id returns that bank's topic/type/difficulty breakdown.
 */
class MyQuestionBanksTool extends TeacherScopedTool
{
    public function description(): Stringable|string
    {
        return 'List the question banks available here (id, name, subject, grade, topics, published/draft counts). Pass question_bank_id for one bank\'s breakdown by topic, type and difficulty — use it before assembling an exam from existing questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->context->allows('lms.manage_own') && ! $this->context->allows('lms.manage')) {
            return $this->deny('You do not have question-bank access in this context.');
        }

        $banks = QuestionBank::query()
            ->where('school_id', $this->context->schoolId())
            ->when($this->context->branchId() !== null, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $this->context->branchId()),
            ))
            ->where('is_active', true)
            ->with(['subject:id,name', 'gradeLevel:id,name'])
            ->withCount([
                'questions as published_count' => fn ($q) => $q->where('status', 'published'),
                'questions as draft_count' => fn ($q) => $q->where('status', 'draft'),
            ])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        if ($banks->isEmpty()) {
            return $this->ok(['banks' => [], 'note' => 'No question banks yet — one is created automatically when questions are drafted.']);
        }

        $bankId = (int) ($request->all()['question_bank_id'] ?? 0);
        $detail = null;

        if ($bankId > 0) {
            /** @var ?QuestionBank $bank */
            $bank = $banks->firstWhere('id', $bankId);

            if ($bank === null) {
                return $this->deny('That bank is not available in this context.');
            }

            $detail = [
                'question_bank_id' => $bank->id,
                'by_topic' => Question::query()
                    ->where('question_bank_id', $bank->id)
                    ->where('status', 'published')
                    ->selectRaw("coalesce(topic, '(no topic)') as topic, type, coalesce(difficulty, 'unset') as difficulty, count(*) as count")
                    ->groupBy('topic', 'type', 'difficulty')
                    ->orderBy('topic')
                    ->limit(120)
                    ->get(),
            ];
        }

        return $this->ok([
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
            'question_bank_id' => $schema->integer()->description('Optional: one bank\'s breakdown by topic/type/difficulty.'),
        ];
    }
}
