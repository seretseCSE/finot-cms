<?php

namespace App\Filament\Resources\AcademicYearResource\Pages;

use App\Filament\Resources\AcademicYearResource;
use App\Services\AcademicYearService;
use Filament\Resources\Pages\EditRecord;

class EditAcademicYear extends EditRecord
{
    protected static string $resource = AcademicYearResource::class;

    protected function beforeSave(): void
    {
        $oldStatus = $this->record->status;
        $newStatus = $this->data['status'] ?? $oldStatus;

        app(AcademicYearService::class)->handleStatusChange($this->record, $newStatus);
    }
}
