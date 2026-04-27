<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OfflineAttendanceController extends Controller
{
    public function __construct(
        private readonly OfflineSyncService $syncService
    ) {
    }

    /**
     * Queue offline attendance records for sync.
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'records' => ['required', 'array'],
            'records.*.session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
            'records.*.status' => ['required', 'in:Present,Absent,Excused,Late,Permission'],
            'records.*.student_id' => ['nullable', 'integer', 'exists:members,id'],
            'records.*.member_id' => ['nullable', 'integer', 'exists:members,id'],
            'records.*.marked_at' => ['nullable', 'date'],
            'process_now' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $records = $this->syncService->queueAttendanceSync(
            Auth::id(),
            $request->input('records')
        );

        $results = [
            'success' => true,
            'message' => count($records).' records queued for sync.',
            'queued' => count($records),
        ];

        if ($request->boolean('process_now')) {
            $processed = $this->syncService->processPendingSyncs();
            $results['processed'] = $processed;
        }

        return response()->json($results)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Get sync status for the current user.
     */
    public function status(): JsonResponse
    {
        $pending = \App\Models\OfflineAttendanceSync::query()
            ->where('user_id', Auth::id())
            ->where('sync_status', 'pending')
            ->count();

        $synced = \App\Models\OfflineAttendanceSync::query()
            ->where('user_id', Auth::id())
            ->where('sync_status', 'synced')
            ->count();

        $conflicts = \App\Models\OfflineAttendanceSync::query()
            ->where('user_id', Auth::id())
            ->where('sync_status', 'conflict')
            ->count();

        return response()->json([
            'success' => true,
            'pending' => $pending,
            'synced' => $synced,
            'conflicts' => $conflicts,
        ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Manually trigger processing of pending syncs.
     */
    public function process(): JsonResponse
    {
        $results = $this->syncService->processPendingSyncs();

        return response()->json([
            'success' => true,
            'message' => 'Pending syncs processed.',
            'synced' => $results['synced'],
            'conflicts' => $results['conflicts'],
        ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
