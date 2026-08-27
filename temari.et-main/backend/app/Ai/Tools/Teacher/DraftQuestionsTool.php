<?php

namespace App\Ai\Tools\Teacher;

use App\Models\QuestionBank;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Write tool of the teacher lane: save AI-generated questions into a
 * question bank. Requires lms.manage_own (or lms.manage) in scope; questions
 * land PUBLISHED (ready to drop onto an exam) with the teacher as creator, in
 * the LMS question-bank studio — a bank question is a reusable building block,
 * so the review gate that matters is the exam, which is never auto-published.
 */
class DraftQuestionsTool extends TeacherScopedTool
{
    use SavesQuestionDrafts;

    public function description(): Stringable|string
    {
        return 'Save generated quiz questions into one of the school\'s question banks (a new bank is created when bank_name is given). Supported types: mcq_single (options a–d + correct id), true_false, short_answer, matching (matching_pairs of correctly matched left/right texts), and group — a reading passage or scenario whose stem holds the FULL passage text and whose sub_questions (2–10) are answered from it; the app keeps a group together on every exam. Questions are saved PUBLISHED so they can be dropped straight onto an exam. Use it when the user wants questions in a bank, or to add new questions to an EXISTING exam — save here, then pass the ids to UpdateExamTool\'s add_question_ids.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->context->allows('lms.manage_own') && ! $this->context->allows('lms.manage')) {
            return $this->deny('You do not have question-bank access in this context.');
        }

        if ($this->context->branchId() === null && ! $this->context->allows('lms.manage')) {
            return $this->deny('Open the AI in a branch workspace to save questions.');
        }

        $questions = $request->all()['questions'] ?? [];

        if (! is_array($questions) || $questions === [] || count($questions) > 20) {
            return $this->deny('Pass 1–20 questions.');
        }

        $bankId = (int) ($request->all()['question_bank_id'] ?? 0);
        $bankName = trim((string) ($request->all()['bank_name'] ?? ''));

        $bank = $bankId > 0
            ? QuestionBank::query()
                ->where('id', $bankId)
                ->where('school_id', $this->context->schoolId())
                ->when($this->context->branchId() !== null, fn ($q) => $q->where('branch_id', $this->context->branchId()))
                ->first()
            : null;

        if ($bank === null && $bankName === '') {
            return $this->deny('Pass question_bank_id or bank_name.');
        }

        return DB::transaction(function () use ($bank, $bankName, $questions): string {
            $bank ??= QuestionBank::create([
                'school_id' => $this->context->schoolId(),
                'branch_id' => $this->context->branchId(),
                'name' => mb_substr($bankName, 0, 120),
                'description' => 'Created with Temari AI.',
                'is_active' => true,
                'created_by' => $this->context->user->id,
            ]);

            $groupIds = [];
            $saved = $this->storeQuestionRows($bank, $questions, groupIds: $groupIds);

            if ($saved === []) {
                return $this->deny('No questions were valid enough to save — check option ids and correct answers.');
            }

            return $this->ok([
                'question_bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'saved_question_ids' => $saved,
                'passage_group_ids' => $groupIds === [] ? null : $groupIds,
                'status' => 'published',
                'review_link' => '/lms/question-banks/'.$bank->id,
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question_bank_id' => $schema->integer()->description('Existing bank to add to (yours).'),
            'bank_name' => $schema->string()->description('Or a name for a new bank, e.g. "AI drafts — Physics G9".'),
            'questions' => $this->questionRowsSchema($schema)->description('The questions to save as drafts.')->required(),
        ];
    }
}
