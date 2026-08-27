<?php

namespace App\Ai\Tools\Platform;

use App\Ai\Tools\AiTool;
use App\Ai\Tools\Teacher\SavesQuestionDrafts;
use App\Enums\QuestionType;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Subject;
use App\Support\QuestionRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The platform lane's exam-prep write tool (exam_prep.manage): assemble a
 * DRAFT platform mock/practice paper in one call — is_platform, no school,
 * open to any registered user once a HUMAN publishes it in the exam studio.
 * Newly generated questions land PUBLISHED in a PLATFORM bank (school_id
 * null, the national bank); existing platform-bank questions can be mixed
 * in. The paper itself always stays a draft — the AI never publishes.
 */
class CreateMockExamTool extends AiTool
{
    use SavesQuestionDrafts;

    public function description(): Stringable|string
    {
        return 'Create a DRAFT platform mock/practice exam for the whole platform (AI Exam Prep): pass subject_id + grade_level_id (from ExamPrepCatalogTool), a title, the prep identity (exam_kind: national_past/mock/practice, optional exam_year_ec + stream), and the questions — new_questions (saved PUBLISHED into a platform bank via question_bank_id or bank_name) and/or question_ids (existing platform-bank questions). Call it as soon as the shape is agreed — the paper saves as a DRAFT invisible to learners, reviewed in the chat preview card and published only with the user\'s explicit confirmation (UpdateExamTool) or by hand in the studio.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->context->allows('exam_prep.manage')) {
            return $this->deny('Exam-prep authoring is for Temari.et content staff only.');
        }

        $input = $request->all();

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return $this->deny('Pass a title for the mock exam.');
        }

        $subject = Subject::query()
            ->whereNull('school_id')
            ->find((int) ($input['subject_id'] ?? 0));
        $grade = GradeLevel::query()->find((int) ($input['grade_level_id'] ?? 0));

        if ($subject === null || $grade === null) {
            return $this->deny('Pass subject_id and grade_level_id from the exam-prep catalog.');
        }

        $examKind = in_array($input['exam_kind'] ?? null, Quiz::EXAM_KINDS, true) ? $input['exam_kind'] : 'mock';
        $stream = in_array($input['stream'] ?? null, Quiz::STREAMS, true) ? $input['stream'] : null;
        $yearEc = is_numeric($input['exam_year_ec'] ?? null) ? (int) $input['exam_year_ec'] : null;
        if ($yearEc !== null && ($yearEc < 1980 || $yearEc > 2100)) {
            $yearEc = null;
        }

        $paper = $this->paperFromInput($input);
        if (is_string($paper)) {
            return $this->deny($paper);
        }

        $buckets = $paper['buckets'];
        $newQuestions = array_merge(...array_column($buckets, 'new'));
        $existingIds = collect(array_merge(...array_column($buckets, 'existing')))->unique()->values();

        if ($newQuestions === [] && $existingIds->isEmpty()) {
            return $this->deny('Pass new_questions and/or question_ids (flat, or inside parts) — an exam needs questions.');
        }

        if (count($newQuestions) > 30 || $existingIds->count() > 100) {
            return $this->deny('Too many questions in one call — up to 30 new and 100 existing.');
        }

        // Existing picks must live in PLATFORM banks — never a school's bank.
        $existing = $existingIds->isEmpty()
            ? collect()
            : Question::query()
                ->whereIn('id', $existingIds)
                ->whereIn('status', ['published', 'draft'])
                ->with(['children' => fn ($q) => $q->where('status', 'published')])
                ->whereHas('bank', fn ($q) => $q->whereNull('school_id'))
                ->get()
                ->keyBy('id');

        if ($existingIds->isNotEmpty() && $existing->count() !== $existingIds->count()) {
            return $this->deny('Some question_ids are not in a platform question bank — re-check with the exam-prep catalog.');
        }

        // A picked passage GROUP expands to its published sub-questions —
        // the container itself never sits a paper (same rule as the studio).
        if ($existing->contains(fn (Question $q): bool => $q->type === QuestionType::Group && $q->children->isEmpty())) {
            return $this->deny('One of the picked passage groups has no published sub-questions — add them in the question bank first.');
        }

        foreach ($buckets as $i => $bucket) {
            $buckets[$i]['existing'] = collect($bucket['existing'])
                ->flatMap(function (int $id) use ($existing): array {
                    $question = $existing->get($id);

                    return $question?->type === QuestionType::Group
                        ? $question->children->pluck('id')->all()
                        : [$id];
                })
                ->unique()
                ->values()
                ->all();
        }

        $bankId = (int) ($input['question_bank_id'] ?? 0);
        $bankName = trim((string) ($input['bank_name'] ?? ''));

        $bank = null;
        if ($newQuestions !== []) {
            $bank = $bankId > 0
                ? QuestionBank::query()->whereNull('school_id')->find($bankId)
                : null;

            if ($bank === null && $bankName === '') {
                $bankName = $title.' — AI drafts';
            }
        }

        $duration = is_numeric($input['duration_minutes'] ?? null)
            ? max(0, min(600, (int) $input['duration_minutes']))
            : null;

        $instructions = trim((string) ($input['instructions'] ?? ''));
        $language = in_array($input['language'] ?? null, ['en', 'am', 'om'], true) ? $input['language'] : 'en';

        return DB::transaction(function () use ($subject, $grade, $title, $examKind, $stream, $yearEc, $duration, $instructions, $language, $bank, $bankName, $newQuestions, $paper, $buckets): string {
            $bank ??= $newQuestions === [] ? null : QuestionBank::create([
                'school_id' => null,
                'branch_id' => null,
                'name' => mb_substr($bankName, 0, 120),
                'description' => 'Created with Temari AI.',
                'subject_id' => $subject->id,
                'grade_level_id' => $grade->id,
                'is_active' => true,
                'created_by' => $this->context->user->id,
            ]);

            $savedPerBucket = [];
            $total = 0;

            foreach ($buckets as $bucket) {
                $ids = $bank === null || $bucket['new'] === [] ? [] : $this->storeQuestionRows($bank, $bucket['new'], 30);
                $savedPerBucket[] = $ids;
                $total += count($ids) + count($bucket['existing']);
            }

            if ($total === 0) {
                return $this->deny('No questions were valid enough to save — check option ids and correct answers.');
            }

            $quiz = Quiz::create([
                'is_platform' => true,
                'school_id' => null,
                'branch_id' => null,
                'subject_assignment_id' => null,
                'kind' => 'mock',
                'title' => mb_substr($title, 0, 255),
                'instructions' => $instructions === '' ? null : QuestionRules::sanitizeStem(mb_substr($instructions, 0, 10000)),
                'subject_id' => $subject->id,
                'grade_level_id' => $grade->id,
                'exam_kind' => $examKind,
                'exam_year_ec' => $yearEc,
                'stream' => $stream,
                'language' => $language,
                // Self-study defaults: unlimited retakes, instant results.
                'settings' => array_filter([
                    'duration_minutes' => $duration,
                    'attempts_allowed' => 0,
                    'results_policy' => 'immediately',
                    'reveal_answers' => true,
                    'navigation' => 'free',
                ], fn ($v) => $v !== null),
                'parts' => $paper['parts'],
                'created_by' => $this->context->user->id,
            ]);

            $sort = 0;
            foreach ($buckets as $index => $bucket) {
                foreach ([...$bucket['existing'], ...$savedPerBucket[$index]] as $questionId) {
                    $quiz->quizQuestions()->create([
                        'question_id' => $questionId,
                        'points' => null,
                        'sort_order' => $sort++,
                        'part_index' => $paper['parts'] === null ? null : $index,
                    ]);
                }
            }

            return $this->ok([
                'quiz_id' => $quiz->id,
                'title' => $quiz->title,
                'exam_kind' => $quiz->exam_kind,
                'subject' => $subject->name,
                'grade' => $grade->name,
                'status' => 'draft',
                'question_count' => $sort,
                'parts' => $paper['parts'] === null ? null : array_column($paper['parts'], 'title'),
                'new_question_ids' => array_merge(...$savedPerBucket),
                'question_bank_id' => $bank?->id,
                'bank_link' => $bank !== null ? '/lms/question-banks/'.$bank->id : null,
                'link' => '/lms/exams/'.$quiz->id,
                'note' => 'The paper is a DRAFT — no learner can see it yet. It can be published from this chat once the user explicitly confirms, or by hand in the exam studio.',
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Paper title, e.g. "EUEE Mathematics Mock — 2016 E.C.".')->required(),
            'subject_id' => $schema->integer()->description('Platform subject id from ExamPrepCatalogTool.')->required(),
            'grade_level_id' => $schema->integer()->description('Grade level id from ExamPrepCatalogTool (national exam grades: 6, 8 and 12).')->required(),
            'exam_kind' => $schema->string()->description('national_past, mock or practice (default mock).'),
            'exam_year_ec' => $schema->integer()->description('Ethiopian year of the source paper (national_past), e.g. 2016.'),
            'stream' => $schema->string()->description('natural or social — Grade 12 papers only.'),
            'language' => $schema->string()->description('Paper language: en, am or om (default en).'),
            'duration_minutes' => $schema->integer()->description('Time limit in minutes (omit for none).'),
            'instructions' => $schema->string()->description('Short instructions shown to takers (plain text).'),
            'new_questions' => $this->questionRowsSchema($schema)->description('Generated questions to save into a platform bank alongside the paper.'),
            'question_bank_id' => $schema->integer()->description('Existing PLATFORM bank for the new questions.'),
            'bank_name' => $schema->string()->description('Or a name for a new platform bank for them.'),
            'question_ids' => $schema->array()->items($schema->integer())->description('Existing question ids from platform banks (see the exam-prep catalog breakdowns) to include as-is.'),
            'parts' => $this->partsSchema($schema)->description('Grouped papers: each part carries its own new_questions and/or question_ids plus optional part instructions. Use INSTEAD of the top-level question fields when the paper should be grouped.'),
        ];
    }
}
