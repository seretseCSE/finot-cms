<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnnualLessonPlan;
use App\Models\Employee;
use App\Models\EvaluationTemplate;
use App\Models\Marklist;
use App\Models\School;
use App\Models\SubjectAssignment;
use App\Models\TeacherEvaluation;
use App\Models\Term;
use App\Models\User;
use App\Models\WeeklyLessonPlan;
use App\Services\Analytics\Analytics;
use App\Services\Notify\Notifier;
use App\Support\EvaluationPolicy;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teacher performance appraisals (MoE continuous appraisal, digitised).
 * Supervisors (evaluations.manage) score against the school's rubric;
 * the evaluated employee reads + acknowledges THEIR OWN record through
 * evaluations.view_own (row-checked). Criteria snapshot at creation; the
 * detail view adds platform-derived signals (lesson-plan and marklist
 * follow-through) as CONTEXT for the evaluator — never auto-scored.
 */
class TeacherEvaluationController extends Controller
{
    /** The school's rubric (auto-provisioned MoE default). */
    public function template(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_unless($schoolId !== null, 422, 'Pick a school workspace first.');
        abort_unless(
            $user->hasPermissionForScope('evaluations.view', $schoolId, $branch?->id)
            || $user->hasPermissionForScope('evaluations.manage', $schoolId, $branch?->id),
            403,
        );

        $template = EvaluationPolicy::templateFor(School::findOrFail($schoolId));

        return response()->json(['data' => self::templateRow($template->load('criteria'))]);
    }

    /** Replace the rubric's name + criteria (weights must sum to 100). */
    public function updateTemplate(Request $request, EvaluationTemplate $template): JsonResponse
    {
        // School managers hold the permission at the template's school
        // directly; a branch-scoped director qualifies through their branch —
        // but ONLY when that branch belongs to the template's school (a
        // context permission alone must never reach another tenant's rubric).
        $user = $request->user();
        $contextBranch = $this->activeBranchOrNull($request);

        abort_unless(
            $user->hasPermissionForScope('evaluations.manage', $template->school_id, null)
            || ((int) ($contextBranch?->school_id ?? 0) === (int) $template->school_id
                && $user->hasPermissionForScope('evaluations.manage', $template->school_id, $contextBranch->id)),
            403,
        );

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'criteria' => ['required', 'array', 'min:1', 'max:30'],
            'criteria.*.domain' => ['required', 'string', 'max:64'],
            'criteria.*.label' => ['required', 'string', 'max:255'],
            'criteria.*.weight' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'criteria.*.max_score' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $weightSum = round(collect($data['criteria'])->sum(fn (array $c): float => (float) $c['weight']), 2);
        abort_unless(abs($weightSum - 100.0) < 0.01, 422, 'Criterion weights must add up to exactly 100.');

        $template->update(['name' => $data['name'] ?? $template->name]);

        // Wholesale replace — existing evaluations keep their snapshots.
        $template->criteria()->delete();
        foreach ($data['criteria'] as $index => $criterion) {
            $template->criteria()->create([...$criterion, 'sort_order' => $index]);
        }

        return response()->json([
            'data' => self::templateRow($template->fresh('criteria')),
            'message' => 'Appraisal template saved.',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);
        $user = $request->user();
        $termId = $request->integer('term_id');

        // Own lane: the employee's own records across their employee files.
        if ($request->boolean('mine')) {
            abort_unless($user->hasContextPermission('evaluations.view_own'), 403);

            $rows = TeacherEvaluation::query()
                ->whereIn('employee_id', Employee::query()->where('user_id', $user->id)->select('id'))
                ->where('term_id', $termId)
                ->whereIn('status', [TeacherEvaluation::STATUS_SUBMITTED, TeacherEvaluation::STATUS_ACKNOWLEDGED])
                ->with(['employee:id,first_name,father_name,grandfather_name,photo_path,user_id', 'evaluator:id,name', 'term:id,name'])
                ->orderByDesc('submitted_at')
                ->get();

            return response()->json(['data' => $rows->map(fn (TeacherEvaluation $e): array => self::row($e))]);
        }

        abort_unless(
            $user->hasContextPermission('evaluations.view') || $user->hasContextPermission('evaluations.manage'),
            403,
        );

        $branch = $this->activeBranchOrNull($request);

        $rows = TeacherEvaluation::query()
            ->where('term_id', $termId)
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($branch === null, fn ($q) => $q->where('school_id', $this->activeSchoolScopeId($request) ?? 0))
            ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->with(['employee:id,first_name,father_name,grandfather_name,photo_path,user_id', 'evaluator:id,name', 'term:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $rows->map(fn (TeacherEvaluation $e): array => self::row($e))]);
    }

    /** Start a draft appraisal: snapshot the rubric for one employee × term. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $term = Term::findOrFail($data['term_id']);

        abort_unless(
            $user->hasPermissionForScope('evaluations.manage', $employee->school_id, $employee->branch_id),
            403,
        );
        abort_unless((int) $term->branch_id === (int) $employee->branch_id, 422, 'The semester belongs to another branch.');
        TermGate::assertWritable($term);

        abort_if(
            TeacherEvaluation::query()->where('employee_id', $employee->id)->where('term_id', $term->id)->exists(),
            422,
            'This employee already has an appraisal for this semester.',
        );

        $template = EvaluationPolicy::templateFor(School::findOrFail($employee->school_id))->load('criteria');

        $evaluation = TeacherEvaluation::create([
            'school_id' => $employee->school_id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'term_id' => $term->id,
            'evaluation_template_id' => $template->id,
            'evaluator_id' => $user->id,
            'status' => TeacherEvaluation::STATUS_DRAFT,
        ]);

        foreach ($template->criteria as $criterion) {
            $evaluation->scores()->create([
                'evaluation_criterion_id' => $criterion->id,
                'domain' => $criterion->domain,
                'label' => $criterion->label,
                'weight' => $criterion->weight,
                'max_score' => $criterion->max_score,
                'sort_order' => $criterion->sort_order,
            ]);
        }

        Analytics::capture($user, 'evaluation.started', [
            'evaluation_id' => $evaluation->id,
        ], $employee->school_id, $employee->branch_id);

        return response()->json([
            'data' => self::detail($evaluation->load(['employee:id,first_name,father_name,grandfather_name,photo_path,user_id', 'evaluator:id,name', 'term:id,name', 'scores'])),
            'message' => 'Appraisal started.',
        ], 201);
    }

    public function show(Request $request, TeacherEvaluation $evaluation): JsonResponse
    {
        $this->authorizeRead($request->user(), $evaluation);

        $evaluation->load(['employee:id,first_name,father_name,grandfather_name,photo_path,user_id', 'evaluator:id,name', 'term:id,name,status', 'scores']);

        return response()->json([
            'data' => [
                ...self::detail($evaluation),
                // Evidence panel — platform facts, never auto-scored.
                'signals' => $this->signals($evaluation),
            ],
        ]);
    }

    /** Save scores + narratives (drafts only, the evaluator's pen). */
    public function update(Request $request, TeacherEvaluation $evaluation): JsonResponse
    {
        $this->authorizeManage($request->user(), $evaluation);
        abort_unless($evaluation->status === TeacherEvaluation::STATUS_DRAFT, 422, 'Only a draft appraisal can be edited.');
        TermGate::assertWritable($evaluation->term);

        $data = $request->validate([
            'strengths' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'improvements' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'scores' => ['sometimes', 'array'],
            'scores.*.id' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0'],
            'scores.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data['scores'] ?? [] as $line) {
            $score = $evaluation->scores->firstWhere('id', (int) $line['id']);
            if ($score === null) {
                continue;
            }
            abort_if(
                isset($line['score']) && (float) $line['score'] > (float) $score->max_score,
                422,
                "Score exceeds the maximum of {$score->max_score} for \"{$score->label}\".",
            );
            $score->update([
                'score' => $line['score'] ?? null,
                'note' => $line['note'] ?? null,
            ]);
        }

        $evaluation->update([
            ...collect($data)->only(['strengths', 'improvements'])->all(),
            'overall_score' => $evaluation->fresh('scores')->computeOverall(),
        ]);

        return response()->json([
            'data' => self::detail($evaluation->fresh(['employee:id,first_name,father_name,grandfather_name,photo_path,user_id', 'evaluator:id,name', 'term:id,name', 'scores'])),
            'message' => 'Appraisal saved.',
        ]);
    }

    /** Share with the teacher: draft → submitted (all criteria scored). */
    public function submit(Request $request, TeacherEvaluation $evaluation, Notifier $notifier): JsonResponse
    {
        $this->authorizeManage($request->user(), $evaluation);
        abort_unless($evaluation->status === TeacherEvaluation::STATUS_DRAFT, 422, 'Only a draft appraisal can be shared.');
        TermGate::assertWritable($evaluation->term);

        $evaluation->load('scores');
        abort_if(
            $evaluation->scores->contains(fn ($s): bool => $s->score === null),
            422,
            'Score every criterion before sharing the appraisal.',
        );

        $evaluation->update([
            'status' => TeacherEvaluation::STATUS_SUBMITTED,
            'overall_score' => $evaluation->computeOverall(),
            'submitted_at' => now(),
        ]);

        $teacherUser = $evaluation->employee?->user_id !== null
            ? User::find($evaluation->employee->user_id)
            : null;

        $notifier->toUser($teacherUser, 'hr.evaluation_shared', [
            'term' => $evaluation->term?->name ?? '',
            'score' => (string) $evaluation->overall_score,
        ], [
            'link' => '/hr/evaluations',
            'schoolId' => $evaluation->school_id,
            'branchId' => $evaluation->branch_id,
        ]);

        Analytics::capture($request->user(), 'evaluation.submitted', [
            'evaluation_id' => $evaluation->id,
            'overall' => (float) $evaluation->overall_score,
        ], $evaluation->school_id, $evaluation->branch_id);

        return response()->json([
            'data' => self::detail($evaluation->fresh(['employee:id,first_name,father_name,grandfather_name,photo_path,user_id', 'evaluator:id,name', 'term:id,name', 'scores'])),
            'message' => 'Appraisal shared with the teacher.',
        ]);
    }

    /** The teacher's signature: submitted → acknowledged (optional comment). */
    public function acknowledge(Request $request, TeacherEvaluation $evaluation, Notifier $notifier): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasContextPermission('evaluations.view_own')
            && $evaluation->employee?->user_id !== null
            && (int) $evaluation->employee->user_id === $user->id,
            403,
        );
        abort_unless($evaluation->status === TeacherEvaluation::STATUS_SUBMITTED, 422, 'Only a shared appraisal can be acknowledged.');

        $data = $request->validate(['teacher_comment' => ['nullable', 'string', 'max:2000']]);

        $evaluation->update([
            'status' => TeacherEvaluation::STATUS_ACKNOWLEDGED,
            'teacher_comment' => $data['teacher_comment'] ?? null,
            'acknowledged_at' => now(),
        ]);

        $notifier->toUser($evaluation->evaluator, 'hr.evaluation_acknowledged', [
            'teacher' => $evaluation->employee?->full_name ?? '',
            'term' => $evaluation->term?->name ?? '',
        ], [
            'link' => '/hr/evaluations',
            'schoolId' => $evaluation->school_id,
            'branchId' => $evaluation->branch_id,
        ]);

        return response()->json([
            'data' => self::detail($evaluation->fresh(['employee:id,first_name,father_name,grandfather_name,photo_path,user_id', 'evaluator:id,name', 'term:id,name', 'scores'])),
            'message' => 'Appraisal acknowledged.',
        ]);
    }

    /** Drafts may be discarded; shared records are history. */
    public function destroy(Request $request, TeacherEvaluation $evaluation): JsonResponse
    {
        $this->authorizeManage($request->user(), $evaluation);
        abort_unless($evaluation->status === TeacherEvaluation::STATUS_DRAFT, 422, 'Only a draft appraisal can be deleted.');

        $evaluation->delete();

        return response()->json(['message' => 'Appraisal deleted.']);
    }

    private function authorizeRead(User $user, TeacherEvaluation $evaluation): void
    {
        $supervisor = $user->hasPermissionForScope('evaluations.view', $evaluation->school_id, $evaluation->branch_id)
            || $user->hasPermissionForScope('evaluations.manage', $evaluation->school_id, $evaluation->branch_id);

        // The evaluated employee reads their own record once it is shared.
        $own = $user->hasPermissionForScope('evaluations.view_own', $evaluation->school_id, $evaluation->branch_id)
            && $evaluation->employee?->user_id !== null
            && (int) $evaluation->employee->user_id === $user->id
            && $evaluation->status !== TeacherEvaluation::STATUS_DRAFT;

        abort_unless($supervisor || $own, 403);
    }

    private function authorizeManage(User $user, TeacherEvaluation $evaluation): void
    {
        abort_unless(
            $user->hasPermissionForScope('evaluations.manage', $evaluation->school_id, $evaluation->branch_id),
            403,
        );
    }

    /**
     * Platform-derived context for the evaluator: how the teacher's term
     * actually went by the system's own records. Facts only.
     *
     * @return array<string, mixed>
     */
    private function signals(TeacherEvaluation $evaluation): array
    {
        $employeeId = $evaluation->employee_id;
        $termId = $evaluation->term_id;

        $assignments = SubjectAssignment::query()
            ->where('employee_id', $employeeId)
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->count();

        $marklistsApproved = Marklist::query()
            ->where('term_id', $termId)
            ->where('status', 'approved')
            ->whereIn('subject_assignment_id', SubjectAssignment::query()
                ->where('employee_id', $employeeId)
                ->where('term_id', $termId)
                ->select('id'))
            ->count();

        $weeklyPlans = WeeklyLessonPlan::query()
            ->where('term_id', $termId)
            ->whereIn('annual_lesson_plan_id', AnnualLessonPlan::query()
                ->where('employee_id', $employeeId)
                ->select('id'))
            ->selectRaw("COUNT(*) as total, COUNT(*) FILTER (WHERE status = 'approved') as approved")
            ->first();

        return [
            'classes' => $assignments,
            'marklists_approved' => $marklistsApproved,
            'lesson_plans_total' => (int) ($weeklyPlans->total ?? 0),
            'lesson_plans_approved' => (int) ($weeklyPlans->approved ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private static function templateRow(EvaluationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'criteria' => $template->criteria->map(fn ($c): array => [
                'id' => $c->id,
                'domain' => $c->domain,
                'label' => $c->label,
                'weight' => (float) $c->weight,
                'max_score' => (float) $c->max_score,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private static function row(TeacherEvaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'employee' => [
                'id' => $evaluation->employee?->id,
                'name' => $evaluation->employee?->full_name,
                'photo_url' => $evaluation->employee?->photo_url,
            ],
            'term' => ['id' => $evaluation->term?->id, 'name' => $evaluation->term?->name],
            'evaluator_name' => $evaluation->evaluator?->name,
            'status' => $evaluation->status,
            'overall_score' => $evaluation->overall_score !== null ? (float) $evaluation->overall_score : null,
            'submitted_at' => $evaluation->submitted_at?->toIso8601String(),
            'acknowledged_at' => $evaluation->acknowledged_at?->toIso8601String(),
            'updated_at' => $evaluation->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private static function detail(TeacherEvaluation $evaluation): array
    {
        return [
            ...self::row($evaluation),
            'strengths' => $evaluation->strengths,
            'improvements' => $evaluation->improvements,
            'teacher_comment' => $evaluation->teacher_comment,
            'scores' => $evaluation->scores->map(fn ($s): array => [
                'id' => $s->id,
                'domain' => $s->domain,
                'label' => $s->label,
                'weight' => (float) $s->weight,
                'max_score' => (float) $s->max_score,
                'score' => $s->score !== null ? (float) $s->score : null,
                'note' => $s->note,
            ])->values(),
        ];
    }
}
