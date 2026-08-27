<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\ParentAttachment;
use App\Models\ParentProfile;
use App\Services\ProfilePhotoSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Guardian documents (ID, custody letters…) + photo. A parent is a global
 * person, so staff authority flows through their linked children: the actor
 * needs guardians.manage in at least one scope holding LIVE custody of a
 * linked child (ParentProfile::activeAdminScopes()) — former schools keep
 * zero write access to a family's files.
 */
class ParentAttachmentController extends Controller
{
    public function store(Request $request, ParentProfile $parent): JsonResponse
    {
        $this->authorizeManage($request, $parent);

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
            "parent-attachments/{$parent->id}",
            ['disk' => config('filesystems.default')],
        );

        [$schoolId, $branchId] = $this->provenanceScope($request, $parent);

        $attachment = $parent->attachments()->create([
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
                'branch_name' => $attachment->branch?->name,
                'uploaded_by_name' => $attachment->uploader?->name,
                'created_at' => $attachment->created_at,
            ],
            'message' => 'Attachment uploaded.',
        ], 201);
    }

    public function destroy(Request $request, ParentProfile $parent, ParentAttachment $attachment): JsonResponse
    {
        $this->authorizeManage($request, $parent);

        abort_unless($attachment->parent_id === $parent->id, 404);

        // Provenance guard (ADR-017): only the school that ADDED a family
        // document may remove it — mirrors student attachments.
        $user = $request->user();
        if ($attachment->school_id !== null
            && ! $user->isSuperAdmin()
            && ! $user->hasPlatformPermission('guardians.manage')) {
            $ownSchoolIds = collect($parent->activeAdminScopes())
                ->filter(fn (array $scope): bool => $user->hasPermissionForScope('guardians.manage', $scope[0], $scope[1]))
                ->pluck(0)
                ->filter();

            abort_unless(
                $ownSchoolIds->contains((int) $attachment->school_id),
                403,
                'Only the school that added this document may remove it.',
            );
        }

        Storage::disk(config('filesystems.default'))->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment removed.']);
    }

    public function photo(Request $request, ParentProfile $parent): JsonResponse
    {
        $this->authorizeManage($request, $parent);

        $request->validate([
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        if ($parent->photo_path !== null) {
            Storage::disk(config('filesystems.default'))->delete($parent->photo_path);
        }

        $path = $request->file('photo')->store(
            "parent-photos/{$parent->id}",
            ['disk' => config('filesystems.default')],
        );

        $parent->forceFill(['photo_path' => $path])->save();

        // The linked login account shows the same picture (avatar follows).
        ProfilePhotoSync::sync($parent);

        return response()->json([
            'data' => ['photo_url' => $parent->photo_url],
            'message' => 'Photo updated.',
        ]);
    }

    private function authorizeManage(Request $request, ParentProfile $parent): void
    {
        foreach ($parent->activeAdminScopes() as [$schoolId, $branchId]) {
            if ($request->user()->hasPermissionForScope('guardians.manage', $schoolId, $branchId)) {
                return;
            }
        }

        abort(403, 'You are not permitted to manage this guardian\'s files.');
    }

    /**
     * The custody scope this upload is filed under: the first live scope in
     * which the actor holds guardians.manage.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function provenanceScope(Request $request, ParentProfile $parent): array
    {
        $scopes = $parent->activeAdminScopes();

        foreach ($scopes as [$schoolId, $branchId]) {
            if ($request->user()->hasPermissionForScope('guardians.manage', $schoolId, $branchId)) {
                return [$schoolId, $branchId];
            }
        }

        return $scopes[0] ?? [null, null];
    }
}
