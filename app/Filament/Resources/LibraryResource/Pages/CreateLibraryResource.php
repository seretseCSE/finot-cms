<?php

namespace App\Filament\Resources\LibraryResource\Pages;

use App\Filament\Resources\LibraryResource;
use App\Services\FileMetadataService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateLibraryResource extends CreateRecord
{
    protected static string $resource = LibraryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(FileMetadataService::class)->setInitialMetadata($data, Auth::id());
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $service = app(FileMetadataService::class);

        // Calculate file size now that file is moved to library/
        $fileSize = $service->calculateFileSize($record->file_path, 'library');
        if ($fileSize > 0) {
            $record->update(['file_size_kb' => $fileSize]);
        }

        $service->logFileCreation($record, $fileSize);
    }
}
