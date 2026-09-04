<?php

namespace App\Ai\Tools\Teacher;

use App\Enums\QuestionType;
use App\Enums\TermStatus;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\SubjectAssignment;
use App\Support\QuestionRules;
use App\Support\TermGate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Stringable;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The exam-builder's write tool: assemble a whole DRAFT exam in one call —
 * newly generated questions land PUBLISHED in a bank (reusable building
 * blocks), existing bank questions can be mixed in, and the quiz (kind
 * quiz/exam) anchors to subject assignments exactly like the studio would.
 * Teachers reach their OWN classes (lms.manage_own); supervisory holders of
 * lms.manage (director in their branch, principal/school admin school-wide)
 * reach any class in the conversation's scope — the same reach the exam
 * studio gives them. The EXAM is created with status=draft: a person reviews,
 * adjusts and publishes it in the exam studio — the AI never publishes or
 * releases the exam itself.
 */
class CreateExamTool extends TeacherScopedTool
{
    use SavesQuestionDrafts;

    public function description(): Stringable|string
    {
        return 'Create a DRAFT exam/quiz in one call: pass subject_id + section_ids (a teacher\'s own classes from MyTeachingLoadTool; a director/principal may pick any class from ClassCatalogTool), a title, and the questions — new_questions (generated rows incl. passage groups and matching, saved PUBLISHED into question_bank_id or a new bank_name) and/or question_ids (existing questions from MyQuestionBanksTool; a passage-group id expands to its sub-questions and stays together on the paper), or parts[] to group the paper (titles WITHOUT numbering — "Multiple Choice", "True/False"; the app adds "Part I/II" itself) with each part carrying its own questions. Call it as soon as the shape (class, coverage, count, types) is agreed — the exam saves as a DRAFT invisible to students, which the user reviews in the chat preview card; UpdateExamTool edits, regroups or (with the user\'s explicit confirmation) publishes it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->context->allows('lms.manage_own') && ! $this->context->allows('lms.manage')) {
            return $this->deny('You do not have exam-authoring access in this context.');
        }

        $input = $request->all();

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return $this->deny('Pass a title for the exam.');
        }

        $kind = in_array($input['kind'] ?? null, ['quiz', 'exam'], true) ? $input['kind'] : 'exam';

        $subjectId = (int) ($input['subject_id'] ?? 0);
        $sectionIds = collect((array) ($input['section_ids'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($subjectId <= 0 || $sectionIds->isEmpty() || $sectionIds->count() > 30) {
            return $this->deny('Pass subject_id and 1–30 section_ids from your own teaching load.');
        }

        // Supervisory authoring: lms.manage reaches any class in scope (a
        // director their branch, a principal school-wide); teachers stay on
        // their own assignments.
        $supervisory = $this->context->allows('lms.manage');

        // A class usually exists in SEVERAL semesters (semester 1 + semester 2
        // assignment rows). Disambiguate per section automatically — the
        // current AND writable term first, then any writable term — the user
        // must never be asked about term records or shown their ids.
        $assignments = ($supervisory ? $this->scopeAssignments($subjectId) : $this->ownAssignments(subjectId: $subjectId))
            ->whereIn('section_id', $sectionIds->all())
            ->groupBy('section_id')
            ->map(fn ($group) => $group->first(fn (SubjectAssignment $a): bool => (bool) $a->term?->is_current && $a->term?->status !== TermStatus::Closed)
                ?? $group->first(fn (SubjectAssignment $a): bool => $a->term?->status !== TermStatus::Closed)
                ?? $group->first())
            ->values();

        $missing = $sectionIds->diff($assignments->pluck('section_id'));
        if ($missing->isNotEmpty()) {
            // User-safe wording: the reason is relayed to the teacher, so it
            // names classes the way the app does — never database ids.
            $names = Section::query()
                ->whereIn('id', $missing)
                ->with('gradeLevel:id,name')
                ->get()
                ->map(fn (Section $section): string => trim(($section->gradeLevel?->name ?? '').' '.$section->name))
                ->filter()
                ->implode(', ');

            return $this->deny(
                $supervisory
                ? 'This subject has no teacher assigned in '.($names !== '' ? $names : 'some of those classes')
                    .' this semester — an exam anchors to a class that actually takes the subject. Re-check with the class catalog; assignments are managed per semester on the Semesters page (/semesters).'
                : 'You do not teach this subject in '.($names !== '' ? $names : 'some of those classes')
                    .' this semester. Pick from the classes in your teaching load.',
            );
        }

        if ($assignments->pluck('term_id')->unique()->count() > 1
            || $assignments->pluck('branch_id')->unique()->count() > 1) {
            return $this->deny('These classes are in different semesters or branches — one exam covers one semester, so create a separate exam per group.');
        }

        /** @var SubjectAssignment $anchor */
        $anchor = $assignments->first();

        try {
            TermGate::assertWritable($anchor->term);
        } catch (HttpException $e) {
            return $this->deny($e->getMessage());
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

        // Existing picks must live in the school's own banks (never platform).
        $existing = $existingIds->isEmpty()
            ? collect()
            : Question::query()
                ->whereIn('id', $existingIds)
                ->whereIn('status', ['published', 'draft'])
                ->with(['children' => fn ($q) => $q->where('status', 'published')])
                ->whereHas('bank', fn ($q) => $q
                    ->where('school_id', $anchor->school_id)
                    ->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $anchor->branch_id)))
                ->get()
                ->keyBy('id');

        if ($existingIds->isNotEmpty() && $existing->count() !== $existingIds->count()) {
            return $this->deny('Some question_ids are not available in your school\'s banks — re-check with the question-banks tool.');
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
                ? QuestionBank::query()
                    ->where('id', $bankId)
                    ->where('school_id', $anchor->school_id)
                    ->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $anchor->branch_id))
                    ->first()
                : null;

            if ($bank === null && $bankName === '') {
                $bankName = $title.' — AI drafts';
            }
        }

        $duration = is_numeric($input['duration_minutes'] ?? null)
            ? max(0, min(600, (int) $input['duration_minutes']))
            : null;

        $instructions = trim((string) ($input['instructions'] ?? ''));

        return DB::transaction(function () use ($anchor, $assignments, $title, $kind, $duration, $instructions, $bank, $bankName, $newQuestions, $paper, $buckets): string {
            $bank ??= $newQuestions === [] ? null : QuestionBank::create([
                'school_id' => $anchor->school_id,
                'branch_id' => $anchor->branch_id,
                'name' => mb_substr($bankName, 0, 120),
                'description' => 'Created with Temari AI.',
                'subject_id' => $anchor->subject_id,
                'grade_level_id' => $anchor->section?->grade_level_id,
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
                'is_platform' => false,
                'school_id' => $anchor->school_id,
                'branch_id' => $anchor->branch_id,
                'subject_assignment_id' => $anchor->id,
                'kind' => $kind,
                'title' => mb_substr($title, 0, 255),
                'instructions' => $instructions === '' ? null : QuestionRules::sanitizeStem(mb_substr($instructions, 0, 10000)),
                'language' => in_array($this->context->user->preferred_language, ['en', 'am', 'om'], true)
                    ? $this->context->user->preferred_language
                    : 'en',
                'settings' => array_filter([
                    'duration_minutes' => $duration,
                    'attempts_allowed' => 1,
                    'navigation' => 'free',
                    'results_policy' => 'manual',
                ], fn ($v) => $v !== null),
                'parts' => $paper['parts'],
                'created_by' => $this->context->user->id,
            ]);

            $quiz->targets()->createMany(
                $assignments->map(fn (SubjectAssignment $a): array => ['subject_assignment_id' => $a->id]),
            );

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
                'kind' => $quiz->kind,
                'status' => 'draft',
                'sections' => $assignments->map(fn (SubjectAssignment $a): string => trim(($a->section?->gradeLevel?->name ?? '').' '.($a->section?->name ?? ''))),
                'question_count' => $sort,
                'parts' => $paper['parts'] === null ? null : array_column($paper['parts'], 'title'),
                'new_question_ids' => array_merge(...$savedPerBucket),
                'question_bank_id' => $bank?->id,
                'bank_link' => $bank !== null ? '/lms/question-banks/'.$bank->id : null,
                'link' => '/lms/exams/'.$quiz->id,
                'note' => 'The exam is a DRAFT — nothing is in front of students yet. It can be published from this chat once the user explicitly confirms, or by hand in the exam studio.',
            ]);
        });
    }

    /**
     * Supervisory reach (lms.manage): every ACTIVE assignment of the subject
     * in the conversation's scope — a director's branch, or the whole school
     * in a school-wide session. The one-branch-per-exam check in handle()
     * still forces a principal to build per branch.
     *
     * @return Collection<int, SubjectAssignment>
     */
    private function scopeAssignments(int $subjectId): Collection
    {
        return SubjectAssignment::query()
            ->where('school_id', $this->context->schoolId())
            ->when($this->context->branchId() !== null, fn ($q) => $q->where('branch_id', $this->context->branchId()))
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->with(['subject:id,code,name', 'section:id,name,grade_level_id', 'section.gradeLevel:id,name', 'term:id,name,is_current,status'])
            ->get();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Exam title, e.g. "Mathematics — Chapter 1 Test".')->required(),
            'kind' => $schema->string()->description('quiz or exam (default exam).'),
            'subject_id' => $schema->integer()->description('The subject id from MyTeachingLoadTool / ClassCatalogTool.')->required(),
            'section_ids' => $schema->array()->items($schema->integer())->description('Section ids sitting this paper — a teacher\'s own classes (MyTeachingLoadTool), or any classes in scope for lms.manage holders (ClassCatalogTool). One branch and one semester per exam.')->required(),
            'duration_minutes' => $schema->integer()->description('Time limit in minutes (omit for none).'),
            'instructions' => $schema->string()->description('Short instructions shown to students (plain text).'),
            'new_questions' => $this->questionRowsSchema($schema)->description('Ungrouped papers: generated questions to save alongside the exam.'),
            'question_bank_id' => $schema->integer()->description('Existing bank for the new questions.'),
            'bank_name' => $schema->string()->description('Or a name for a new bank for them.'),
            'question_ids' => $schema->array()->items($schema->integer())->description('Ungrouped papers: existing question ids (from MyQuestionBanksTool breakdowns) to include as-is.'),
            'parts' => $this->partsSchema($schema)->description('Grouped papers: each part carries its own new_questions and/or question_ids plus optional part instructions. Use INSTEAD of the top-level question fields when the exam should be grouped by type/topic.'),
        ];
    }
}
