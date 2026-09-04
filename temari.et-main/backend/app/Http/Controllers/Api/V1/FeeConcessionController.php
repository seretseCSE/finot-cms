<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConcessionCategory;
use App\Enums\ConcessionStatus;
use App\Enums\DiscountType;
use App\Enums\FeeType;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\FeeConcessionResource;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\FeeConcession;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\Term;
use App\Services\FeeConcessionResolver;
use App\Services\Notify\Notifier;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Standing discounts/scholarships (ADR: concessions are the policy layer above
 * per-invoice discounts). Manual grants by fees.manage staff are born ACTIVE;
 * policy suggestions (sibling / employee-child) are born PENDING and pass
 * through this controller's approve/reject review lane. Gated by fees.* like
 * every other money surface.
 */
class FeeConcessionController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;

    private const LIST_WITH = [
        'student:id,first_name,father_name,grandfather_name,public_id',
        'parentProfile:id,user_id,first_name,father_name,grandfather_name',
        'parentProfile.user:id,name',
        'branch:id,name',
        'academicYear:id,name',
        'term:id,name',
        'approver:id,name',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        [$schoolId, $branch] = $this->scope($request, 'fees.view');

        $query = $this->baseQuery($request, $schoolId, $branch?->id)
            ->with(self::LIST_WITH)
            ->withCount('invoices');

        $this->applySort($query, $request, ['created_at', 'status', 'category'], 'created_at');

        return FeeConcessionResource::collection($query->paginate($this->perPage($request)));
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        [$schoolId, $branch] = $this->scope($request, 'fees.view');

        $rows = $this->baseQuery($request, $schoolId, $branch?->id)
            ->with(self::LIST_WITH)
            ->withCount('invoices')
            ->latest()
            ->limit(5000)
            ->get();

        return FeeConcessionResource::collection($rows);
    }

    /**
     * Review-queue vitals + the ETB value concessions have taken off bills
     * this scope (derived from stamped invoices — the frozen truth).
     */
    public function stats(Request $request): JsonResponse
    {
        [$schoolId, $branch] = $this->scope($request, 'fees.view');

        $counts = FeeConcession::query()
            ->where('school_id', $schoolId)
            ->when($branch, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branch->id)))
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'pending') AS pending, COUNT(*) FILTER (WHERE status = 'active') AS active")
            ->first();

        $net = Invoice::netAmountSql();
        $granted = Invoice::query()
            ->whereNotNull('fee_concession_id')
            ->where('school_id', $schoolId)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->where('status', '!=', 'void')
            ->selectRaw("COALESCE(SUM(amount - ({$net})), 0) AS value, COUNT(*) AS invoices")
            ->first();

        return response()->json([
            'data' => [
                'pending_count' => (int) ($counts->pending ?? 0),
                'active_count' => (int) ($counts->active ?? 0),
                'granted_value' => (string) round((float) ($granted->value ?? 0), 2),
                'granted_invoices' => (int) ($granted->invoices ?? 0),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id', 'required_without:parent_id', 'prohibits:parent_id'],
            'parent_id' => ['nullable', 'integer', 'exists:parents,id'],
            'category' => ['required', Rule::enum(ConcessionCategory::class)],
            'discount_type' => ['required', Rule::in([
                DiscountType::Percentage->value, DiscountType::Fixed->value, DiscountType::FullScholarship->value,
            ])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'fee_types' => ['nullable', 'array', 'min:1'],
            'fee_types.*' => [Rule::enum(FeeType::class)],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            // Also re-price the student's open (unpaid/partial, undiscounted)
            // invoices in scope — the fix for "billed a minute before the
            // concession was filed". Paid/void history is never touched.
            'apply_to_open_invoices' => ['sometimes', 'boolean'],
        ]);

        $type = DiscountType::from($data['discount_type']);
        $value = round((float) ($data['discount_value'] ?? 0), 2);

        if ($type === DiscountType::Percentage && ($value <= 0 || $value > 100)) {
            throw ValidationException::withMessages([
                'discount_value' => ['A percentage discount must be between 0 and 100.'],
            ]);
        }
        if ($type === DiscountType::Fixed && $value <= 0) {
            throw ValidationException::withMessages([
                'discount_value' => ['A fixed discount must be greater than zero.'],
            ]);
        }

        // A year (or its term) anchors the concession to that year's branch;
        // without one it is a school-wide, open-ended grant.
        $year = ! empty($data['academic_year_id'])
            ? AcademicYear::findOrFail((int) $data['academic_year_id'])
            : null;

        if (! empty($data['term_id'])) {
            $term = Term::findOrFail((int) $data['term_id']);

            if ($year === null || $term->academic_year_id !== $year->id) {
                throw ValidationException::withMessages([
                    'term_id' => ['The semester must belong to the selected academic year.'],
                ]);
            }
        }

        $branch = $year !== null
            ? $year->branch()->firstOrFail()
            : $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context first.');
        abort_unless(
            $request->user()->hasPermissionForScope('fees.manage', $schoolId, $branch?->id),
            403,
        );

        $this->assertSubjectBelongsToSchool($data, $schoolId);

        $concession = FeeConcession::create([
            'school_id' => $schoolId,
            'branch_id' => $branch?->id,
            'student_id' => $data['student_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'category' => $data['category'],
            'discount_type' => $type->value,
            'discount_value' => $type === DiscountType::FullScholarship ? 0 : $value,
            'fee_types' => $data['fee_types'] ?? null,
            'academic_year_id' => $year?->id,
            'term_id' => $data['term_id'] ?? null,
            // A deliberate grant by fees.manage staff needs no second approval.
            'status' => ConcessionStatus::Active->value,
            'source' => 'manual',
            'reason' => $data['reason'] ?? null,
            'requested_by' => $request->user()->id,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $repriced = $request->boolean('apply_to_open_invoices')
            ? app(FeeConcessionResolver::class)->applyToOpenInvoices($concession)
            : 0;

        $this->notifyGranted($concession);

        return (new FeeConcessionResource($concession->load(self::LIST_WITH)))
            ->additional([
                'message' => 'Concession granted.',
                'meta' => ['repriced_invoices' => $repriced],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Tell the family a standing discount now covers them: the student's
     * family for a student-scoped concession, the guardian's own account for
     * a guardian-scoped one (it covers all their linked children).
     */
    private function notifyGranted(FeeConcession $concession): void
    {
        $notifier = app(Notifier::class);
        $options = [
            'link' => '/me/payments',
            'schoolId' => $concession->school_id,
            'branchId' => $concession->branch_id,
        ];

        if ($concession->student_id !== null) {
            $notifier->toFamily($concession->student, 'finance.concession_granted', [], $options);
        } elseif (($user = $concession->parentProfile?->user) !== null) {
            $notifier->toUser($user, 'finance.concession_granted', [
                'student' => Lang::get('notifications.finance.concession_granted.all_children', [], $user->preferred_language ?: 'en'),
            ], $options);
        }
    }

    public function show(Request $request, FeeConcession $feeConcession): FeeConcessionResource
    {
        $this->authorizeRow($request, $feeConcession, 'fees.view');

        return new FeeConcessionResource($feeConcession->load(self::LIST_WITH)->loadCount('invoices'));
    }

    /** Approve a pending suggestion — it starts applying to NEW invoices. */
    public function approve(Request $request, FeeConcession $feeConcession): FeeConcessionResource
    {
        $this->authorizeRow($request, $feeConcession, 'fees.manage');
        $this->assertStatus($feeConcession, ConcessionStatus::Pending);

        $feeConcession->update([
            'status' => ConcessionStatus::Active->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $repriced = $request->boolean('apply_to_open_invoices')
            ? app(FeeConcessionResolver::class)->applyToOpenInvoices($feeConcession)
            : 0;

        $this->notifyGranted($feeConcession);

        return (new FeeConcessionResource($feeConcession->load(self::LIST_WITH)))
            ->additional([
                'message' => 'Concession approved.',
                'meta' => ['repriced_invoices' => $repriced],
            ]);
    }

    public function reject(Request $request, FeeConcession $feeConcession): FeeConcessionResource
    {
        $this->authorizeRow($request, $feeConcession, 'fees.manage');
        $this->assertStatus($feeConcession, ConcessionStatus::Pending);

        $feeConcession->update([
            'status' => ConcessionStatus::Rejected->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return (new FeeConcessionResource($feeConcession->load(self::LIST_WITH)))
            ->additional(['message' => 'Suggestion rejected.']);
    }

    /** Stop an active concession for FUTURE invoices; billed history stays. */
    public function revoke(Request $request, FeeConcession $feeConcession): FeeConcessionResource
    {
        $this->authorizeRow($request, $feeConcession, 'fees.manage');
        $this->assertStatus($feeConcession, ConcessionStatus::Active);

        $feeConcession->update([
            'status' => ConcessionStatus::Revoked->value,
            'revoked_at' => now(),
        ]);

        return (new FeeConcessionResource($feeConcession->load(self::LIST_WITH)))
            ->additional(['message' => 'Concession revoked.']);
    }

    /**
     * Decide a batch of PENDING policy suggestions (sibling / employee-child) —
     * the finance officer's review lane, which arrives one screenful at a time
     * after each intake. Rows already decided or outside the actor's money scope
     * are skipped and reported; no discount is ever applied silently.
     */
    public function bulkDecide(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...self::bulkIdRules(),
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'apply_to_open_invoices' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user();
        $approving = $data['decision'] === 'approved';
        $reprice = $approving && $request->boolean('apply_to_open_invoices');
        $decided = 0;
        $repriced = 0;
        $skipped = [];

        $rows = $this->bulkRows($data['ids'], FeeConcession::with(self::LIST_WITH), $skipped);

        foreach ($rows as $concession) {
            $name = $concession->student?->full_name ?? $concession->parentProfile?->user?->name;

            if (! $actor->hasPermissionForScope('fees.manage', $concession->school_id, $concession->branch_id)) {
                $skipped[] = self::skipRow($concession, $name, 'not_permitted');

                continue;
            }

            if ($concession->status !== ConcessionStatus::Pending) {
                $skipped[] = self::skipRow($concession, $name, 'already_decided');

                continue;
            }

            $concession->update([
                'status' => $approving ? ConcessionStatus::Active->value : ConcessionStatus::Rejected->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            if ($approving) {
                if ($reprice) {
                    $repriced += app(FeeConcessionResolver::class)->applyToOpenInvoices($concession);
                }
                $this->notifyGranted($concession);
            }

            $decided++;
        }

        return response()->json([
            'message' => "{$decided} suggestion(s) decided.",
            'meta' => [
                'decided' => $decided,
                'requested' => count($data['ids']),
                'repriced_invoices' => $repriced,
                'skipped' => $skipped,
            ],
        ]);
    }

    public function destroy(Request $request, FeeConcession $feeConcession): JsonResponse
    {
        $this->authorizeRow($request, $feeConcession, 'fees.manage');

        if ($feeConcession->invoices()->exists()) {
            throw ValidationException::withMessages([
                'concession' => ['This concession has been applied to invoices — revoke it instead of deleting.'],
            ]);
        }

        $feeConcession->delete();

        return response()->json(['message' => 'Concession deleted.']);
    }

    /**
     * Context scoping + list filters shared by index/export.
     *
     * @return Builder<FeeConcession>
     */
    private function baseQuery(Request $request, int $schoolId, ?int $branchId): Builder
    {
        $query = FeeConcession::query()
            ->where('school_id', $schoolId)
            // Branch context sees its own rows PLUS school-wide (branchless)
            // grants — those apply to its students too.
            ->when($branchId, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->when($branchId === null, fn ($q) => $q->when(
                $this->branchFilterId($request, null),
                fn ($inner, int $id) => $inner->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $id)),
            ));

        $this->applySearch($query, $request, fn ($outer, string $n) => $outer
            ->whereHas('student', fn ($s) => $s->where('search_text', 'ilike', $this->needle($n)))
            ->orWhereHas('parentProfile', fn ($p) => $p->where('search_text', 'ilike', $this->needle($n))));

        if ($statuses = $this->csvValues($request, 'status')) {
            $query->whereIn('status', $statuses);
        }

        if ($categories = $this->csvValues($request, 'category')) {
            $query->whereIn('category', $categories);
        }

        if ($yearIds = $this->csvIds($request, 'academic_year_id')) {
            $query->where(fn ($q) => $q->whereNull('academic_year_id')->orWhereIn('academic_year_id', $yearIds));
        }

        if ($request->filled('student_id')) {
            $studentId = $request->integer('student_id');
            // A student's concessions include guardian-level ones reaching them.
            $query->where(fn ($q) => $q
                ->where('student_id', $studentId)
                ->orWhereIn('parent_id', fn ($sub) => $sub
                    ->select('parent_id')
                    ->from('student_guardians')
                    ->where('student_id', $studentId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')));
        }

        return $query;
    }

    /**
     * @return array{0: int, 1: Branch|null}
     */
    private function scope(Request $request, string $permission): array
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context first.');
        abort_unless(
            $request->user()->hasPermissionForScope($permission, $schoolId, $branch?->id),
            403,
        );

        return [(int) $schoolId, $branch];
    }

    private function authorizeRow(Request $request, FeeConcession $concession, string $permission): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope($permission, $concession->school_id, $concession->branch_id),
            403,
        );
    }

    private function assertStatus(FeeConcession $concession, ConcessionStatus $expected): void
    {
        if ($concession->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Only {$expected->value} concessions can take this action."],
            ]);
        }
    }

    /**
     * The subject must belong to this school: the student via registration or
     * enrollment, a guardian via a linked child there.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertSubjectBelongsToSchool(array $data, int $schoolId): void
    {
        if (! empty($data['student_id'])) {
            $ok = Student::query()
                ->whereKey((int) $data['student_id'])
                ->where(fn ($q) => $q
                    ->where('school_id', $schoolId)
                    ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolId)))
                ->exists();

            if (! $ok) {
                throw ValidationException::withMessages([
                    'student_id' => ['The student does not belong to this school.'],
                ]);
            }
        }

        if (! empty($data['parent_id'])) {
            $ok = ParentProfile::query()
                ->whereKey((int) $data['parent_id'])
                ->whereHas('guardianships.student', fn ($s) => $s
                    ->where('school_id', $schoolId)
                    ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolId)))
                ->exists();

            if (! $ok) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The guardian has no linked student at this school.'],
                ]);
            }
        }
    }
}
