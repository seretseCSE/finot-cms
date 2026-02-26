<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance;
use App\Models\AttendanceSyncConflict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceSyncController extends Controller
{
    public function sync(Request $request)
    {
        $payload = $request->input('attendance', []);

        if (!is_array($payload)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $synced = [];
        $conflicts = [];
        $errors = [];

        foreach ($payload as $item) {
            try {
                DB::transaction(function () use ($item, &$synced, &$conflicts) {
                    $studentId = $item['student_id'];
                    $sessionId = $item['session_id'];
                    $status = $item['status'];
                    $markedAt = $item['marked_at'] ?? now()->toDateTimeString();

                    $existing = StudentAttendance::query()
                        ->where('student_id', $studentId)
                        ->where('session_id', $sessionId)
                        ->first();

                    if ($existing) {
                        // Conflict: record exists
                        $winner = $status; // last sync wins

                        AttendanceSyncConflict::create([
                            'student_id' => $studentId,
                            'session_id' => $sessionId,
                            'first_user_id' => $existing->marked_by,
                            'first_value' => $existing->status,
                            'first_synced_at' => $existing->marked_at,
                            'second_user_id' => Auth::id(),
                            'second_value' => $status,
                            'second_synced_at' => now(),
                            'winner_value' => $winner,
                        ]);

                        $existing->update([
                            'status' => $winner,
                            'marked_by' => Auth::id(),
                            'marked_at' => $markedAt,
                            'sync_timestamp' => now(),
                            'is_synced' => true,
                        ]);

                        $conflicts[] = [
                            'student_id' => $studentId,
                            'session_id' => $sessionId,
                            'first_value' => $existing->status,
                            'second_value' => $status,
                            'winner' => $winner,
                        ];
                    } else {
                        // No conflict: create new
                        StudentAttendance::create([
                            'student_id' => $studentId,
                            'session_id' => $sessionId,
                            'status' => $status,
                            'marked_by' => Auth::id(),
                            'marked_at' => $markedAt,
                            'sync_timestamp' => now(),
                            'is_synced' => true,
                        ]);

                        $synced[] = [
                            'student_id' => $studentId,
                            'session_id' => $sessionId,
                            'status' => $status,
                        ];
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = [
                    'student_id' => $item['student_id'] ?? null,
                    'session_id' => $item['session_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'synced' => $synced,
            'conflicts' => $conflicts,
            'errors' => $errors,
        ]);
    }
}
