<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Filament\Support\NavHubRegistry;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    public function mount(): void
    {
        $this->redirect(NavHubRegistry::hubUrl('facilities', 'booking'));
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
