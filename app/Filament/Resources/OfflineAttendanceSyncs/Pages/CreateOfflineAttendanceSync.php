<?php

namespace App\Filament\Resources\OfflineAttendanceSyncs\Pages;

use App\Filament\Resources\OfflineAttendanceSyncs\OfflineAttendanceSyncResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOfflineAttendanceSync extends CreateRecord
{
    protected static string $resource = OfflineAttendanceSyncResource::class;
}
