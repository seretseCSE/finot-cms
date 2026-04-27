<?php

namespace App\Filament\Resources\LossRecordResource\Pages;

use App\Filament\Resources\LossRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLossRecords extends ListRecords
{
    protected static string $resource = LossRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
