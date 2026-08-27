<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\TermPeriod;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The term's period schedule. Replacing it re-times every timetable slot at
 * once (slots point at period NUMBERS, times live here). `defaults` seeds a
 * sensible Ethiopian school day from the term's class window: periods with a
 * mid-morning break and lunch.
 */
class TermPeriodController extends Controller
{
    public function index(Request $request, Term $term): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.view', $term->school_id, $term->branch_id),
            403,
        );

        return response()->json(['data' => $this->rows($term)]);
    }

    /** Replace the whole period schedule (order = array order). */
    public function replace(Request $request, Term $term): JsonResponse
    {
        $this->authorizeManage($request, $term);
        TermGate::assertWritable($term);

        $data = $request->validate([
            'periods' => ['required', 'array', 'min:1', 'max:20'],
            'periods.*.type' => ['required', Rule::in(TermPeriod::TYPES)],
            'periods.*.label' => ['nullable', 'string', 'max:50'],
            'periods.*.starts_at' => ['required', 'date_format:H:i'],
            'periods.*.ends_at' => ['required', 'date_format:H:i', 'after:periods.*.starts_at'],
        ]);

        // Rows must be in chronological order and never overlap.
        $previousEnd = null;

        foreach ($data['periods'] as $i => $row) {
            if ($previousEnd !== null && $row['starts_at'] < $previousEnd) {
                abort(422, 'Periods must be in order and must not overlap.');
            }

            $previousEnd = $row['ends_at'];
        }

        DB::transaction(function () use ($term, $data): void {
            $term->periods()->delete();

            $sequence = 1;
            $periodNumber = 1;

            foreach ($data['periods'] as $row) {
                $term->periods()->create([
                    'sequence' => $sequence++,
                    'type' => $row['type'],
                    'period_number' => $row['type'] === 'class' ? $periodNumber++ : null,
                    'label' => $row['label'] ?? null,
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                ]);
            }
        });

        return response()->json(['data' => $this->rows($term), 'message' => 'Period schedule saved.']);
    }

    /** Seed the default bell schedule from the term's class window. */
    public function defaults(Request $request, Term $term): JsonResponse
    {
        $this->authorizeManage($request, $term);
        TermGate::assertWritable($term);

        abort_if($term->periods()->exists(), 422, 'This semester already has a bell schedule.');

        DB::transaction(function () use ($term): void {
            foreach (TermPeriod::defaultsFor($term) as $row) {
                $term->periods()->create($row);
            }
        });

        return response()->json(['data' => $this->rows($term), 'message' => 'Bell schedule created.']);
    }

    private function authorizeManage(Request $request, Term $term): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.manage', $term->school_id, $term->branch_id),
            403,
        );
    }

    /** @return list<array<string, mixed>> */
    private function rows(Term $term): array
    {
        return $term->periods()->get()->map(fn (TermPeriod $p) => [
            'id' => $p->id,
            'sequence' => $p->sequence,
            'type' => $p->type,
            'period_number' => $p->period_number,
            'label' => $p->label,
            'starts_at' => substr((string) $p->starts_at, 0, 5),
            'ends_at' => substr((string) $p->ends_at, 0, 5),
        ])->all();
    }
}
