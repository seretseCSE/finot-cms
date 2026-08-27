<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSubjectAssignmentRequest;
use App\Http\Requests\Api\V1\UpdateSubjectAssignmentRequest;
use App\Http\Resources\SubjectAssignmentResource;
use App\Models\Employee;
use App\Models\Section;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SubjectAssignmentController extends Controller
{
    public function index(Request $request, Section $section): AnonymousResourceCollection
    {
        // Judged in the SECTION's own scope — a timetable.view context alone
        // must never open another school's teaching grid by section id.
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.view', $section->school_id, $section->branch_id),
            403,
        );

        $assignments = SubjectAssignment::query()
            ->where('section_id', $section->id)
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->integer('term_id')))
            ->with(['subject', 'employee', 'timetableSlots'])
            ->get();

        return SubjectAssignmentResource::collection($assignments);
    }

    public function store(StoreSubjectAssignmentRequest $request, Section $section): JsonResponse
    {
        $this->authorize('create', SubjectAssignment::class);

        $data = $request->validated();

        // Cross-branch integrity: the term and teacher must belong to the
        // section's own branch — ids from another tenant must never attach.
        $term = Term::findOrFail($data['term_id']);
        if ((int) $term->branch_id !== (int) $section->branch_id) {
            throw ValidationException::withMessages([
                'term_id' => ['The term must belong to the section\'s branch.'],
            ]);
        }
        $this->assertEmployeeInBranch($data['employee_id'] ?? null, $section);
        TermGate::assertWritable($term);

        $assignment = SubjectAssignment::create([
            ...$data,
            'section_id' => $section->id,
            'school_id' => $section->school_id,
            'branch_id' => $section->branch_id,
            'academic_year_id' => $term->academic_year_id,
        ]);

        return (new SubjectAssignmentResource($assignment->load(['subject', 'employee'])))
            ->additional(['message' => 'Subject assigned.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSubjectAssignmentRequest $request, SubjectAssignment $subjectAssignment): SubjectAssignmentResource
    {
        $this->authorize('update', $subjectAssignment);
        TermGate::assertWritable($subjectAssignment->term);

        $data = $request->validated();
        $this->assertEmployeeInBranch($data['employee_id'] ?? null, $subjectAssignment->section);

        $subjectAssignment->update($data);

        return new SubjectAssignmentResource($subjectAssignment->load(['subject', 'employee']));
    }

    public function destroy(SubjectAssignment $subjectAssignment): JsonResponse
    {
        $this->authorize('delete', $subjectAssignment);
        TermGate::assertWritable($subjectAssignment->term);

        $subjectAssignment->delete();

        return response()->json(['message' => 'Assignment removed.']);
    }

    private function assertEmployeeInBranch(?int $employeeId, Section $section): void
    {
        if ($employeeId === null) {
            return;
        }

        $inBranch = Employee::whereKey($employeeId)
            ->where('branch_id', $section->branch_id)
            ->exists();

        if (! $inBranch) {
            throw ValidationException::withMessages([
                'employee_id' => ['The teacher must belong to the section\'s branch.'],
            ]);
        }
    }
}
