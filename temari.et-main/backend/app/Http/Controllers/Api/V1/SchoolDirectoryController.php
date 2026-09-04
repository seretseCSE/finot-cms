<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolDirectoryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The platform-wide Ethiopian school directory (see the migration for the
 * catalog's rationale). Reads are open to any authenticated staff context;
 * inline additions require students.create in the active context and land
 * unverified with provenance; verify/edit/delete are platform-only.
 */
class SchoolDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $entries = SchoolDirectoryEntry::query()
            ->when($query !== '', function ($q) use ($query): void {
                $q->where(function ($w) use ($query): void {
                    $w->where('name', 'ilike', "%{$query}%")
                        ->orWhere('city', 'ilike', "%{$query}%");
                });
            })
            ->orderByDesc('is_verified')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'region', 'zone', 'city', 'school_id', 'is_verified']);

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('students.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'zone' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        // Reuse an existing row on exact name+city match instead of duplicating.
        $existing = SchoolDirectoryEntry::query()
            ->where('name', 'ilike', $data['name'])
            ->when(! empty($data['city']), fn ($q) => $q->where('city', 'ilike', $data['city']))
            ->first();

        if ($existing !== null) {
            return response()->json(['data' => $existing, 'message' => 'School already in the directory.']);
        }

        $entry = SchoolDirectoryEntry::create([
            ...$data,
            'is_verified' => false,
            'created_by_school_id' => $request->user()->activeSchoolId(),
        ]);

        return response()->json(['data' => $entry, 'message' => 'School added to the directory.'])
            ->setStatusCode(201);
    }

    public function update(Request $request, SchoolDirectoryEntry $entry): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('platform.access'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'zone' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_verified' => ['sometimes', 'boolean'],
        ]);

        $entry->update($data);

        return response()->json(['data' => $entry, 'message' => 'Directory entry updated.']);
    }

    public function verify(Request $request, SchoolDirectoryEntry $entry): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('platform.access'), 403);

        $entry->update(['is_verified' => true]);

        return response()->json(['data' => $entry, 'message' => 'Directory entry verified.']);
    }

    public function destroy(Request $request, SchoolDirectoryEntry $entry): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('platform.access'), 403);
        abort_if($entry->school_id !== null, 422, 'Entries for schools hosted on Temari cannot be deleted.');

        $entry->delete();

        return response()->json(['message' => 'Directory entry deleted.']);
    }
}
