<?php

namespace App\Ai\Tools\Teacher;

use App\Ai\Tools\AiTool;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\Lms\QuizPublisher;
use App\Support\QuestionRules;
use App\Support\TermGate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The exam-builder's editing tool — everything the exam studio can do to an
 * EXISTING paper, from chat: inspect the paper (quiz_id alone), retitle,
 * change instructions/timing/settings, REGROUP into parts ("Part I —
 * Multiple Choice"…), reorder, add/remove bank questions, and publish/close.
 * Authority comes from QuizPolicy (authorship or lms.manage / exam_prep.
 * manage — judged in the QUIZ's own scope) plus the conversation's frozen
 * context; the studio's rules apply unchanged: TermGate, layout frozen once
 * someone sat the paper, publish through the one QuizPublisher path.
 * Publishing and closing additionally require the USER's explicit
 * confirmation in chat, relayed via confirmed=true. Denies mid-edit throw,
 * so a refused call never half-applies.
 */
class UpdateExamTool extends AiTool
{
    use SavesQuestionDrafts;

    public function description(): Stringable|string
    {
        return 'Inspect or edit an existing exam/quiz (pass quiz_id; with no other fields it returns the paper: questions with ids/types, parts, settings, status). Edits: title, instructions, duration/settings, parts (regroup — each part lists its question_ids from THIS paper), order (reorder ungrouped papers), add_question_ids/remove_question_ids (a passage-group id expands to its sub-questions), clear_parts. set_status publish|close makes the exam live/closed — ONLY with confirmed=true after the user explicitly confirmed in chat. Question layout freezes once someone has taken the exam. To add newly generated questions, save them with DraftQuestionsTool first, then add their ids.';
    }

    public function handle(Request $request): Stringable|string
    {
        $input = $request->all();

        $quiz = Quiz::query()
            ->with('subjectAssignment.term')
            ->find((int) ($input['quiz_id'] ?? 0));

        if ($quiz === null || ! $this->inScope($quiz)) {
            return $this->deny('That exam is not available in this workspace — pass the quiz_id of an exam from this conversation or its exam-list tools.');
        }

        $user = $this->context->user;

        $partsInput = array_values(array_filter((array) ($input['parts'] ?? []), 'is_array'));
        $order = $this->idList($input['order'] ?? null);
        $addIds = $this->idList($input['add_question_ids'] ?? null);
        $removeIds = $this->idList($input['remove_question_ids'] ?? null);
        $clearParts = filter_var($input['clear_parts'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $structural = $partsInput !== [] || $order !== [] || $addIds !== [] || $removeIds !== [] || $clearParts;

        $meta = array_intersect_key($input, array_flip([
            'title', 'instructions', 'duration_minutes', 'attempts_allowed', 'results_policy',
            'navigation', 'shuffle_questions', 'shuffle_options', 'reveal_answers',
        ]));

        $action = (string) ($input['set_status'] ?? '');

        // Pure inspection: no edits requested.
        if (! $structural && $meta === [] && $action === '') {
            if (! $user->can('view', $quiz)) {
                return $this->deny('You cannot see this exam.');
            }

            return $this->ok($this->describePaper($quiz));
        }

        if (($structural || $meta !== []) && ! $user->can('update', $quiz)) {
            return $this->deny('You cannot edit this exam — only its author or a supervisor can change the paper.');
        }

        if ($action !== '') {
            if (! in_array($action, ['publish', 'close'], true)) {
                return $this->deny('set_status accepts publish or close.');
            }

            if (! filter_var($input['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return $this->deny('Publishing or closing needs the user\'s explicit confirmation — ask them in chat first, then call again with confirmed set to true.');
            }

            if (! $user->can('manage', $quiz)) {
                return $this->deny('You cannot publish or close this exam in this context.');
            }
        }

        if ($quiz->subjectAssignment !== null) {
            try {
                TermGate::assertWritable($quiz->subjectAssignment->term);
            } catch (HttpException $e) {
                return $this->deny($e->getMessage());
            }
        }

        if ($structural && $quiz->attempts()->exists()) {
            return $this->deny('Someone has already taken this exam — its questions and layout are frozen. Title, instructions, settings and closing are still possible.');
        }

        try {
            return DB::transaction(function () use ($quiz, $meta, $partsInput, $order, $addIds, $removeIds, $clearParts, $action): string {
                $changes = [];

                $this->applyMeta($quiz, $meta, $changes);

                if ($addIds !== []) {
                    $this->addQuestions($quiz, $addIds, $changes);
                }

                if ($removeIds !== []) {
                    $quiz->quizQuestions()->whereIn('question_id', $removeIds)->delete();
                    $changes[] = 'removed '.count($removeIds).' question(s)';
                }

                if ($clearParts && $partsInput === []) {
                    $quiz->quizQuestions()->update(['part_index' => null]);
                    $quiz->parts = null;
                    $changes[] = 'removed the part grouping';
                }

                if ($partsInput !== []) {
                    $this->regroup($quiz, $partsInput, $changes);
                }

                if ($order !== [] && $partsInput === []) {
                    $this->reorder($quiz, $order, $changes);
                }

                $quiz->save();

                if ($action !== '') {
                    $action === 'publish'
                        ? app(QuizPublisher::class)->publish($quiz)
                        : app(QuizPublisher::class)->close($quiz);

                    $changes[] = $action === 'publish' ? 'PUBLISHED the exam' : 'CLOSED the exam';
                }

                if ($changes === []) {
                    throw new RuntimeException('Nothing to change — pass the fields to update, or only quiz_id to read the paper.');
                }

                return $this->ok([
                    'changes' => $changes,
                    ...$this->describePaper($quiz->refresh()),
                ]);
            });
        } catch (ValidationException $e) {
            return $this->deny((string) collect($e->errors())->flatten()->first());
        } catch (RuntimeException $e) {
            return $this->deny($e->getMessage());
        }
    }

    /** The conversation's frozen scope must contain the quiz (never widen). */
    private function inScope(Quiz $quiz): bool
    {
        if ($quiz->is_platform) {
            return $this->context->schoolId() === null;
        }

        return $quiz->school_id === $this->context->schoolId()
            && ($this->context->branchId() === null || $quiz->branch_id === $this->context->branchId());
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>  $changes
     */
    private function applyMeta(Quiz $quiz, array $meta, array &$changes): void
    {
        if (isset($meta['title']) && trim((string) $meta['title']) !== '') {
            $quiz->title = mb_substr(trim((string) $meta['title']), 0, 255);
            $changes[] = 'title';
        }

        if (array_key_exists('instructions', $meta)) {
            $text = trim((string) $meta['instructions']);
            $quiz->instructions = $text === '' ? null : QuestionRules::sanitizeStem(mb_substr($text, 0, 10000));
            $changes[] = 'instructions';
        }

        $settings = $quiz->settings ?? [];

        if (is_numeric($meta['duration_minutes'] ?? null)) {
            $settings['duration_minutes'] = max(0, min(600, (int) $meta['duration_minutes']));
            $changes[] = 'time limit';
        }

        if (is_numeric($meta['attempts_allowed'] ?? null)) {
            $settings['attempts_allowed'] = max(0, min(10, (int) $meta['attempts_allowed']));
            $changes[] = 'attempts allowed';
        }

        if (in_array($meta['results_policy'] ?? null, ['immediately', 'after_close', 'manual'], true)) {
            $settings['results_policy'] = $meta['results_policy'];
            $changes[] = 'results policy';
        }

        if (in_array($meta['navigation'] ?? null, ['free', 'sequential'], true)) {
            $settings['navigation'] = $meta['navigation'];
            $changes[] = 'navigation';
        }

        foreach (['shuffle_questions', 'shuffle_options', 'reveal_answers'] as $flag) {
            if (array_key_exists($flag, $meta) && $meta[$flag] !== null && $meta[$flag] !== '') {
                $settings[$flag] = filter_var($meta[$flag], FILTER_VALIDATE_BOOLEAN);
                $changes[] = str_replace('_', ' ', $flag);
            }
        }

        $quiz->settings = $settings;
    }

    /**
     * Append existing bank questions (same scope rules as CreateExamTool /
     * CreateMockExamTool: the school's own banks, or platform banks for
     * platform papers). A passage-group id expands to its published
     * sub-questions — the container itself never sits a paper.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $changes
     */
    private function addQuestions(Quiz $quiz, array $ids, array &$changes): void
    {
        $questions = Question::query()
            ->whereIn('id', $ids)
            ->whereIn('status', ['published', 'draft'])
            ->with(['children' => fn ($q) => $q->where('status', 'published')])
            ->whereHas('bank', fn ($q) => $quiz->is_platform
                ? $q->whereNull('school_id')
                : $q->where('school_id', $quiz->school_id)
                    ->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $quiz->branch_id)))
            ->get()
            ->keyBy('id');

        if ($questions->count() !== count($ids)) {
            throw new RuntimeException('Some question ids to add are not available in the banks this exam can use.');
        }

        if ($questions->contains(fn (Question $q): bool => $q->type === QuestionType::Group && $q->children->isEmpty())) {
            throw new RuntimeException('One of the picked passage groups has no published sub-questions — add them in the question bank first.');
        }

        $resolved = collect($ids)
            ->flatMap(function (int $id) use ($questions): array {
                $question = $questions->get($id);

                return $question?->type === QuestionType::Group
                    ? $question->children->pluck('id')->all()
                    : [$id];
            })
            ->unique()
            ->values();

        $onPaper = $quiz->quizQuestions()->pluck('question_id')->all();
        $sort = ((int) $quiz->quizQuestions()->max('sort_order')) + 1;
        $added = 0;

        foreach ($resolved as $id) {
            if (in_array($id, $onPaper, true)) {
                continue;
            }

            $quiz->quizQuestions()->create([
                'question_id' => $id,
                'points' => null,
                'sort_order' => $sort++,
                'part_index' => null,
            ]);
            $added++;
        }

        $changes[] = 'added '.$added.' question(s)';
    }

    /**
     * Replace the paper's grouping AND order: parts in the given order, each
     * part's questions in the given order; paper questions not mentioned
     * follow at the end, ungrouped.
     *
     * @param  list<array<string, mixed>>  $partsInput
     * @param  list<string>  $changes
     */
    private function regroup(Quiz $quiz, array $partsInput, array &$changes): void
    {
        if (count($partsInput) > 20) {
            throw new RuntimeException('Too many parts — a paper carries at most 20.');
        }

        $rows = $quiz->quizQuestions()->orderBy('sort_order')->get();
        $paperIds = $rows->pluck('question_id')->all();

        $partsMeta = [];
        $assignment = [];

        foreach ($partsInput as $i => $part) {
            if ((array) ($part['new_questions'] ?? []) !== []) {
                throw new RuntimeException('This tool regroups EXISTING paper questions — save new ones with DraftQuestionsTool, add them via add_question_ids, then regroup by question_ids.');
            }

            $title = $this->cleanPartTitle((string) ($part['title'] ?? ''));
            $instructions = trim((string) ($part['instructions'] ?? ''));

            $partsMeta[] = [
                'title' => mb_substr($title, 0, 200),
                'instructions' => $instructions === '' ? null : QuestionRules::sanitizeStem(mb_substr($instructions, 0, 10000)),
            ];

            foreach ($this->idList($part['question_ids'] ?? null) as $qid) {
                if (! in_array($qid, $paperIds, true)) {
                    throw new RuntimeException('A question listed in the parts is not on this paper — read the paper first (call with only quiz_id) and use its question ids.');
                }
                $assignment[$qid] ??= $i;
            }
        }

        $byQuestion = $rows->groupBy('question_id');
        $sort = 0;

        foreach (array_keys($partsMeta) as $i) {
            foreach ($assignment as $qid => $part) {
                if ($part !== $i) {
                    continue;
                }
                foreach ($byQuestion->get($qid, collect()) as $row) {
                    $row->update(['sort_order' => $sort++, 'part_index' => $i]);
                }
            }
        }

        foreach ($rows as $row) {
            if (! array_key_exists($row->question_id, $assignment)) {
                $row->update(['sort_order' => $sort++, 'part_index' => null]);
            }
        }

        $quiz->parts = $partsMeta;
        $changes[] = 'regrouped into '.count($partsMeta).' part(s)';
    }

    /**
     * Reorder an UNGROUPED paper; unlisted questions keep their relative
     * order after the listed ones.
     *
     * @param  list<int>  $order
     * @param  list<string>  $changes
     */
    private function reorder(Quiz $quiz, array $order, array &$changes): void
    {
        if (is_array($quiz->parts) && $quiz->parts !== []) {
            throw new RuntimeException('This paper is grouped into parts — pass parts (each with its question_ids) to change the order.');
        }

        $rows = $quiz->quizQuestions()->orderBy('sort_order')->get();
        $byQuestion = $rows->groupBy('question_id');
        $sort = 0;

        foreach ($order as $qid) {
            foreach ($byQuestion->get($qid, collect()) as $row) {
                $row->update(['sort_order' => $sort++]);
            }
        }

        foreach ($rows as $row) {
            if (! in_array($row->question_id, $order, true)) {
                $row->update(['sort_order' => $sort++]);
            }
        }

        $changes[] = 'reordered the questions';
    }

    /**
     * The paper as the model needs it for follow-up edits.
     *
     * @return array<string, mixed>
     */
    private function describePaper(Quiz $quiz): array
    {
        $rows = $quiz->quizQuestions()->with('question:id,type,topic,points,status,body')->orderBy('sort_order')->get();

        return [
            'quiz_id' => $quiz->id,
            'title' => $quiz->title,
            'kind' => $quiz->kind,
            'status' => $quiz->status->value,
            'is_platform' => (bool) $quiz->is_platform,
            'settings' => $quiz->settings,
            'parts' => is_array($quiz->parts) && $quiz->parts !== [] ? array_column($quiz->parts, 'title') : null,
            'question_count' => $rows->count(),
            'attempts' => $quiz->attempts()->count(),
            'questions' => $rows->map(function ($qq): array {
                $question = $qq->question;

                return [
                    'question_id' => $qq->question_id,
                    'type' => $question?->type->value,
                    'topic' => $question?->topic,
                    'part_index' => $qq->part_index,
                    'points' => $qq->effectivePoints(),
                    'stem' => $question === null ? '' : mb_substr(trim(strip_tags((string) ($question->body['stem'] ?? ''))), 0, 90),
                ];
            }),
            'link' => '/lms/exams/'.$quiz->id,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'quiz_id' => $schema->integer()->description('The exam to inspect or edit (from CreateExamTool / the exam list). Alone = read the paper.')->required(),
            'title' => $schema->string()->description('New title.'),
            'instructions' => $schema->string()->description('New overall instructions (plain text; empty string clears).'),
            'duration_minutes' => $schema->integer()->description('Time limit in minutes (0 = none).'),
            'attempts_allowed' => $schema->integer()->description('Allowed attempts per student (0 = unlimited).'),
            'results_policy' => $schema->string()->description('immediately, after_close or manual.'),
            'navigation' => $schema->string()->description('free or sequential.'),
            'shuffle_questions' => $schema->string()->description('"true"/"false": shuffle question order per sitting.'),
            'shuffle_options' => $schema->string()->description('"true"/"false": shuffle answer options per sitting.'),
            'reveal_answers' => $schema->string()->description('"true"/"false": show correct answers with results.'),
            'parts' => $this->partsSchema($schema)->description('REGROUP the paper: parts in order, each listing its question_ids from THIS paper (read it first). Unlisted questions land ungrouped at the end. Replaces the previous grouping and order.'),
            'clear_parts' => $schema->string()->description('"true": remove the part grouping (questions keep their order).'),
            'order' => $schema->array()->items($schema->integer())->description('Ungrouped papers: the question_ids in the desired order.'),
            'add_question_ids' => $schema->array()->items($schema->integer())->description('Existing bank question ids to append (save generated questions with DraftQuestionsTool first).'),
            'remove_question_ids' => $schema->array()->items($schema->integer())->description('Question ids to take off the paper.'),
            'set_status' => $schema->string()->description('publish (make it live for its takers) or close (stop new attempts). Requires confirmed=true.'),
            'confirmed' => $schema->string()->description('"true" ONLY after the user explicitly confirmed the publish/close in chat.'),
        ];
    }
}
