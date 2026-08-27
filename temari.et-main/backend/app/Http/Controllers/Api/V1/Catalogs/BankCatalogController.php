<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Platform CRUD over the Ethiopian bank + mobile wallet catalog. Rows carry
 * FK weight (bank_accounts.bank_id restricts) — referenced banks can only be
 * deactivated, never deleted.
 */
class BankCatalogController extends CatalogController
{
    use HandlesListQueries;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, ['name', 'code', 'type', 'is_active', 'created_at'], 'name', 'asc');

        return BankResource::collection($query->paginate($this->perPage($request)));
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->assertCatalogManager($request);

        $query = $this->buildQuery($request);
        $this->applySort($query, $request, ['name', 'code', 'type', 'is_active', 'created_at'], 'name', 'asc');

        return BankResource::collection($query->limit(1000)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $data = $this->validated($request);

        $bank = Bank::create($data);

        return (new BankResource($bank->loadCount('accounts')))
            ->additional(['message' => 'Bank added to the catalog.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Bank $bank): BankResource
    {
        $this->assertCatalogManager($request);

        $bank->update($this->validated($request, $bank));

        return (new BankResource($bank->loadCount('accounts')))
            ->additional(['message' => 'Bank updated.']);
    }

    public function destroy(Request $request, Bank $bank): JsonResponse
    {
        $this->assertCatalogManager($request);

        abort_if(
            $bank->accounts()->exists(),
            422,
            'Schools hold collection accounts at this bank — deactivate it instead of deleting.',
        );

        $bank->delete();

        return response()->json(['message' => 'Bank deleted.']);
    }

    /**
     * @return Builder<Bank>
     */
    private function buildQuery(Request $request): Builder
    {
        $query = Bank::query()->withCount('accounts');

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('name', 'ilike', $this->needle($n))
            ->orWhere('code', 'ilike', $this->needle($n)));

        if ($types = $this->csvValues($request, 'type')) {
            $query->whereIn('type', $types);
        }

        $this->applyBooleanFilter($query, $request, 'is_active', 'is_active');

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Bank $bank = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('banks', 'code')->ignore($bank?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([Bank::TYPE_BANK, Bank::TYPE_WALLET])],
            'logo' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
