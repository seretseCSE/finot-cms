<?php

namespace App\Filament\Resources\SubjectOfferingResource\Pages;

use App\Filament\Resources\SubjectOfferingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubjectOffering extends EditRecord
{
    protected static string $resource = SubjectOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
