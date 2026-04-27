<?php

namespace App\Filament\Resources\OfflineAttendanceSyncs\Pages;

use App\Filament\Resources\OfflineAttendanceSyncs\OfflineAttendanceSyncResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfflineAttendanceSyncs extends ListRecords
{
    protected static string $resource = OfflineAttendanceSyncResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
