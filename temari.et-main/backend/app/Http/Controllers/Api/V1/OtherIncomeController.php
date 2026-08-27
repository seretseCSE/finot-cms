<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\OtherIncomeResource;
use App\Models\BankAccount;
use App\Models\OtherIncome;
use App\Support\Ethiopia;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Non-fee money in (hall rental, sales, donations…). Student-fee payments
 * never land here — those are invoice-anchored `payments`.
 */
class OtherIncomeController extends Controller
{
    use HandlesListQueries;

    private const LIST_WITH = ['category:id,name,kind', 'bankAccount.bank:id,code,name,type,logo', 'recorder:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasContextPermission('finance.books.view'), 403);

        $query = $this->baseQuery($request)->with(self::LIST_WITH);

        if ($this->activeBranchOrNull($request) === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['received_on', 'amount', 'created_at'], 'received_on');

        return OtherIncomeResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('finance.books.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $this->validatePayload($request, $branch->school_id, $branch->id);

        $income = OtherIncome::create([
            ...$data,
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'recorded_by' => $request->user()->id,
        ]);

        return (new OtherIncomeResource($income->load(self::LIST_WITH)))
            ->additional(['message' => 'Income recorded.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, OtherIncome $otherIncome): OtherIncomeResource
    {
        $this->authorizeRow($request, $otherIncome);

        $otherIncome->update($this->validatePayload($request, $otherIncome->school_id, $otherIncome->branch_id));

        return (new OtherIncomeResource($otherIncome->load(self::LIST_WITH)))
            ->additional(['message' => 'Income saved.']);
    }

    public function destroy(Request $request, OtherIncome $otherIncome): JsonResponse
    {
        $this->authorizeRow($request, $otherIncome);

        $otherIncome->delete();

        return response()->json(['message' => 'Income deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, int $schoolId, int $branchId): array
    {
        $data = $request->validate([
            'finance_category_id' => [
                'required',
                Rule::exists('finance_categories', 'id')
                    ->where('school_id', $schoolId)
                    ->where('kind', 'income'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'received_on' => ['required', 'date', 'after:2000-01-01', 'before_or_equal:'.Ethiopia::today()],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'wallet', 'other'])],
            'bank_account_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'received_on.before_or_equal' => __('dates.day_future'),
        ]);

        if (! in_array($data['method'], ['bank_transfer', 'wallet'], true)) {
            $data['bank_account_id'] = null;
        } elseif (($data['bank_account_id'] ?? null) !== null) {
            $usable = BankAccount::query()
                ->whereKey($data['bank_account_id'])
                ->usableByBranch($branchId)
                ->exists();

            if (! $usable) {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['Pick a bank account that is active for this branch.'],
                ]);
            }
        }

        return $data;
    }

    private function authorizeRow(Request $request, OtherIncome $income): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('finance.books.manage', $income->school_id, $income->branch_id),
            403,
        );
    }

    /**
     * @return Builder<OtherIncome>
     */
    private function baseQuery(Request $request): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        return OtherIncome::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($this->csvIds($request, 'finance_category_id'), fn ($q, array $ids) => $q->whereIn('finance_category_id', $ids))
            ->when($this->csvValues($request, 'method'), fn ($q, array $methods) => $q->whereIn('method', $methods))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('received_on', '>=', $from))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('received_on', '<=', $to))
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->where('title', 'ilike', $this->needle($n))
                ->orWhere('source', 'ilike', $this->needle($n))
                ->orWhere('reference', 'ilike', $this->needle($n))));
    }
}
