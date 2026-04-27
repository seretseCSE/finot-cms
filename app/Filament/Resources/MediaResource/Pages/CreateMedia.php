<?php

namespace App\Filament\Resources\MediaResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\MediaResource;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $paths = $data['file_path'];

        // If it's not an array (single upload), just proceed normally
        if (!is_array($paths)) {
            return parent::handleRecordCreation($data);
        }

        unset($data['file_path']);
        $record = null;

        foreach ($paths as $index => $path) {
            // Create a record for each file
            // We can optionally modify the title for subsequent files,
            // but for now we'll keep the same title/category/etc.
            $recordData = $data;
            $recordData['file_path'] = $path;

            // Auto-detect type from file extension
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $recordData['type'] = in_array($extension, $imageExtensions, true) ? 'Photo' : 'Video';

            // Add file size from stored file
            try {
                $fullPath = storage_path('app/public/' . $path);
                if (file_exists($fullPath)) {
                    $recordData['file_size_kb'] = round(filesize($fullPath) / 1024); // Convert bytes to KB
                } else {
                    $recordData['file_size_kb'] = 0;
                }
            } catch (\Exception $e) {
                // Handle metadata retrieval errors gracefully
                $recordData['file_size_kb'] = 0;
                \Log::warning('File metadata retrieval failed', [
                    'file' => $path,
                    'error' => $e->getMessage(),
                    'index' => $index,
                ]);
            }

            // If it's not the first file, maybe append a number to the title?
            if ($index > 0) {
                $recordData['title'] = $data['title'] . ' (' . ($index + 1) . ')';
            }

            $record = static::getModel()::create($recordData);
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
