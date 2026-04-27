<?php

namespace App\Filament\Resources\PredefinedReports\Pages;

use App\Filament\Resources\PredefinedReports\PredefinedReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPredefinedReport extends EditRecord
{
    protected static string $resource = PredefinedReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
