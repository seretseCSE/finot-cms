<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Support\QuestionRules;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Questions inside a bank (ADR-016). Adding to a bank follows the BANK's
 * update policy (can you shape this bank at all); editing/deleting an
 * existing question follows QuestionPolicy — the question's own creator,
 * or a supervisory role. Bodies and answer keys are deep-validated per type
 * via QuestionRules so malformed questions never enter the pool. Questions
 * referenced by any quiz or attempt retire instead of deleting.
 */
class QuestionController extends Controller
{
    public function index(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $this->authorize('view', $questionBank);

        $questions = $questionBank->questions()
            ->with('creator:id,name')
            ->withCount('children')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('difficulty'), fn ($q) => $q->where('difficulty', $request->string('difficulty')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('topic'), fn ($q) => $q->where('topic', $request->string('topic')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('body->stem', 'ilike', SearchTerm::contains($n))))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        // Every row belongs to the already-loaded bank — reuse it instead of
        // one lazy `->bank` query per row (QuestionPolicy reads bank scope).
        $questions->getCollection()->each(fn (Question $question) => $question->setRelation('bank', $questionBank));

        return QuestionResource::collection($questions)->response();
    }

    /**
     * Cross-bank browse for the exam paper picker: one request for however
     * many banks a quiz draws from, instead of N. Banks the caller can't
     * view are silently dropped (never leaked) rather than 403'd, since a
     * mixed selection of authorized + unauthorized ids is a normal shape.
     */
    public function indexMany(Request $request): JsonResponse
    {
        $request->validate([
            'question_bank_id' => ['required', 'array', 'min:1', 'max:20'],
            'question_bank_id.*' => ['integer', 'exists:question_banks,id'],
        ]);

        $user = $request->user();
        $requestedIds = collect($request->input('question_bank_id'))->map(fn ($id) => (int) $id)->unique();

        $authorizedBankIds = QuestionBank::whereIn('id', $requestedIds)
            ->get()
            ->filter(fn (QuestionBank $bank) => $user->can('view', $bank))
            ->pluck('id');

        $questions = Question::whereIn('question_bank_id', $authorizedBankIds)
            ->with(['creator:id,name', 'bank:id,name,school_id,branch_id'])
            ->withCount('children')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('difficulty'), fn ($q) => $q->where('difficulty', $request->string('difficulty')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('topic'), fn ($q) => $q->where('topic', $request->string('topic')))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('body->stem', 'ilike', SearchTerm::contains($n))))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 100), 200));

        return QuestionResource::collection($questions)->response();
    }

    public function store(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $this->authorize('update', $questionBank);

        $data = $request->validate(QuestionRules::base());
        // validated() prunes nested array keys it has no rules for — the body
        // and key are free-shaped by design, so take them raw and deep-check.
        $data['body'] = QuestionRules::normalizeBody((array) $request->input('body'));
        $data['answer_key'] = $request->input('answer_key');
        $type = QuestionType::from($data['type']);
        QuestionRules::assertCoherent($type, $data['body'], $data['answer_key'] ?? null);

        $parent = $this->resolveParent($request, $questionBank, $type);

        $question = $questionBank->questions()->create([
            ...$data,
            'parent_id' => $parent?->id,
            'position' => $parent === null ? null : ((int) $parent->children()->max('position')) + 1,
            'points' => $data['points'] ?? 1,
            'status' => $data['status'] ?? 'published',
            'created_by' => $request->user()->id,
        ]);

        $questionBank->rememberTopic($data['topic'] ?? null);

        return (new QuestionResource($question))
            ->additional(['message' => 'Question added.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Question $question): QuestionResource
    {
        $this->authorize('update', $question);

        $data = $request->validate(QuestionRules::base());
        $data['body'] = QuestionRules::normalizeBody((array) $request->input('body'));
        $data['answer_key'] = $request->input('answer_key');
        $type = QuestionType::from($data['type']);
        QuestionRules::assertCoherent($type, $data['body'], $data['answer_key'] ?? null);

        if ($request->has('parent_id')) {
            $parent = $this->resolveParent($request, $question->bank, $type, $question);
            $data['parent_id'] = $parent?->id;
            if ($parent !== null && $question->parent_id !== $parent->id) {
                $data['position'] = ((int) $parent->children()->max('position')) + 1;
            }
        }

        if ($question->type === QuestionType::Group && $type !== QuestionType::Group && $question->children()->exists()) {
            throw ValidationException::withMessages([
                'type' => ['This group still has sub-questions — remove them before changing its type.'],
            ]);
        }

        $question->update($data);
        $question->bank->rememberTopic($data['topic'] ?? null);

        return new QuestionResource($question);
    }

    /**
     * Reorder a passage group's sub-questions (the passage editor's
     * group-by-type and up/down moves). The id list must be exactly the
     * group's children; positions are rewritten 1..n in the given order —
     * every surface (bank, exam paper, player, PDF) reads this order.
     */
    public function reorder(Request $request, Question $question): JsonResponse
    {
        $this->authorize('update', $question);

        if ($question->type !== QuestionType::Group) {
            throw ValidationException::withMessages([
                'question' => ['Only a question group (passage) can be reordered.'],
            ]);
        }

        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1', 'max:50'],
            'question_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map(intval(...), $data['question_ids'])));
        $childIds = $question->children()->pluck('id')->all();

        if (count($ids) !== count($childIds) || array_diff($ids, $childIds) !== []) {
            throw ValidationException::withMessages([
                'question_ids' => ['Pass every question of this passage exactly once.'],
            ]);
        }

        foreach ($ids as $index => $id) {
            Question::query()->whereKey($id)->update(['position' => $index + 1]);
        }

        return QuestionResource::collection($question->children()->get())->response();
    }

    /**
     * Toggle a single question between draft and published from the bank
     * table — the body never changes, so this skips the full coherence
     * re-validation of update(). Publishing re-asserts the question is
     * coherent (a draft can be half-finished); un-publishing is always safe.
     */
    public function setStatus(Request $request, Question $question): QuestionResource
    {
        $this->authorize('update', $question);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,published'],
        ]);

        if ($data['status'] === 'published') {
            QuestionRules::assertCoherent($question->type, (array) $question->body, $question->answer_key);
        }

        $question->update(['status' => $data['status']]);

        return new QuestionResource($question);
    }

    /**
     * Media for the question being authored (stem images, reference files).
     * Stored on R2; the returned `path` goes into `body.attachments` (or the
     * stem's <img src> via the returned signed `url`).
     */
    public function upload(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $this->authorize('update', $questionBank);

        $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,gif,pdf,mp3,mp4,doc,docx,ppt,pptx,xls,xlsx'],
        ]);

        $file = $request->file('file');
        $path = $file->store('lms/question-media', ['disk' => config('filesystems.default')]);

        return response()->json(['data' => [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'url' => s3Url($path),
        ]], 201);
    }

    public function destroy(Question $question): JsonResponse
    {
        $this->authorize('delete', $question);

        // Deleting a group takes its sub-questions with it — each child
        // follows the same retire-if-referenced rule as any question.
        $family = $question->type === QuestionType::Group
            ? $question->children()->get()->push($question)
            : collect([$question]);

        $anyReferenced = $family->contains(fn (Question $member): bool => $member->isReferenced());

        if ($anyReferenced) {
            foreach ($family as $member) {
                $member->update(['status' => 'retired']);
            }

            return response()->json(['message' => 'This question is used in exams — it was retired instead of deleted.']);
        }

        foreach ($family as $member) {
            $member->delete();
        }

        return response()->json(['message' => 'Question deleted.']);
    }

    /**
     * Validate an optional `parent_id`: the parent must be a GROUP in the
     * same bank, and a group can never nest inside another group.
     */
    private function resolveParent(Request $request, QuestionBank $questionBank, QuestionType $type, ?Question $current = null): ?Question
    {
        $parentId = $request->input('parent_id');

        if ($parentId === null || $parentId === '') {
            return null;
        }

        /** @var ?Question $parent */
        $parent = Question::query()->find((int) $parentId);

        if ($parent === null
            || $parent->question_bank_id !== $questionBank->id
            || $parent->type !== QuestionType::Group
            || ($current !== null && $parent->id === $current->id)) {
            throw ValidationException::withMessages([
                'parent_id' => ['Sub-questions must belong to a question group in the same bank.'],
            ]);
        }

        if ($type === QuestionType::Group) {
            throw ValidationException::withMessages([
                'parent_id' => ['A question group cannot sit inside another group.'],
            ]);
        }

        return $parent;
    }

    /**
     * Bulk import (national bank curation + school papers): an array of
     * question payloads, all-or-nothing.
     */
    public function bulkStore(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $this->authorize('update', $questionBank);

        $request->validate([
            'questions' => ['required', 'array', 'min:1', 'max:200'],
        ]);

        $rules = collect(QuestionRules::base())
            ->mapWithKeys(fn ($rule, $field) => ["questions.*.{$field}" => $rule])
            ->all();
        $request->validate($rules);

        $userId = $request->user()->id;

        $created = DB::transaction(function () use ($request, $questionBank, $userId): int {
            $count = 0;

            foreach ($request->input('questions') as $payload) {
                $type = QuestionType::from($payload['type']);
                $payload['body'] = QuestionRules::normalizeBody((array) ($payload['body'] ?? []));
                QuestionRules::assertCoherent($type, $payload['body'], $payload['answer_key'] ?? null);

                $questionBank->questions()->create([
                    ...collect($payload)->only([
                        'type', 'body', 'answer_key', 'points', 'difficulty',
                        'topic', 'tags', 'source', 'explanation', 'status',
                    ])->all(),
                    'points' => $payload['points'] ?? 1,
                    'status' => $payload['status'] ?? 'published',
                    'created_by' => $userId,
                ]);
                $questionBank->rememberTopic($payload['topic'] ?? null);
                $count++;
            }

            return $count;
        });

        return response()->json(['message' => "{$created} questions imported.", 'meta' => ['count' => $created]], 201);
    }
}
