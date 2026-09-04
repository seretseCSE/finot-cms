<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceEvent;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The scan log: every raw tap with its processing outcome — how admins see
 * that a device is alive, that a card resolved, or why a scan was rejected
 * (unknown/lost card, no enrollment, closed term).
 */
class DeviceEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('devices.view'), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);
        $branchFilterId = $this->branchFilterId($request, $branch);

        $filters = $request->validate([
            'device_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([
                DeviceEvent::STATUS_PENDING, DeviceEvent::STATUS_PROCESSED,
                DeviceEvent::STATUS_UNKNOWN_CARD, DeviceEvent::STATUS_INACTIVE_CARD,
                DeviceEvent::STATUS_NO_ENROLLMENT, DeviceEvent::STATUS_CLOSED_TERM,
            ])],
            'date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = DeviceEvent::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->when($filters['device_id'] ?? null, fn ($q, $id) => $q->where('device_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('scanned_at', $date))
            ->with(['device:id,name', 'holder'])
            ->orderByDesc('scanned_at')
            ->paginate(min((int) ($filters['per_page'] ?? 25), 100));

        return response()->json([
            'data' => collect($events->items())->map(fn (DeviceEvent $e) => [
                'id' => $e->id,
                'device_id' => $e->device_id,
                'device_name' => $e->device?->name,
                'card_uid' => $e->card_uid,
                'holder_type' => $e->holder instanceof Employee ? 'employee' : ($e->holder ? 'student' : null),
                'holder_name' => $e->holder?->full_name,
                'scanned_at' => $e->scanned_at->toIso8601String(),
                'received_at' => $e->received_at->toIso8601String(),
                'status' => $e->status,
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}
