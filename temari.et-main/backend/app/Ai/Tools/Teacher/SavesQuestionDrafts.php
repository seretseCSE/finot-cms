<?php

namespace App\Ai\Tools\Teacher;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Support\QuestionRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\Validation\ValidationException;

/**
 * Shared row parser for the teacher lane's write tools: model-drafted
 * question rows become PUBLISHED Question records in a bank (a bank question
 * is a reusable building block, not student-facing on its own — the exam that
 * uses it is what the teacher still reviews and publishes by hand). Incoherent
 * rows are skipped silently (the model sees what saved).
 */
trait SavesQuestionDrafts
{
    /**
     * The question-row array schema shared by the drafting tools. Every
     * nested array/object is FULLY typed — Gemini rejects function
     * declarations whose arrays carry no `items` with a 400.
     *
     * A row may be a PASSAGE GROUP (`type: "group"`): the stem is the
     * passage/introduction and `sub_questions` carries the questions answered
     * from it — saved as one container the exam engine keeps together.
     */
    protected function questionRowsSchema(JsonSchema $schema): ArrayType
    {
        $fields = $this->questionRowFields($schema);
        $fields['type'] = $schema->string()->description('mcq_single, true_false, short_answer, matching, or group (a reading passage/scenario with sub-questions).');
        $fields['sub_questions'] = $schema->array()->items($schema->object($this->questionRowFields($schema)))
            ->description('group only: the 2–10 questions answered from the passage, in order.');

        return $schema->array()->items($schema->object($fields));
    }

    /**
     * The fields of ONE question row (also the shape of a group's
     * sub-questions — which cannot nest another group).
     *
     * @return array<string, mixed>
     */
    private function questionRowFields(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('mcq_single, true_false, short_answer or matching.'),
            'stem' => $schema->string()->description('The question text (for a group: the full passage/introduction).'),
            'options' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('Option id: a, b, c or d.'),
                'text' => $schema->string(),
            ]))->description('mcq_single only: the 2–5 answer options.'),
            'correct' => $schema->string()->description('mcq_single: the correct option id, e.g. "a". true_false: "true" or "false".'),
            'accepted_answers' => $schema->array()->items($schema->string())->description('short_answer only: every accepted answer.'),
            'matching_pairs' => $schema->array()->items($schema->object([
                'left' => $schema->string()->description('The prompt item.'),
                'right' => $schema->string()->description('Its correct match.'),
            ]))->description('matching only: 2–10 CORRECTLY matched pairs — the app shuffles the right column for takers.'),
            'points' => $schema->number()->description('Points (default 1).'),
            'difficulty' => $schema->string()->description('easy, medium or hard.'),
            'topic' => $schema->string()->description('Chapter/topic label, e.g. "Chapter 1".'),
            'explanation' => $schema->string()->description('Why the answer is correct (shown to students on review).'),
        ];
    }

    /**
     * The paper-parts schema shared by the exam-creating tools: the studio's
     * "Part I — Multiple Choice / Part II — True-False" layout, each part
     * carrying its own questions. Fully typed for Gemini.
     */
    protected function partsSchema(JsonSchema $schema): ArrayType
    {
        return $schema->array()->items($schema->object([
            'title' => $schema->string()->description('Part heading WITHOUT any "Part N" prefix — the app numbers parts automatically ("Part I — …"). Pass "Multiple Choice", never "Part I — Multiple Choice".'),
            'instructions' => $schema->string()->description('Optional instructions for this part (plain text).'),
            'new_questions' => $this->questionRowsSchema($schema)->description('Generated questions for this part.'),
            'question_ids' => $schema->array()->items($schema->integer())->description('Existing question ids for this part.'),
        ]));
    }

    /**
     * Every surface renders part headings as "Part {roman} — {title}"
     * (studio, exam player, printed paper) — a stored title carrying its own
     * "Part N" prefix reads "Part I — Part I — Multiple Choice". Models love
     * adding the prefix regardless of instructions, so strip it here. An
     * empty result is fine: the paper then shows just "Part I".
     */
    protected function cleanPartTitle(string $title): string
    {
        return trim((string) preg_replace(
            '/^part\s+(?:[0-9]+|[ivxlcdm]+)\.?\s*(?:[—–:\/-]\s*|\b)/iu',
            '',
            trim($title),
        ));
    }

    /**
     * Normalize the two authoring shapes into ONE paper: either the flat
     * new_questions/question_ids fields (ungrouped), or parts[] where each
     * part carries its own questions. Returns a user-safe deny REASON string
     * when the shape is unusable.
     *
     * @param  array<string, mixed>  $input
     * @return array{parts: ?list<array{title: string, instructions: ?string}>, buckets: list<array{new: array<int, mixed>, existing: list<int>}>}|string
     */
    protected function paperFromInput(array $input): array|string
    {
        $rawParts = array_values(array_filter((array) ($input['parts'] ?? []), 'is_array'));

        if ($rawParts === []) {
            return [
                'parts' => null,
                'buckets' => [[
                    'new' => (array) ($input['new_questions'] ?? []),
                    'existing' => $this->idList($input['question_ids'] ?? null),
                ]],
            ];
        }

        if (count($rawParts) > 20) {
            return 'Too many parts — a paper carries at most 20.';
        }

        $parts = [];
        $buckets = [];

        foreach ($rawParts as $part) {
            $title = $this->cleanPartTitle((string) ($part['title'] ?? ''));
            $instructions = trim((string) ($part['instructions'] ?? ''));

            $parts[] = [
                'title' => mb_substr($title, 0, 200),
                'instructions' => $instructions === '' ? null : QuestionRules::sanitizeStem(mb_substr($instructions, 0, 10000)),
            ];
            $buckets[] = [
                'new' => (array) ($part['new_questions'] ?? []),
                'existing' => $this->idList($part['question_ids'] ?? null),
            ];
        }

        return ['parts' => $parts, 'buckets' => $buckets];
    }

    /** @return list<int> */
    protected function idList(mixed $ids): array
    {
        return collect((array) ($ids ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $questions
     * @param  list<int>  $groupIds  filled with the ids of saved passage groups
     * @return list<int> ids of the saved (published) questions, in input
     *                   order. For a passage group these are its SUB-QUESTION
     *                   ids — the ids an exam paper actually carries (the
     *                   container rides along via parent_id, like the studio).
     */
    protected function storeQuestionRows(QuestionBank $bank, array $questions, int $max = 20, array &$groupIds = []): array
    {
        $saved = [];

        foreach (array_slice($questions, 0, $max) as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['type'] ?? null) === QuestionType::Group->value) {
                $stem = trim((string) ($row['stem'] ?? ''));
                $subs = array_values(array_filter((array) ($row['sub_questions'] ?? []), 'is_array'));

                if ($stem === '' || $subs === []) {
                    continue;
                }

                $group = $this->createQuestion($bank, [
                    'type' => QuestionType::Group,
                    'body' => ['stem' => QuestionRules::sanitizeStem($stem)],
                    'key' => null,
                ], $row);

                $children = [];
                foreach (array_slice($subs, 0, 10) as $position => $sub) {
                    $parsed = $this->parseRow($sub);
                    if ($parsed === null) {
                        continue;
                    }

                    // Sub-questions inherit the passage's topic unless they
                    // name their own.
                    $sub['topic'] ??= $row['topic'] ?? null;
                    $children[] = $this->createQuestion($bank, $parsed, $sub, $group->id, $position + 1)->id;
                }

                if ($children === []) {
                    $group->forceDelete(); // A passage with no valid questions is noise.

                    continue;
                }

                $groupIds[] = $group->id;
                $saved = [...$saved, ...$children];

                continue;
            }

            $parsed = $this->parseRow($row);
            if ($parsed === null) {
                continue;
            }

            $saved[] = $this->createQuestion($bank, $parsed, $row)->id;
        }

        return $saved;
    }

    /**
     * Parse ONE primitive (non-group) row into a coherent type/body/key
     * triple, or null when the draft is unusable.
     *
     * @param  array<string, mixed>  $row
     * @return array{type: QuestionType, body: array<string, mixed>, key: ?array<string, mixed>}|null
     */
    private function parseRow(array $row): ?array
    {
        $type = QuestionType::tryFrom((string) ($row['type'] ?? ''));
        $stem = trim((string) ($row['stem'] ?? ''));

        if ($type === null || $stem === '') {
            return null;
        }

        $body = ['stem' => QuestionRules::sanitizeStem($stem)];
        $key = null;

        if ($type === QuestionType::McqSingle) {
            $options = collect($row['options'] ?? [])
                ->filter(fn ($o) => is_array($o) && trim((string) ($o['text'] ?? '')) !== '')
                ->map(fn (array $o): array => [
                    'id' => (string) ($o['id'] ?? ''),
                    'text' => QuestionRules::sanitizeStem((string) $o['text']),
                ])
                ->values()
                ->all();
            $body['options'] = $options;
            $key = ['correct' => (string) ($row['correct'] ?? '')];
        } elseif ($type === QuestionType::TrueFalse) {
            // `correct` arrives as a string over the tool wire — a plain
            // (bool) cast would turn "false" into true.
            $key = ['correct' => filter_var($row['correct'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        } elseif ($type === QuestionType::ShortAnswer) {
            $accepted = array_values(array_filter(array_map(
                fn ($a) => trim((string) $a),
                (array) ($row['accepted_answers'] ?? []),
            )));
            $key = ['accepted' => $accepted];
        } elseif ($type === QuestionType::Matching) {
            // The model sends correctly matched TEXT pairs; the server mints
            // the ids and shuffles the right column so takers never see the
            // aligned order. Model-minted ids proved too error-prone.
            $pairs = collect($row['matching_pairs'] ?? [])
                ->filter(fn ($p) => is_array($p)
                    && trim((string) ($p['left'] ?? '')) !== ''
                    && trim((string) ($p['right'] ?? '')) !== '')
                ->take(10)
                ->values();

            if ($pairs->count() < 2) {
                return null;
            }

            $body['left'] = $pairs->map(fn (array $p, int $i): array => [
                'id' => 'l'.($i + 1),
                'text' => QuestionRules::sanitizeInline(trim((string) $p['left'])),
            ])->all();
            $rightIds = collect(range(1, $pairs->count()))->shuffle();
            $body['right'] = $rightIds->map(fn (int $n): array => [
                'id' => 'r'.$n,
                'text' => QuestionRules::sanitizeInline(trim((string) $pairs[$n - 1]['right'])),
            ])->values()->all();
            $key = ['pairs' => $pairs->mapWithKeys(fn (array $p, int $i): array => ['l'.($i + 1) => 'r'.($i + 1)])->all()];
        } else {
            return null; // Other types stay studio-only for now.
        }

        try {
            QuestionRules::assertCoherent($type, $body, $key);
        } catch (ValidationException) {
            return null; // Skip incoherent drafts silently — the model sees what saved.
        }

        return ['type' => $type, 'body' => $body, 'key' => $key];
    }

    /**
     * @param  array{type: QuestionType, body: array<string, mixed>, key: ?array<string, mixed>}  $parsed
     * @param  array<string, mixed>  $row
     */
    private function createQuestion(QuestionBank $bank, array $parsed, array $row, ?int $parentId = null, ?int $position = null): Question
    {
        $question = Question::create([
            'question_bank_id' => $bank->id,
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $parsed['type']->value,
            'body' => $parsed['body'],
            'answer_key' => $parsed['key'],
            'points' => is_numeric($row['points'] ?? null) ? (float) $row['points'] : 1,
            'difficulty' => in_array($row['difficulty'] ?? null, ['easy', 'medium', 'hard'], true) ? $row['difficulty'] : null,
            'topic' => isset($row['topic']) ? mb_substr((string) $row['topic'], 0, 120) : null,
            'explanation' => isset($row['explanation']) ? mb_substr((string) $row['explanation'], 0, 2000) : null,
            'source' => 'Temari AI draft',
            'status' => 'published',
            'created_by' => $this->context->user->id,
        ]);

        $bank->rememberTopic($question->topic);

        return $question;
    }
}
