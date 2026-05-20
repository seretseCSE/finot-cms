<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\RehearsalAttendance;
use App\Models\StudentAttendance;
use App\Models\TeacherAttendance;
use App\Models\TourAttendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkAttendanceService
{
    /**
     * Bulk create or update student attendance records
     */
    public function bulkUpdateStudentAttendance(int $sessionId, array $attendanceData): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        try {
            DB::transaction(function () use ($sessionId, $attendanceData, &$results) {
                foreach ($attendanceData as $studentId => $data) {
                    if (empty($data['status'])) {
                        continue; // Skip if no status is set
                    }

                    try {
                        $attendance = StudentAttendance::updateOrCreate(
                            ['student_id' => $studentId, 'session_id' => $sessionId],
                            [
                                'status' => $data['status'],
                                'marked_by' => Auth::id(),
                                'marked_at' => now(),
                            ]
                        );

                        if ($attendance->wasRecentlyCreated) {
                            $results['created']++;
                        } else {
                            $results['updated']++;
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Student ID {$studentId}: " . $e->getMessage();
                    }
                }
            });

            $this->logBulkOperation('student_attendance', $sessionId, $results);

        } catch (\Exception $e) {
            Log::error('Bulk student attendance update failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Bulk create or update teacher attendance records
     */
    public function bulkUpdateTeacherAttendance(int $sessionId, array $attendanceData): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        try {
            DB::transaction(function () use ($sessionId, $attendanceData, &$results) {
                foreach ($attendanceData as $assignmentId => $data) {
                    if (empty($data['attendance_status'])) {
                        continue; // Skip if no status is set
                    }

                    try {
                        $attendance = TeacherAttendance::updateOrCreate(
                            ['teacher_assignment_id' => $assignmentId, 'session_id' => $sessionId],
                            [
                                'attendance_status' => $data['attendance_status'],
                                'notes' => $data['notes'] ?? null,
                                'marked_by' => Auth::id(),
                                'marked_at' => now(),
                            ]
                        );

                        if ($attendance->wasRecentlyCreated) {
                            $results['created']++;
                        } else {
                            $results['updated']++;
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Assignment ID {$assignmentId}: " . $e->getMessage();
                    }
                }
            });

            $this->logBulkOperation('teacher_attendance', $sessionId, $results);

        } catch (\Exception $e) {
            Log::error('Bulk teacher attendance update failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Bulk create or update rehearsal attendance records
     */
    public function bulkUpdateRehearsalAttendance(int $rehearsalId, array $attendanceData): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        try {
            DB::transaction(function () use ($rehearsalId, $attendanceData, &$results) {
                foreach ($attendanceData as $memberId => $data) {
                    if (empty($data['status'])) {
                        continue; // Skip if no status is set
                    }

                    try {
                        $attendance = RehearsalAttendance::updateOrCreate(
                            ['rehearsal_id' => $rehearsalId, 'member_id' => $memberId],
                            [
                                'status' => $data['status'],
                                'marked_by' => Auth::id(),
                                'marked_at' => now(),
                            ]
                        );

                        if ($attendance->wasRecentlyCreated) {
                            $results['created']++;
                        } else {
                            $results['updated']++;
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Member ID {$memberId}: " . $e->getMessage();
                    }
                }
            });

            $this->logBulkOperation('rehearsal_attendance', $rehearsalId, $results);

        } catch (\Exception $e) {
            Log::error('Bulk rehearsal attendance update failed', [
                'rehearsal_id' => $rehearsalId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Bulk create or update tour attendance records
     */
    public function bulkUpdateTourAttendance(int $sessionId, array $attendanceData): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        try {
            DB::transaction(function () use ($sessionId, $attendanceData, &$results) {
                foreach ($attendanceData as $passengerId => $data) {
                    if (empty($data['status'])) {
                        continue; // Skip if no status is set
                    }

                    try {
                        $attendance = TourAttendance::updateOrCreate(
                            ['session_id' => $sessionId, 'passenger_id' => $passengerId],
                            [
                                'status' => $data['status'],
                                'notes' => $data['notes'] ?? null,
                                'marked_by' => Auth::id(),
                                'marked_at' => now(),
                            ]
                        );

                        if ($attendance->wasRecentlyCreated) {
                            $results['created']++;
                        } else {
                            $results['updated']++;
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Passenger ID {$passengerId}: " . $e->getMessage();
                    }
                }
            });

            $this->logBulkOperation('tour_attendance', $sessionId, $results);

        } catch (\Exception $e) {
            Log::error('Bulk tour attendance update failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Bulk create or update general member attendance records
     */
    public function bulkUpdateGeneralAttendance(string $eventType, string $eventDate, array $attendanceData): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        try {
            DB::transaction(function () use ($eventType, $eventDate, $attendanceData, &$results) {
                foreach ($attendanceData as $memberId => $data) {
                    if (empty($data['status'])) {
                        continue; // Skip if no status is set
                    }

                    try {
                        $attendance = AttendanceRecord::updateOrCreate(
                            [
                                'member_id' => $memberId,
                                'event_type' => $eventType,
                                'event_date' => $eventDate,
                            ],
                            [
                                'status' => $data['status'],
                                'notes' => $data['notes'] ?? null,
                                'created_by' => Auth::id(),
                            ]
                        );

                        if ($attendance->wasRecentlyCreated) {
                            $results['created']++;
                        } else {
                            $results['updated']++;
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Member ID {$memberId}: " . $e->getMessage();
                    }
                }
            });

            $this->logBulkOperation('general_attendance', $eventType . ':' . $eventDate, $results);

        } catch (\Exception $e) {
            Log::error('Bulk general attendance update failed', [
                'event_type' => $eventType,
                'event_date' => $eventDate,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Get attendance summary for different attendance types
     */
    public function getAttendanceSummary(string $type, int $recordId): array
    {
        switch ($type) {
            case 'student':
                $attendances = StudentAttendance::where('session_id', $recordId)->lazy();
                break;
            case 'teacher':
                $attendances = TeacherAttendance::where('session_id', $recordId)->lazy();
                break;
            case 'rehearsal':
                $attendances = RehearsalAttendance::where('rehearsal_id', $recordId)->lazy();
                break;
            case 'tour':
                $attendances = TourAttendance::where('session_id', $recordId)->lazy();
                break;
            default:
                return [];
        }

        $statusCounts = $attendances->groupBy('status')->map->count();

        return [
            'total' => $attendances->count(),
            'present' => $statusCounts->get('Present', 0),
            'absent' => $statusCounts->get('Absent', 0),
            'late' => $statusCounts->get('Late', 0),
            'excused' => $statusCounts->get('Excused', 0),
            'permission' => $statusCounts->get('Permission', 0),
            'not_present' => $statusCounts->get('Not Present', 0),
        ];
    }

    /**
     * Log bulk operations for audit trail
     */
    private function logBulkOperation(string $attendanceType, string $recordId, array $results): void
    {
        Log::channel('audit')->info('Bulk Attendance Operation', [
            'tier' => 2,
            'action' => 'bulk_attendance_update',
            'attendance_type' => $attendanceType,
            'record_id' => $recordId,
            'results' => $results,
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Validate attendance data before bulk operations
     */
    public function validateAttendanceData(array $attendanceData, array $validStatuses): array
    {
        $errors = [];

        foreach ($attendanceData as $id => $data) {
            if (!empty($data['status']) && !in_array($data['status'], $validStatuses)) {
                $errors[] = "Invalid status '{$data['status']}' for ID {$id}";
            }
        }

        return $errors;
    }
}
