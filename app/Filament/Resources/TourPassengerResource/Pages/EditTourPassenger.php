<?php

namespace App\Filament\Resources\TourPassengerResource\Pages;

use App\Filament\Resources\TourPassengerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTourPassenger extends EditRecord
{
    protected static string $resource = TourPassengerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
