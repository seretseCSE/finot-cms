<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A teacher's UNAVAILABLE windows (whole weekdays or period ranges) — the
 * part-timer constraint the solver honours as hard. Replaced as a set, like
 * the teaching-capability rows on the staff form.
 */
class TeacherAvailabilityController extends Controller
{
    public function index(Request $request, Employee $employee): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.view', $employee->school_id, $employee->branch_id),
            403,
        );

        return response()->json(['data' => $this->rows($employee)]);
    }

    public function replace(Request $request, Employee $employee): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.manage', $employee->school_id, $employee->branch_id),
            403,
        );

        $data = $request->validate([
            'windows' => ['present', 'array', 'max:24'],
            'windows.*.day_of_week' => ['required', 'integer', 'min:1', 'max:6'],
            'windows.*.from_period' => ['nullable', 'integer', 'min:1', 'max:15'],
            'windows.*.to_period' => ['nullable', 'integer', 'min:1', 'max:15', 'gte:windows.*.from_period'],
            'windows.*.note' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($employee, $data): void {
            $employee->availabilities()->delete();

            foreach ($data['windows'] as $window) {
                $employee->availabilities()->create([
                    'day_of_week' => $window['day_of_week'],
                    'from_period' => $window['from_period'] ?? null,
                    'to_period' => $window['to_period'] ?? null,
                    'note' => $window['note'] ?? null,
                ]);
            }
        });

        return response()->json(['data' => $this->rows($employee), 'message' => 'Availability saved.']);
    }

    /** @return list<array<string, mixed>> */
    private function rows(Employee $employee): array
    {
        return $employee->availabilities()
            ->orderBy('day_of_week')
            ->orderBy('from_period')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'day_of_week' => $w->day_of_week,
                'from_period' => $w->from_period,
                'to_period' => $w->to_period,
                'note' => $w->note,
            ])->all();
    }
}
