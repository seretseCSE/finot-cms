<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAttachment;
use App\Models\StudentTransferRequest;
use App\Services\ProfilePhotoSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Student documents (birth certificate, IDs, transfer letters…). Files live
 * privately on R2 under the student's folder; responses only ever expose
 * signed URLs. Gated by StudentPolicy@update — whoever may edit the student
 * manages their files. Also hosts the photo upload (avatar pattern).
 */
class StudentAttachmentController extends Controller
{
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
            ],
        ]);

        $path = $request->file('file')->store(
            "student-attachments/{$student->id}",
            ['disk' => config('filesystems.default')],
        );

        [$schoolId, $branchId] = $this->provenanceScope($request, $student);

        $attachment = $student->attachments()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'path' => $path,
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize(),
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'uploaded_by' => $request->user()->id,
        ]);

        $attachment->load('branch:id,name', 'uploader:id,name');

        return response()->json([
            'data' => [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'category' => $attachment->category,
                'url' => $attachment->url(),
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'school_id' => $attachment->school_id,
                'branch_name' => $attachment->branch?->name,
                'uploaded_by_name' => $attachment->uploader?->name,
                'created_at' => $attachment->created_at,
            ],
            'message' => 'Attachment uploaded.',
        ], 201);
    }

    public function destroy(Request $request, Student $student, StudentAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $student);

        abort_unless($attachment->student_id === $student->id, 404);

        // Provenance guard (ADR-017): a school may discard only its OWN
        // paperwork — documents another school placed in the file are that
        // school's certified copies, never yours to destroy.
        $this->assertProvenanceAllowsDeletion($request, $student, $attachment);

        if ($this->referencedByHandoverSnapshot($attachment)) {
            // Part of a former school's frozen file: hide from the live
            // record but keep the row AND the R2 object — era archives must
            // keep opening their copy (the paper-world photocopy).
            $attachment->delete();
        } else {
            Storage::disk(config('filesystems.default'))->delete($attachment->path);
            $attachment->forceDelete();
        }

        return response()->json(['message' => 'Attachment removed.']);
    }

    public function photo(Request $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $request->validate([
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        if ($student->photo_path !== null) {
            Storage::disk(config('filesystems.default'))->delete($student->photo_path);
        }

        $path = $request->file('photo')->store(
            "student-photos/{$student->id}",
            ['disk' => config('filesystems.default')],
        );

        $student->forceFill(['photo_path' => $path])->save();

        // The linked login account shows the same picture (avatar follows).
        ProfilePhotoSync::sync($student);

        return response()->json([
            'data' => ['photo_url' => $student->photo_url],
            'message' => 'Photo updated.',
        ]);
    }

    /**
     * Rule: only the school that ADDED a document (its provenance stamp) may
     * delete it. Platform staff may clean anything; legacy rows with no stamp
     * fall to whoever holds custody.
     */
    private function assertProvenanceAllowsDeletion(Request $request, Student $student, StudentAttachment $attachment): void
    {
        $user = $request->user();

        if ($attachment->school_id === null
            || $user->isSuperAdmin()
            || $user->hasPlatformPermission('students.update')) {
            return;
        }

        $ownSchoolIds = collect($student->activeAdminScopes())
            ->filter(fn (array $scope): bool => $user->hasPermissionForScope('students.update', $scope[0], $scope[1]))
            ->pluck(0)
            ->filter();

        abort_unless(
            $ownSchoolIds->contains((int) $attachment->school_id),
            403,
            'Only the school that added this document may remove it.',
        );
    }

    /**
     * True when the document is part of at least one handover snapshot — a
     * former school's frozen file references it, so it must never be
     * destroyed, only hidden (JSONB containment on the snapshot's id list).
     */
    private function referencedByHandoverSnapshot(StudentAttachment $attachment): bool
    {
        return StudentTransferRequest::query()
            ->where('student_id', $attachment->student_id)
            ->whereNotNull('handover_snapshot')
            ->whereRaw("handover_snapshot->'attachments' @> ?::jsonb", [json_encode([['id' => $attachment->id]])])
            ->exists();
    }

    /**
     * The custody scope this upload is filed under: the first live scope in
     * which the actor holds students.update. Platform staff acting on a
     * scopeless (B2C) student stamp nothing.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function provenanceScope(Request $request, Student $student): array
    {
        $scopes = $student->activeAdminScopes();

        foreach ($scopes as [$schoolId, $branchId]) {
            if ($request->user()->hasPermissionForScope('students.update', $schoolId, $branchId)) {
                return [$schoolId, $branchId];
            }
        }

        // Super admins pass the policy without matching a scope — file the
        // document under the student's primary custody scope.
        return $scopes[0] ?? [null, null];
    }
}
