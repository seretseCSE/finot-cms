<?php

namespace App\Filament\Resources\LibraryResource\Pages;

use App\Filament\Resources\LibraryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateLibraryResource extends CreateRecord
{
    protected static string $resource = LibraryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = Auth::id();
        // File size will be calculated after creation when file is in final location
        $data['file_size_kb'] = 0;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // Calculate file size now that file is moved to library/
        $fileSize = $this->calculateFileSize($record->file_path);
        if ($fileSize > 0) {
            $record->update(['file_size_kb' => $fileSize]);
        }

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

    private function calculateFileSize(?string $filePath): int
    {
        if (! $filePath) {
            return 0;
        }

        try {
            // Files are stored in the library disk (public)
            if (Storage::disk('library')->exists($filePath)) {
                return round(Storage::disk('library')->size($filePath) / 1024);
            }
        } catch (\Exception $e) {
            Log::warning('Could not calculate file size: '.$e->getMessage());
        }

        return 0;
    }
}
