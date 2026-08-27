<?php

namespace App\Ai\Tools\Family;

use App\Ai\Tools\AiTool;
use App\Enums\QuizStatus;
use App\Models\Quiz;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Temari's national exam-prep catalog (ADR-016 platform lane): past papers
 * and mock exams for Grade 6/8/12 — open to every authenticated user. The
 * tutor recommends concrete papers (with ids the UI can deep-link) after a
 * weakness analysis instead of generic advice.
 */
class ExamPrepCatalogTool extends AiTool
{
    public function description(): Stringable|string
    {
        return 'Search Temari\'s national exam-prep catalog (Grade 6/8/12 past papers and mock exams) by grade, subject or exam year. Returns exam ids the app can open at /me/exam-prep. Use when recommending practice material.';
    }

    public function handle(Request $request): Stringable|string
    {
        $exams = Quiz::query()
            ->where('is_platform', true)
            ->where('status', QuizStatus::Published->value)
            ->with(['subject:id,name', 'gradeLevel:id,name'])
            ->when($request->integer('grade_level_id') ?: null, fn ($q, $id) => $q->where('grade_level_id', $id))
            ->when($request->string('subject')->toString() !== '', fn ($q) => $q->whereHas(
                'subject', fn ($s) => $s->where('name', 'ilike', '%'.$request->string('subject')->toString().'%'),
            ))
            ->when($request->integer('exam_year_ec') ?: null, fn ($q, $year) => $q->where('exam_year_ec', $year))
            ->orderByDesc('exam_year_ec')
            ->limit(20)
            ->get()
            ->map(fn (Quiz $quiz): array => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'subject' => $quiz->subject?->name,
                'grade' => $quiz->gradeLevel?->name,
                'exam_kind' => $quiz->exam_kind,
                'exam_year_ec' => $quiz->exam_year_ec,
                'stream' => $quiz->stream,
                'language' => $quiz->language,
            ]);

        return $this->ok(['exams' => $exams, 'open_link' => '/me/exam-prep']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'grade_level_id' => $schema->integer()->description('Filter by grade level id (from the student profile/results tools).'),
            'subject' => $schema->string()->description('Filter by subject name, e.g. "Mathematics".'),
            'exam_year_ec' => $schema->integer()->description('Filter by Ethiopian exam year, e.g. 2016.'),
        ];
    }
}
