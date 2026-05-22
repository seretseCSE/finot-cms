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

        if ($record->file_path) {
            $fileSize = $service->calculateFileSize($record->file_path, 'library');
            if ($fileSize > 0) {
                $record->update(['file_size_kb' => $fileSize]);
            }
        }

        $service->logFileCreation($record, $record->file_size_kb ?? 0);
    }
}
