<?php

namespace App\Filament\Resources\AcademicYearResource\Pages;

use App\Filament\Resources\AcademicYearResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EditAcademicYear extends EditRecord
{
    protected static string $resource = AcademicYearResource::class;

    protected function beforeSave(): void
    {
        $oldStatus = $this->record->status;
        $newStatus = $this->data['status'] ?? $oldStatus;
        
        Log::info('EditAcademicYear - Status change: ' . $oldStatus . ' -> ' . $newStatus);
        
        // Check if status is being changed to Active
        if ($oldStatus !== 'Active' && $newStatus === 'Active') {
            Log::info('EditAcademicYear - Will call ensureSingleActiveYear after save');
            $this->afterSave = function () {
                Log::info('EditAcademicYear - Calling ensureSingleActiveYear for record: ' . $this->record->id);
                AcademicYearResource::ensureSingleActiveYear($this->record);
            };
        }
    }
}

