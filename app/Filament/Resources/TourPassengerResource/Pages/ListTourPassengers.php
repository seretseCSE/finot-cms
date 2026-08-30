<?php

namespace App\Filament\Resources\TourPassengerResource\Pages;

use App\Filament\Resources\TourPassengerResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListTourPassengers extends ListRecords
{
    protected static string $resource = TourPassengerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
