<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Branch facilities (labs, gym, library…) the timetable books as exclusive
 * resources. Branch-anchored writes follow the school-wide targeting pattern
 * (explicit branch_id beats the X-Branch-Id context).
 */
class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasContextPermission('timetable.view'), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);
        $branchFilterId = $this->branchFilterId($request, $branch);

        $rooms = Room::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rooms->map(fn (Room $r) => $this->row($r))]);
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('timetable.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('rooms', 'name')->where('branch_id', $branch->id)->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::in(Room::TYPES)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $room = Room::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'capacity' => $data['capacity'] ?? null,
        ]);

        return response()->json(['data' => $this->row($room), 'message' => 'Room added.'], 201);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.manage', $room->school_id, $room->branch_id),
            403,
        );

        $data = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:80',
                Rule::unique('rooms', 'name')->where('branch_id', $room->branch_id)->whereNull('deleted_at')->ignore($room->id),
            ],
            'type' => ['sometimes', 'required', Rule::in(Room::TYPES)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $room->update($data);

        return response()->json(['data' => $this->row($room), 'message' => 'Room updated.']);
    }

    public function destroy(Request $request, Room $room): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.manage', $room->school_id, $room->branch_id),
            403,
        );

        $room->delete();

        return response()->json(['message' => 'Room deleted.']);
    }

    /** @return array<string, mixed> */
    private function row(Room $room): array
    {
        return [
            'id' => $room->id,
            'branch_id' => $room->branch_id,
            'name' => $room->name,
            'type' => $room->type,
            'capacity' => $room->capacity,
            'is_active' => $room->is_active,
        ];
    }
}
