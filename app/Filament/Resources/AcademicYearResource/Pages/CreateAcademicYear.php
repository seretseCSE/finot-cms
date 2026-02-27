<?php

namespace App\Filament\Resources\AcademicYearResource\Pages;

use App\Filament\Resources\AcademicYearResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateAcademicYear extends CreateRecord
{
    protected static string $resource = AcademicYearResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;
        
        Log::info('CreateAcademicYear - Status being set to: ' . ($data['status'] ?? 'not set'));
        
        // If status is set to Active, automatically activate the academic year
        if ($data['status'] === 'Active') {
            Log::info('CreateAcademicYear - Will call ensureSingleActiveYear after creation');
            $this->afterCreate = function ($record) {
                Log::info('CreateAcademicYear - Calling ensureSingleActiveYear for record: ' . $record->id);
                AcademicYearResource::ensureSingleActiveYear($record);
            };
        }
        
        return $data;
    }
}

