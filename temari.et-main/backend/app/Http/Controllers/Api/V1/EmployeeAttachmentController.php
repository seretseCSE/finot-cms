<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttachment;
use App\Services\ProfilePhotoSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Staff documents (credentials, IDs, contracts…). Files live privately on R2
 * under the branch's employee folder; responses only ever expose signed URLs.
 * A file may anchor to a specific position (contract) or qualification (degree
 * scan) of the same employee. Gated by EmployeePolicy@update — whoever may edit
 * the profile manages its files. Also hosts the photo upload (avatar pattern).
 */
class EmployeeAttachmentController extends Controller
{
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_position_id' => [
                'nullable', 'integer',
                Rule::exists('employee_positions', 'id')->where('employee_id', $employee->id),
            ],
            'employee_qualification_id' => [
                'nullable', 'integer',
                Rule::exists('employee_qualifications', 'id')->where('employee_id', $employee->id),
            ],
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
            ],
        ]);

        $path = $request->file('file')->store(
            "employee-attachments/{$employee->id}",
            ['disk' => config('filesystems.default')],
        );

        $attachment = $employee->attachments()->create([
            'name' => $data['name'],
            'employee_position_id' => $data['employee_position_id'] ?? null,
            'employee_qualification_id' => $data['employee_qualification_id'] ?? null,
            'path' => $path,
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize(),
        ]);

        return response()->json([
            'data' => [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'url' => $attachment->url(),
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'employee_position_id' => $attachment->employee_position_id,
                'employee_qualification_id' => $attachment->employee_qualification_id,
                'created_at' => $attachment->created_at,
            ],
            'message' => 'Attachment uploaded.',
        ], 201);
    }

    public function photo(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $request->validate([
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        if ($employee->photo_path !== null) {
            Storage::disk(config('filesystems.default'))->delete($employee->photo_path);
        }

        $path = $request->file('photo')->store(
            "employee-photos/{$employee->id}",
            ['disk' => config('filesystems.default')],
        );

        $employee->forceFill(['photo_path' => $path])->save();

        // The linked login account shows the same picture (avatar follows).
        ProfilePhotoSync::sync($employee);

        return response()->json([
            'data' => ['photo_url' => $employee->photo_url],
            'message' => 'Photo updated.',
        ]);
    }

    public function destroy(Employee $employee, EmployeeAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $employee);

        abort_unless($attachment->employee_id === $employee->id, 404);

        Storage::disk(config('filesystems.default'))->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment removed.']);
    }
}
