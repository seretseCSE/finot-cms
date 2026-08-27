<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceNotificationLog;
use App\Support\Ethiopia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The alert ledger: which guardian was texted/emailed about which mark — and,
 * because rows are never deleted, the school's SMS meter (this-month count in
 * meta).
 */
class AttendanceNotificationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('attendance.view'), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);
        $branchFilterId = $this->branchFilterId($request, $branch);

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'channel' => ['nullable', Rule::in(['sms', 'email'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $scope = fn ($q) => $q
            ->when($branch, fn ($qq) => $qq->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($qq) => $qq->where('school_id', $schoolScopeId))
            ->when($request->filled('school_id'), fn ($qq) => $qq->where('school_id', $request->integer('school_id')))
            ->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($branchFilterId, fn ($qq) => $qq->where('branch_id', $branchFilterId));

        $logs = AttendanceNotificationLog::query()
            ->tap($scope)
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('date', $date))
            ->when($filters['channel'] ?? null, fn ($q, $channel) => $q->where('channel', $channel))
            ->with(['student:id,first_name,father_name,grandfather_name', 'guardianUser:id,name'])
            ->latest('id')
            ->paginate(min((int) ($filters['per_page'] ?? 25), 100));

        $monthStart = Ethiopia::now()->startOfMonth()->toDateString();

        return response()->json([
            'data' => collect($logs->items())->map(fn (AttendanceNotificationLog $log) => [
                'id' => $log->id,
                'date' => $log->date,
                'student_id' => $log->student_id,
                'student_name' => $log->student?->full_name,
                'guardian_name' => $log->guardianUser?->name,
                'status' => $log->status,
                'channel' => $log->channel,
                'recipient' => $log->recipient,
                'result' => $log->result,
                'sent_at' => $log->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
                'sms_this_month' => AttendanceNotificationLog::query()
                    ->tap($scope)
                    ->where('channel', 'sms')
                    ->where('date', '>=', $monthStart)
                    ->count(),
            ],
        ]);
    }
}
