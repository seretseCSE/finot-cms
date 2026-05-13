<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Services\FileMetadataService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(FileMetadataService::class)->setDocumentMetadata($data, Auth::user());
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $service = app(FileMetadataService::class);

        // Calculate file size and type now that file is moved to documents/
        $fileInfo = $service->calculateFileInfo($record->file_path, 'documents');
        if ($fileInfo['size'] > 0) {
            $record->update([
                'file_size_kb' => $fileInfo['size'],
                'file_type' => $fileInfo['type'],
            ]);
        }

        $service->logDocumentCreation($record, $fileInfo);
    }
}
