<?php

namespace App\Filament\Resources\AcademicYearResource\Pages;

use App\Filament\Resources\AcademicYearResource;
use App\Services\AcademicYearService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAcademicYear extends CreateRecord
{
    protected static string $resource = AcademicYearResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(AcademicYearService::class);
        $data = $service->processBeforeCreate($data, Auth::user()->id);

        // If status is set to Active, automatically activate the academic year
        if ($data['status'] === 'Active') {
            $this->afterCreate = function ($record) use ($service) {
                $service->ensureSingleActiveYear($record);
            };
        }

        return $data;
    }
}
