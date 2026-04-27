<?php

namespace App\Filament\Resources\AidDistributionResource\Pages;

use App\Filament\Resources\AidDistributionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAidDistribution extends EditRecord
{
    protected static string $resource = AidDistributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->canBeEdited()),
        ];
    }
}
