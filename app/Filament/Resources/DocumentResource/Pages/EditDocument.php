<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();

        // Prevent non-admins from changing department
        if (! $user?->can('documents.update')) {
            unset($data['department_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // Recalculate file size and type if file was changed
        if ($record->file_path) {
            $fileInfo = $this->calculateFileInfo($record->file_path);
            if ($fileInfo['size'] > 0 && ($fileInfo['size'] !== $record->file_size_kb || $fileInfo['type'] !== $record->file_type)) {
                $record->update([
                    'file_size_kb' => $fileInfo['size'],
                    'file_type' => $fileInfo['type'],
                ]);
            }
        }

        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'document_updated',
            'entity' => 'document',
            'resource_id' => $record->getKey(),
            'new_value' => [
                'title' => $record->title,
                'visibility' => $record->visibility,
                'department_id' => $record->department_id,
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
            Log::warning('Could not calculate file info', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'context' => 'document_edit',
                'user_id' => auth()->id(),
            ]);
        }

        return ['size' => 0, 'type' => null];
    }
}
