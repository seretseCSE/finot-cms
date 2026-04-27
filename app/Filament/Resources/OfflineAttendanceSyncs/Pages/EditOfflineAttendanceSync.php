<?php

namespace App\Filament\Resources\OfflineAttendanceSyncs\Pages;

use App\Filament\Resources\OfflineAttendanceSyncs\OfflineAttendanceSyncResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOfflineAttendanceSync extends EditRecord
{
    protected static string $resource = OfflineAttendanceSyncResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
