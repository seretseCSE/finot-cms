<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileMetadataService
{
    /**
     * Set initial metadata for library resource creation.
     *
     * @param array $data The form data
     * @param int $userId The user ID
     * @return array The modified data
     */
    public function setInitialMetadata(array $data, int $userId): array
    {
        $data['uploaded_by'] = $userId;
        // File size will be calculated after creation when file is in final location
        $data['file_size_kb'] = 0;

        return $data;
    }

    /**
     * Set document metadata including department logic.
     *
     * @param array $data The form data
     * @param \App\Models\User $user The authenticated user
     * @return array The modified data
     */
    public function setDocumentMetadata(array $data, $user): array
    {
        $data['uploaded_by'] = $user?->id;

        // Auto-set department from user if not admin
        if (!$user?->hasRole(['admin', 'superadmin'])) {
            $data['department_id'] = $user?->department_id;
        }

        // File size will be calculated after creation when file is in final location
        $data['file_size_kb'] = 0;
        $data['file_type'] = null;

        return $data;
    }

    /**
     * Calculate file size in KB.
     *
     * @param string|null $filePath The file path
     * @param string $disk The storage disk
     * @return int The file size in KB
     */
    public function calculateFileSize(?string $filePath, string $disk): int
    {
        if (!$filePath) {
            return 0;
        }

        try {
            if (Storage::disk($disk)->exists($filePath)) {
                return round(Storage::disk($disk)->size($filePath) / 1024);
            }
        } catch (\Exception $e) {
            Log::warning('Could not calculate file size', [
                'disk' => $disk,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'context' => 'file_metadata',
                'user_id' => auth()->id(),
            ]);
        }

        return 0;
    }

    /**
     * Calculate file info (size and type).
     *
     * @param string|null $filePath The file path
     * @param string $disk The storage disk
     * @return array The file info with 'size' and 'type' keys
     */
    public function calculateFileInfo(?string $filePath, string $disk): array
    {
        if (!$filePath) {
            return ['size' => 0, 'type' => null];
        }

        try {
            if (Storage::disk($disk)->exists($filePath)) {
                $size = round(Storage::disk($disk)->size($filePath) / 1024);
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);

                return [
                    'size' => (int) $size,
                    'type' => strtolower($extension),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Could not calculate file info', [
                'disk' => $disk,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'context' => 'file_metadata',
                'user_id' => auth()->id(),
            ]);
        }

        return ['size' => 0, 'type' => null];
    }

    /**
     * Log file creation for library resource.
     *
     * @param mixed $record The library resource record
     * @param int $fileSize The file size in KB
     * @return void
     */
    public function logFileCreation($record, int $fileSize): void
    {
        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'library_resource_created',
            'entity' => 'library_resource',
            'resource_id' => $record->getKey(),
            'new_value' => [
                'title' => $record->title,
                'category_id' => $record->category_id,
                'subcategory_id' => $record->subcategory_id,
                'file_size_kb' => $fileSize,
            ],
            'performed_by' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log document creation.
     *
     * @param mixed $record The document record
     * @param array $fileInfo The file info with 'size' and 'type' keys
     * @return void
     */
    public function logDocumentCreation($record, array $fileInfo): void
    {
        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'document_created',
            'entity' => 'document',
            'resource_id' => $record->getKey(),
            'new_value' => [
                'title' => $record->title,
                'department_id' => $record->department_id,
                'visibility' => $record->visibility,
                'file_size_kb' => $fileInfo['size'],
            ],
            'performed_by' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
