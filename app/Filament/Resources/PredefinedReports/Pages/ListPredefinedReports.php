<?php

namespace App\Filament\Resources\PredefinedReports\Pages;

use App\Filament\Resources\PredefinedReports\PredefinedReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPredefinedReports extends ListRecords
{
    protected static string $resource = PredefinedReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
