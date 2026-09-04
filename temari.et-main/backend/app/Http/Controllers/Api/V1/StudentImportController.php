<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudentImportRowStatus;
use App\Enums\StudentImportStatus;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudentImportRequest;
use App\Http\Resources\StudentImportResource;
use App\Http\Resources\StudentImportRowResource;
use App\Jobs\ImportStudentsJob;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Services\Imports\StudentImportRowValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk student import (the studio's API). The spreadsheet is parsed in the
 * browser; this controller receives an import session + canonical row chunks,
 * validates rows in place, accepts inline fixes and duplicate resolutions,
 * then hands the clean set to ImportStudentsJob. Everything is gated on
 * `students.create` at the import's branch — the same authority the
 * registration wizard requires.
 */
class StudentImportController extends Controller
{
    use HandlesListQueries;

    private const MAX_ROWS_PER_CHUNK = 500;

    private const MAX_ROWS_PER_IMPORT = 10000;

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasContextPermission('students.create'), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $imports = StudentImport::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->with(['branch:id,name', 'academicYear:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return StudentImportResource::collection($imports);
    }

    public function store(StoreStudentImportRequest $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('students.create', $branch->school_id, $branch->id),
            403,
        );

        $import = StudentImport::create([
            ...$request->safe()->except(['branch_id']),
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'status' => StudentImportStatus::Draft->value,
            'created_by' => $request->user()->id,
        ]);

        return (new StudentImportResource($import))
            ->additional(['message' => 'Import session created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, StudentImport $import): StudentImportResource
    {
        $this->authorizeImport($request, $import);

        $import->load(['branch:id,name', 'academicYear:id,name', 'creator:id,name']);
        $import->row_stats = $import->rows()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        // What "Start import" will actually write — duplicates count only
        // once the registrar resolved them to create/enroll.
        $import->importable_count = $import->importableRows()->count();

        return new StudentImportResource($import);
    }

    /**
     * Append + validate a chunk of mapped rows. Re-sent row numbers replace
     * their previous version (retry-safe uploads). Responds with each row's
     * verdict so the grid paints statuses as chunks land.
     */
    public function appendRows(Request $request, StudentImport $import): JsonResponse
    {
        $this->authorizeImport($request, $import);
        $this->assertDraft($import);

        $payload = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS_PER_CHUNK],
            'rows.*.row_number' => ['required', 'integer', 'min:1'],
            'rows.*.data' => ['required', 'array'],
        ]);

        $incoming = collect($payload['rows'])->unique('row_number')->values();

        if ($import->rows()->count() + $incoming->count() > self::MAX_ROWS_PER_IMPORT) {
            abort(422, 'An import is limited to '.number_format(self::MAX_ROWS_PER_IMPORT).' rows — split the file.');
        }

        $validator = new StudentImportRowValidator($import->loadMissing('branch'));
        $validator->prime($incoming->pluck('data')->all());

        $results = DB::transaction(function () use ($import, $incoming, $validator) {
            $import->rows()->whereIn('row_number', $incoming->pluck('row_number'))->delete();

            return $incoming->map(function (array $row) use ($import, $validator): StudentImportRow {
                $verdict = $validator->validate($row['data']);

                return $import->rows()->create([
                    'row_number' => (int) $row['row_number'],
                    'data' => $verdict['data'],
                    'status' => $verdict['status']->value,
                    'issues' => $verdict['issues'],
                    'duplicate_student_id' => $verdict['duplicate_student_id'],
                    'resolution' => $verdict['resolution'],
                ]);
            });
        });

        $import->update(['total_rows' => $import->rows()->count()]);

        return response()->json([
            'data' => StudentImportRowResource::collection($results->each->load('duplicateStudent:id,public_id,first_name,father_name,grandfather_name')),
            'message' => 'Rows validated.',
        ]);
    }

    public function rows(Request $request, StudentImport $import): AnonymousResourceCollection
    {
        $this->authorizeImport($request, $import);

        $rows = $import->rows()
            ->when($request->filled('status'), function ($q) use ($request): void {
                $statuses = array_intersect(
                    explode(',', (string) $request->query('status')),
                    array_column(StudentImportRowStatus::cases(), 'value'),
                );
                $q->whereIn('status', $statuses === [] ? ['ready'] : $statuses);
            })
            ->with(['duplicateStudent:id,public_id,first_name,father_name,grandfather_name', 'student:id,public_id,first_name,father_name,grandfather_name'])
            ->orderBy('row_number')
            ->paginate($this->perPage($request));

        return StudentImportRowResource::collection($rows);
    }

    /**
     * Inline fix from the validation grid: new data revalidates the row; a
     * `resolution` records the registrar's duplicate decision.
     */
    public function updateRow(Request $request, StudentImport $import, StudentImportRow $row): StudentImportRowResource
    {
        $this->authorizeImport($request, $import);
        $this->assertDraft($import);
        abort_unless($row->student_import_id === $import->id, 404);

        $payload = $request->validate([
            'data' => ['sometimes', 'array'],
            'resolution' => ['sometimes', 'nullable', 'in:skip,create,enroll_existing'],
        ]);

        if (array_key_exists('data', $payload)) {
            $validator = new StudentImportRowValidator($import->loadMissing('branch'));
            $verdict = $validator->validate($payload['data']);

            $row->fill([
                'data' => $verdict['data'],
                'status' => $verdict['status']->value,
                'issues' => $verdict['issues'],
                'duplicate_student_id' => $verdict['duplicate_student_id'],
                // A fresh verdict resets the decision — the old resolution may
                // point at a match that no longer exists.
                'resolution' => $verdict['resolution'],
            ]);
        }

        if (array_key_exists('resolution', $payload)) {
            abort_unless($row->status === StudentImportRowStatus::Duplicate, 422, 'Only duplicate rows take a resolution.');
            $row->resolution = $payload['resolution'] ?? 'skip';
        }

        $row->save();

        return (new StudentImportRowResource($row->load('duplicateStudent:id,public_id,first_name,father_name,grandfather_name')))
            ->additional(['message' => 'Row updated.']);
    }

    /**
     * Freeze the toggles and queue the run. SMS defaults OFF — sending is an
     * explicit, counted decision the studio confirms separately.
     */
    public function commit(Request $request, StudentImport $import): StudentImportResource
    {
        $this->authorizeImport($request, $import);
        $this->assertDraft($import);

        $payload = $request->validate([
            'options' => ['sometimes', 'array'],
            'options.send_sms' => ['sometimes', 'boolean'],
            'options.create_student_accounts' => ['sometimes', 'boolean'],
        ]);

        abort_unless($import->importableRows()->exists(), 422, 'Nothing to import — fix or resolve at least one row first.');

        $import->update([
            'options' => [...($import->options ?? []), ...($payload['options'] ?? [])],
            'status' => StudentImportStatus::Importing->value,
            'committed_at' => now(),
        ]);

        ImportStudentsJob::dispatch($import->id);

        return (new StudentImportResource($import->refresh()))
            ->additional(['message' => 'Import started.']);
    }

    public function destroy(Request $request, StudentImport $import): JsonResponse
    {
        $this->authorizeImport($request, $import);
        abort_if($import->status === StudentImportStatus::Importing, 422, 'An import that is running cannot be deleted.');

        $import->delete();

        return response()->json(['message' => 'Import deleted.']);
    }

    private function authorizeImport(Request $request, StudentImport $import): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('students.create', $import->school_id, $import->branch_id),
            403,
        );
    }

    private function assertDraft(StudentImport $import): void
    {
        abort_unless($import->status === StudentImportStatus::Draft, 422, 'This import has already been committed.');
    }
}
