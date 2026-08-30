<?php

namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use App\Filament\Support\NavHubRegistry;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListFacilities extends ListRecords
{
    protected static string $resource = FacilityResource::class;

    public function mount(): void
    {
        $this->redirect(NavHubRegistry::hubUrl('facilities', 'facility'));
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
