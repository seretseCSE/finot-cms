<?php

namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use App\Filament\Support\NavHubRegistry;
use Filament\Resources\Pages\CreateRecord;

class CreateFacility extends CreateRecord
{
    protected static string $resource = FacilityResource::class;

    protected function getRedirectUrl(): string
    {
        return NavHubRegistry::hubUrl('facilities', 'facility');
    }
}
