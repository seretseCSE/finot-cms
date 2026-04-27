<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        $data['uploaded_by'] = $user?->id;

        // Auto-set department from user if not admin
        if (! $user?->hasRole(['admin', 'superadmin'])) {
            $data['department_id'] = $user?->department_id;
        }

        // File size will be calculated after creation when file is in final location
        $data['file_size_kb'] = 0;
        $data['file_type'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // Calculate file size and type now that file is moved to documents/
        $fileInfo = $this->calculateFileInfo($record->file_path);
        if ($fileInfo['size'] > 0) {
            $record->update([
                'file_size_kb' => $fileInfo['size'],
                'file_type' => $fileInfo['type'],
            ]);
        }

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

    private function calculateFileInfo(?string $filePath): array
    {
        if (! $filePath) {
            return ['size' => 0, 'type' => null];
        }

        try {
            if (Storage::disk('documents')->exists($filePath)) {
                $size = round(Storage::disk('documents')->size($filePath) / 1024);
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);

                return [
                    'size' => (int) $size,
                    'type' => strtolower($extension),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Could not calculate file info: '.$e->getMessage());
        }

        return ['size' => 0, 'type' => null];
    }
}
