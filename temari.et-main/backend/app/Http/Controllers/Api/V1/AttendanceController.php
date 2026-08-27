<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveAttendanceAction;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Roster for a section on a given date: every actively-enrolled student with
     * their mark for the day (null when not yet taken).
     */
    public function register(Request $request, Section $section): JsonResponse
    {
        // Two lanes, mirroring store(): supervisory attendance.view reads any
        // section in scope; teachers (attendance.view_own) ONLY the sections
        // they homeroom — subject teachers never open another class register.
        $user = $request->user();
        $allowed = $user->hasPermissionForScope('attendance.view', $section->school_id, $section->branch_id)
            || ($user->hasPermissionForScope('attendance.view_own', $section->school_id, $section->branch_id)
                && $section->isHomeroomedBy($user));

        abort_unless($allowed, 403);

        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        $enrollments = $section->enrollments()
            ->where('status', EnrollmentStatus::Active->value)
            ->with('student')
            ->get()
            ->filter(fn ($enrollment) => $enrollment->student !== null);

        $marks = AttendanceRecord::where('section_id', $section->id)
            ->where('date', $date)
            ->get()
            ->keyBy('student_id');

        $roster = $enrollments
            ->map(function ($enrollment) use ($marks): array {
                $mark = $marks->get($enrollment->student_id);

                return [
                    'student_id' => $enrollment->student_id,
                    'student_name' => $enrollment->student->full_name,
                    'status' => $mark?->status->value,
                    'source' => $mark?->source,
                    'check_in' => $mark?->check_in ? substr((string) $mark->check_in, 0, 5) : null,
                    'check_out' => $mark?->check_out ? substr((string) $mark->check_out, 0, 5) : null,
                    'note' => $mark?->note,
                ];
            })
            ->sortBy('student_name')
            ->values();

        return response()->json([
            'data' => $roster,
            'meta' => ['date' => $date, 'section_id' => $section->id],
        ]);
    }

    public function store(
        StoreAttendanceRequest $request,
        Section $section,
        SaveAttendanceAction $action,
    ): JsonResponse {
        // Two lanes (ADR-011): supervisory staff record anywhere in scope;
        // teachers (`attendance.record_own`) only for the sections they
        // HOMEROOM — daily attendance is the homeroom teacher's register.
        $user = $request->user();
        $allowed = $user->hasPermissionForScope('attendance.record', $section->school_id, $section->branch_id)
            || ($user->hasPermissionForScope('attendance.record_own', $section->school_id, $section->branch_id)
                && $section->isHomeroomedBy($user));

        abort_unless($allowed, 403);

        $records = $action->execute($section, $request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Attendance saved.',
            'meta' => ['saved' => $records->count(), 'date' => $request->validated()['date']],
        ]);
    }
}
