<?php

namespace App\Filament\Resources\AidDistributionResource\Pages;

use App\Filament\Resources\AidDistributionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAidDistribution extends ViewRecord
{
    protected static string $resource = AidDistributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
