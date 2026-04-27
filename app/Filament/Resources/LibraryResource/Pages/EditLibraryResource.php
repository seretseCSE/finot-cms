<?php

namespace App\Filament\Resources\LibraryResource\Pages;

use App\Filament\Resources\LibraryResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditLibraryResource extends EditRecord
{
    protected static string $resource = LibraryResource::class;

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // Recalculate file size if file was changed
        if ($record->file_path) {
            $fileSize = $this->calculateFileSize($record->file_path);
            if ($fileSize > 0 && $fileSize !== $record->file_size_kb) {
                $record->update(['file_size_kb' => $fileSize]);
            }
        }

        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'library_resource_updated',
            'entity' => 'library_resource',
            'resource_id' => $record->getKey(),
            'new_value' => [
                'title' => $record->title,
                'is_active' => $record->is_active,
                'is_featured' => $record->is_featured,
            ],
            'performed_by' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    private function calculateFileSize(?string $filePath): int
    {
        if (! $filePath) {
            return 0;
        }

        try {
            if (Storage::disk('library')->exists($filePath)) {
                return round(Storage::disk('library')->size($filePath) / 1024);
            }
        } catch (\Exception $e) {
            Log::warning('Could not calculate file size: '.$e->getMessage());
        }

        return 0;
    }
}
